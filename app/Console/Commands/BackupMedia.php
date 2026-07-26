<?php

namespace App\Console\Commands;

use App\Domain\Compliance\Actions\MirrorMediaBackupAction;
use Illuminate\Console\Command;

/**
 * The S3 half of REC-10 ("restore PG + S3 backup"). Scheduled alongside
 * spatie/laravel-backup's own backup:run in routes/console.php — that
 * command only covers the database, since its file-backup feature zips
 * local paths and everything real here lives on the 'media' S3 disk.
 */
class BackupMedia extends Command
{
    protected $signature = 'steriqore:backup-media';

    protected $description = 'Mirror every file on the media disk to the backups disk';

    public function handle(MirrorMediaBackupAction $action): int
    {
        $count = $action->execute();
        $this->line("Mirrored {$count} file(s) from the media disk to the backups disk.");

        return self::SUCCESS;
    }
}
