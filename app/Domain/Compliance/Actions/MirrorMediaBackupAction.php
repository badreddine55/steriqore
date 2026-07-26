<?php

namespace App\Domain\Compliance\Actions;

use Illuminate\Support\Facades\Storage;

/**
 * spatie/laravel-backup only zips local paths — the real user-generated
 * files (cycle attachments, etc.) live on the 'media' S3 disk, so REC-10
 * ("restore PG + S3 backup") needs its own mechanism. Every object is
 * copied to the 'backups' disk under media-backup/, preserving its path,
 * so RestoreMediaBackupAction can copy it straight back.
 */
class MirrorMediaBackupAction
{
    private const string PREFIX = 'media-backup';

    /**
     * @return int number of files mirrored
     */
    public function execute(): int
    {
        $count = 0;

        foreach (Storage::disk('media')->allFiles() as $path) {
            Storage::disk('backups')->put(
                self::PREFIX.'/'.$path,
                Storage::disk('media')->get($path),
            );
            $count++;
        }

        return $count;
    }
}
