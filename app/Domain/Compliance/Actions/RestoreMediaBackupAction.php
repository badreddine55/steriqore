<?php

namespace App\Domain\Compliance\Actions;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The reverse of MirrorMediaBackupAction — copies every object back from
 * the 'backups' disk's media-backup/ prefix onto the 'media' disk,
 * stripping the prefix to restore each file's original path.
 */
class RestoreMediaBackupAction
{
    private const string PREFIX = 'media-backup';

    /**
     * @return int number of files restored
     */
    public function execute(): int
    {
        $count = 0;

        foreach (Storage::disk('backups')->allFiles(self::PREFIX) as $backedUpPath) {
            $originalPath = Str::after($backedUpPath, self::PREFIX.'/');

            Storage::disk('media')->put(
                $originalPath,
                Storage::disk('backups')->get($backedUpPath),
            );
            $count++;
        }

        return $count;
    }
}
