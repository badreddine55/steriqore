<?php

namespace App\Domain\Reporting\Jobs;

use App\Domain\Reporting\Enums\DataExportStatus;
use App\Domain\Reporting\Models\DataExportRequest;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * S6: "full data+file portability export" — every tenant-owned table
 * (found via information_schema, not a hardcoded list, so a table added
 * later can never be silently missed) plus every file the tenant owns on
 * the 'media' disk, zipped into one archive.
 *
 * A queued job has no "current tenant" bound the way a request does, so
 * every DataExportRequest read/write below is wrapped in its own short
 * TenantContext::run() — the same helper SetCurrentTenant middleware
 * uses — otherwise both the Eloquent TenantScope global scope AND the
 * Postgres RLS policy on data_export_requests fail closed (zero rows),
 * exactly like the RegisterTenantAction bug fixed earlier this session.
 * Each call is its own short transaction rather than one big one wrapping
 * the whole job: TenantContext::run() commits via DB::transaction(), and
 * a single transaction spanning the entire zip/S3 upload would keep the
 * "processing" status invisible to a polling client until the whole job
 * finishes, then jump straight to "completed" — the intermediate state
 * would never actually be observable. The tenant_id itself is learned via
 * a raw 'pgsql_admin' scalar read (RLS-bypassing, not a scoped model)
 * since nothing is bound as "the current tenant" yet at that point.
 */
class GenerateDataExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $dataExportRequestId) {}

    public function handle(): void
    {
        $tenantId = DB::connection('pgsql_admin')->table('data_export_requests')
            ->where('id', $this->dataExportRequestId)->value('tenant_id');

        if ($tenantId === null) {
            throw (new ModelNotFoundException)->setModel(DataExportRequest::class, [$this->dataExportRequestId]);
        }

        $tenantId = (string) $tenantId;
        $tenant = Tenant::findOrFail($tenantId);

        TenantContext::run($tenant, function () {
            DataExportRequest::findOrFail($this->dataExportRequestId)
                ->update(['status' => DataExportStatus::Processing]);
        });

        $workDir = storage_path("app/data-exports/{$this->dataExportRequestId}");
        File::ensureDirectoryExists($workDir);
        File::ensureDirectoryExists("{$workDir}/files");
        $zipPath = null;

        try {
            $this->dumpTables($tenantId, $workDir);
            $this->copyMediaFiles($tenantId, "{$workDir}/files");

            $zipPath = storage_path("app/data-exports/{$this->dataExportRequestId}.zip");
            $this->zipDirectory($workDir, $zipPath);

            $diskPath = "data-exports/{$tenantId}/{$this->dataExportRequestId}.zip";
            $stream = fopen($zipPath, 'r');
            if ($stream === false) {
                throw new RuntimeException("Could not open generated export archive at {$zipPath}.");
            }
            Storage::disk('backups')->put($diskPath, $stream);
            fclose($stream);

            TenantContext::run($tenant, function () use ($diskPath) {
                DataExportRequest::findOrFail($this->dataExportRequestId)->update([
                    'status' => DataExportStatus::Completed,
                    'file_path' => $diskPath,
                    'completed_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            TenantContext::run($tenant, function () use ($e) {
                DataExportRequest::findOrFail($this->dataExportRequestId)
                    ->update(['status' => DataExportStatus::Failed, 'error' => $e->getMessage()]);
            });
            throw $e;
        } finally {
            File::deleteDirectory($workDir);
            if ($zipPath !== null && file_exists($zipPath)) {
                unlink($zipPath);
            }
        }
    }

    private function dumpTables(string $tenantId, string $workDir): void
    {
        $tables = DB::connection('pgsql_admin')->select(<<<'SQL'
            select table_name from information_schema.columns
            where column_name = 'tenant_id' and table_schema = 'public'
            SQL);

        foreach ($tables as $row) {
            $rows = DB::connection('pgsql_admin')->table($row->table_name)->where('tenant_id', $tenantId)->get();
            file_put_contents("{$workDir}/{$row->table_name}.json", $rows->toJson(JSON_PRETTY_PRINT));
        }
    }

    private function copyMediaFiles(string $tenantId, string $filesDir): void
    {
        $mediaRows = DB::connection('pgsql_admin')->table('media')->where('tenant_id', $tenantId)->get();

        foreach ($mediaRows as $media) {
            $diskPath = "{$media->id}/{$media->file_name}";

            if (Storage::disk('media')->exists($diskPath)) {
                file_put_contents("{$filesDir}/{$media->file_name}", Storage::disk('media')->get($diskPath));
            }
        }
    }

    private function zipDirectory(string $sourceDir, string $zipPath): void
    {
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($files as $file) {
            $relativePath = substr($file->getPathname(), strlen($sourceDir) + 1);
            $zip->addFile($file->getPathname(), $relativePath);
        }

        $zip->close();
    }
}
