<?php

use App\Domain\Compliance\Actions\MirrorMediaBackupAction;
use App\Domain\Compliance\Actions\RestoreMediaBackupAction;
use Illuminate\Support\Facades\Storage;

/**
 * Storage::fake() proves the mirror/restore logic itself (right paths,
 * right content, round-trips cleanly) the same way every other S3-backed
 * feature in this codebase is tested — the real MinIO round-trip is
 * verified manually inside the Docker container (docs/open-questions.md
 * OQ-11), same reasoning as S3-ANA-04's Browsershot PDF rendering.
 */
test('every file on the media disk is mirrored to the backups disk under media-backup/', function () {
    Storage::fake('media');
    Storage::fake('backups');
    Storage::disk('media')->put('cycles/1/photo.jpg', 'photo-bytes');
    Storage::disk('media')->put('cycles/2/photo.jpg', 'other-bytes');

    $count = app(MirrorMediaBackupAction::class)->execute();

    expect($count)->toBe(2);
    Storage::disk('backups')->assertExists('media-backup/cycles/1/photo.jpg');
    Storage::disk('backups')->assertExists('media-backup/cycles/2/photo.jpg');
    expect(Storage::disk('backups')->get('media-backup/cycles/1/photo.jpg'))->toBe('photo-bytes');
});

test('a lost media file can be fully recovered from its backup mirror', function () {
    Storage::fake('media');
    Storage::fake('backups');
    Storage::disk('media')->put('cycles/1/photo.jpg', 'original-bytes');
    app(MirrorMediaBackupAction::class)->execute();

    // Simulate data loss.
    Storage::disk('media')->delete('cycles/1/photo.jpg');
    expect(Storage::disk('media')->exists('cycles/1/photo.jpg'))->toBeFalse();

    $count = app(RestoreMediaBackupAction::class)->execute();

    expect($count)->toBe(1);
    Storage::disk('media')->assertExists('cycles/1/photo.jpg');
    expect(Storage::disk('media')->get('cycles/1/photo.jpg'))->toBe('original-bytes');
});

test('mirroring an empty media disk is a no-op, not an error', function () {
    Storage::fake('media');
    Storage::fake('backups');

    expect(app(MirrorMediaBackupAction::class)->execute())->toBe(0);
});
