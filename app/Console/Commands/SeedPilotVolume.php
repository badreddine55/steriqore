<?php

namespace App\Console\Commands;

use App\Domain\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * S5-ANA-04 (performance): generates a synthetic pilot-scale dataset for
 * one tenant to measure real query performance against — CLAUDE.md's
 * targets ("common screens < 2s; evidence search < 2s at pilot volume")
 * don't define an exact row count, so this picks a concrete, documented
 * scale (~1 year of a busy single-practice pilot) rather than leaving
 * "pilot volume" untested against any real number:
 *   3 devices, 3,000 cycles, ~10 pouches/cycle (30,000 labels/usages),
 *   2,000 patients, 500 batches.
 *
 * Bulk-inserts via the admin connection (bypasses RLS — appropriate for a
 * one-off fixture script, never for request-serving code) since this is
 * a fixture generator, not a feature — CLAUDE.md's "demo/pilot datasets
 * fully synthetic" applies here regardless.
 */
class SeedPilotVolume extends Command
{
    protected $signature = 'steriqore:seed-pilot-volume {--force}';

    protected $description = 'Seed a synthetic pilot-scale dataset for one tenant (performance testing only)';

    private const CYCLE_COUNT = 3000;

    private const ITEMS_PER_CYCLE = 10;

    private const PATIENT_COUNT = 2000;

    private const BATCH_COUNT = 500;

    // Postgres caps a single query at 65,535 bind parameters — with
    // ITEMS_PER_CYCLE=10 rows per cycle and ~9 columns on the widest
    // table, the outer chunk has to stay well under 1,000 cycles.
    private const CHUNK_SIZE = 500;

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Refusing to seed synthetic pilot-volume data in production without --force.');

