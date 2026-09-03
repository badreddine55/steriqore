<?php

namespace App\Domain\Prosthetics\Actions;

use App\Domain\Prosthetics\Models\ProstheticCase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ChangeProstheticCaseStatusAction
{
    public function execute(ProstheticCase $case, User $actor, string $newStatus, ?string $note = null): ProstheticCase
    {
        return DB::transaction(function () use ($case, $actor, $newStatus, $note) {
            $now = Carbon::now();
            $history = $case->status_history ?? [];
            $history[] = [
                'id' => count($history) + 1,
                'case_id' => $case->id,
                'from_status' => $case->status,
                'to_status' => $newStatus,
                'changed_by_user_name' => $actor->name,
                'changed_by_user_role' => $actor->role ?? 'practitioner',
                'timestamp' => $now->toISOString(),
                'note' => $note,
            ];

            $case->status = $newStatus;
            $case->status_history = $history;

            if ($newStatus === 'sent_to_lab' && !$case->sent_to_lab_date) {
                $case->sent_to_lab_date = $now;
            } elseif ($newStatus === 'received_at_practice' && !$case->returned_from_lab_date) {
                $case->returned_from_lab_date = $now;
            } elseif ($newStatus === 'placed' && !$case->actual_placement_date) {
                $case->actual_placement_date = $now;
            }

            $case->save();
            return $case;
        });
    }
}