            return self::FAILURE;
        }

        $db = DB::connection('pgsql_admin');
        $now = now();

        $this->line('Creating tenant, site, location, device, product, supplier...');
        $tenant = Tenant::create(['name' => 'Pilot Volume Clinic', 'slug' => 'pilot-volume-'.Str::lower(Str::random(8))]);
        $tenantId = $tenant->id;

        $siteId = (string) Str::uuid7();
        $db->table('sites')->insert(['id' => $siteId, 'tenant_id' => $tenantId, 'name' => 'Cabinet Principal', 'timezone' => 'Europe/Paris', 'is_primary' => true, 'created_at' => $now, 'updated_at' => $now]);

        $locationId = (string) Str::uuid7();
        $db->table('storage_locations')->insert(['id' => $locationId, 'tenant_id' => $tenantId, 'site_id' => $siteId, 'name' => 'Réserve', 'created_at' => $now, 'updated_at' => $now]);

        $userId = (string) Str::uuid7();
        $db->table('users')->insert(['id' => $userId, 'name' => 'Pilot Practitioner', 'email' => 'pilot-'.Str::lower(Str::random(8)).'@example.com', 'password' => Hash::make('password'), 'email_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        $db->table('tenant_user')->insert(['id' => (string) Str::uuid7(), 'tenant_id' => $tenantId, 'user_id' => $userId, 'status' => 'active', 'joined_at' => $now, 'created_at' => $now, 'updated_at' => $now]);

        $categoryId = (string) Str::uuid7();
        $db->table('product_categories')->insert(['id' => $categoryId, 'tenant_id' => $tenantId, 'name' => 'Instruments', 'created_at' => $now, 'updated_at' => $now]);
        $productId = (string) Str::uuid7();
        $db->table('products')->insert(['id' => $productId, 'tenant_id' => $tenantId, 'category_id' => $categoryId, 'name' => 'Sachet de stérilisation', 'reference' => 'REF-PILOT', 'unit' => 'piece', 'min_threshold' => 0, 'is_sterilizable' => true, 'created_at' => $now, 'updated_at' => $now]);
        $supplierId = (string) Str::uuid7();
        $db->table('suppliers')->insert(['id' => $supplierId, 'tenant_id' => $tenantId, 'name' => 'Pilot Supplier', 'created_at' => $now, 'updated_at' => $now]);

        $deviceIds = [];
        for ($d = 0; $d < 3; $d++) {
            $deviceId = (string) Str::uuid7();
            $deviceIds[] = $deviceId;
            $db->table('devices')->insert(['id' => $deviceId, 'tenant_id' => $tenantId, 'site_id' => $siteId, 'name' => "Autoclave {$d}", 'serial_number' => "SN-PILOT-{$d}", 'kind' => 'autoclave', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }

        $dluRuleId = (string) Str::uuid7();
        $db->table('dlu_rules')->insert(['id' => $dluRuleId, 'tenant_id' => $tenantId, 'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire', 'shelf_life_days' => 180, 'created_at' => $now, 'updated_at' => $now]);

        $this->line('Creating '.self::BATCH_COUNT.' batches...');
        $batchIds = $this->bulkInsert($db, 'batches', self::BATCH_COUNT, fn (int $i) => [
            'id' => (string) Str::uuid7(), 'tenant_id' => $tenantId, 'product_id' => $productId, 'supplier_id' => $supplierId,
            'batch_number' => "B-{$i}", 'expiry_date' => $now->clone()->addYear()->toDateString(), 'received_at' => $now, 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->line('Creating '.self::PATIENT_COUNT.' patients...');
        $patientIds = $this->bulkInsert($db, 'patients', self::PATIENT_COUNT, fn (int $i) => [
            'id' => (string) Str::uuid7(), 'tenant_id' => $tenantId, 'reference' => 'PAT-'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT),
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->line('Creating '.self::CYCLE_COUNT.' cycles with '.self::ITEMS_PER_CYCLE.' pouches each...');
        $cycleNumbersByDevice = [0, 0, 0];
        $progressBar = $this->output->createProgressBar(self::CYCLE_COUNT);

        for ($batchStart = 0; $batchStart < self::CYCLE_COUNT; $batchStart += self::CHUNK_SIZE) {
            $cycleRows = [];
            $itemRows = [];
            $labelRows = [];
            $usageRows = [];

            $batchEnd = min($batchStart + self::CHUNK_SIZE, self::CYCLE_COUNT);
            for ($c = $batchStart; $c < $batchEnd; $c++) {
                $deviceIndex = $c % 3;
                $deviceId = $deviceIds[$deviceIndex];
                $cycleNumbersByDevice[$deviceIndex]++;

                $cycleId = (string) Str::uuid7();
                $startedAt = $now->clone()->subDays(self::CYCLE_COUNT - $c);
                $completedAt = $startedAt->clone()->addMinutes(45);

                $cycleRows[] = [
                    'id' => $cycleId, 'tenant_id' => $tenantId, 'device_id' => $deviceId,
                    'operator_id' => $userId, 'cycle_number' => $cycleNumbersByDevice[$deviceIndex],
                    'status' => 'released', 'started_at' => $startedAt, 'completed_at' => $completedAt,
                    'created_at' => $now, 'updated_at' => $now,
                ];

                for ($i = 1; $i <= self::ITEMS_PER_CYCLE; $i++) {
                    $itemId = (string) Str::uuid7();
                    $batchId = $batchIds[array_rand($batchIds)];
                    $itemRows[] = [
                        'id' => $itemId, 'tenant_id' => $tenantId, 'cycle_id' => $cycleId, 'batch_id' => $batchId,
                        'description' => 'Kit chirurgical', 'sequence_in_cycle' => $i, 'created_at' => $now, 'updated_at' => $now,
                    ];

                    $labelId = (string) Str::uuid7();
                    $useByDate = $completedAt->clone()->addDays(180);
                    $labelRows[] = [
                        'id' => $labelId, 'tenant_id' => $tenantId, 'cycle_item_id' => $itemId, 'status' => 'used',
                        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire', 'shelf_life_days' => 180,
                        'sterilized_at' => $completedAt, 'use_by_date' => $useByDate->toDateString(), 'print_counter' => 1,
                        'created_at' => $now, 'updated_at' => $now,
                    ];

                    $patientId = $patientIds[array_rand($patientIds)];
                    $usageRows[] = [
                        'id' => (string) Str::uuid7(), 'tenant_id' => $tenantId, 'label_id' => $labelId,
                        'patient_id' => $patientId, 'practitioner_id' => $userId, 'procedure' => 'Détartrage',
                        'used_at' => $completedAt->clone()->addHour(), 'created_at' => $now,
                    ];
                }

                $progressBar->advance();
            }

            $db->table('cycles')->insert($cycleRows);
            $db->table('cycle_items')->insert($itemRows);
            $db->table('labels')->insert($labelRows);
            $db->table('label_usages')->insert($usageRows);
        }

        $progressBar->finish();
        $this->newLine(2);

        $releaseRows = $db->table('cycles')->where('tenant_id', $tenantId)->pluck('id')->map(fn ($cycleId) => [
            'id' => (string) Str::uuid7(), 'tenant_id' => $tenantId, 'cycle_id' => $cycleId, 'decision' => 'compliant',
            'released_by_user_id' => $userId, 'released_at' => $now, 'created_at' => $now,
        ])->all();
        foreach (array_chunk($releaseRows, self::CHUNK_SIZE) as $chunk) {
            $db->table('cycle_releases')->insert($chunk);
        }

        $this->info("Done. Tenant slug: {$tenant->slug}");

        return self::SUCCESS;
    }

    /**
     * @param  Closure(int): array<string, mixed>  $rowFactory
     * @return array<int, string> the generated ids
     */
    private function bulkInsert(Connection $connection, string $table, int $count, Closure $rowFactory): array
    {
        $ids = [];
        for ($start = 0; $start < $count; $start += self::CHUNK_SIZE) {
            $rows = [];
            $end = min($start + self::CHUNK_SIZE, $count);
            for ($i = $start; $i < $end; $i++) {
                $row = $rowFactory($i);
                $ids[] = $row['id'];
                $rows[] = $row;
            }
            $connection->table($table)->insert($rows);
        }

        return $ids;
    }
}
