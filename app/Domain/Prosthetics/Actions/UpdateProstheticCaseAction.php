<?php

namespace App\Domain\Prosthetics\Actions;

use App\Domain\Prosthetics\Models\ProstheticCase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateProstheticCaseAction
{
    public function execute(ProstheticCase $case, User $actor, array $data): ProstheticCase
    {
        return DB::transaction(function () use ($case, $actor, $data) {
            $financialFields = [
                'deposit_requested',
                'deposit_received',
                'deposit_amount',
                'total_amount',
                'final_payment_completed',
                'administrative_comments',
            ];

            $financialChanges = [];
            $oldFinancials = [];
            $newFinancials = [];

            foreach ($financialFields as $field) {
                if (array_key_exists($field, $data)) {
                    $oldVal = $case->{$field};
                    $newVal = $data[$field];

                    $changed = false;
                    if (in_array($field, ['deposit_amount', 'total_amount'])) {
                        $changed = abs((float) $oldVal - (float) $newVal) > 0.001;
                    } elseif (in_array($field, ['deposit_requested', 'deposit_received', 'final_payment_completed'])) {
                        $changed = (bool) $oldVal !== (bool) $newVal;
                    } else {
                        $changed = trim((string) $oldVal) !== trim((string) $newVal);
                    }

                    if ($changed) {
                        $financialChanges[] = [
                            'field' => $field,
                            'old' => $oldVal,
                            'new' => $newVal,
                        ];
                        $oldFinancials[$field] = $oldVal;
                        $newFinancials[$field] = $newVal;
                    }
                }
            }

            $fillable = [
                'patient_name',
                'patient_birth_date',
                'patient_code',
                'practitioner_id',
                'practitioner_name',
                'practitioner_specialty',
                'laboratory_id',
                'laboratory_name',
                'work_type',
                'tooth_number',
                'impression_date',
                'impression_type',
                'sent_to_lab_date',
                'expected_return_date',
                'returned_from_lab_date',
                'planned_placement_date',
                'actual_placement_date',
                'priority',
                'clinical_notes',
                'internal_notes',
                'deposit_requested',
                'deposit_received',
                'deposit_amount',
                'total_amount',
                'final_payment_completed',
                'administrative_comments',
                'attachments',
            ];

            foreach ($fillable as $field) {
                if (array_key_exists($field, $data)) {
                    $val = $data[$field];
                    if (in_array($field, ['patient_birth_date', 'impression_date', 'sent_to_lab_date', 'expected_return_date', 'returned_from_lab_date', 'planned_placement_date', 'actual_placement_date'])) {
                        $val = $val ? Carbon::parse($val) : null;
                    }
                    $case->{$field} = $val;
                }
            }

            if (!empty($financialChanges)) {
                $now = Carbon::now();
                $history = $case->status_history ?? [];

                $changeDescriptions = [];
                foreach ($financialChanges as $c) {
                    $f = $c['field'];
                    $old = $c['old'];
                    $new = $c['new'];
                    if ($f === 'deposit_amount') {
                        $changeDescriptions[] = sprintf("Acompte: %.2f € -> %.2f €", (float) $old, (float) $new);
                    } elseif ($f === 'total_amount') {
                        $changeDescriptions[] = sprintf("Total: %.2f € -> %.2f €", (float) $old, (float) $new);
                    } elseif ($f === 'deposit_received') {
                        $changeDescriptions[] = "Acompte: " . ($new ? 'Encaissé' : 'Non encaissé');
                    } elseif ($f === 'final_payment_completed') {
                        $changeDescriptions[] = "Règlement: " . ($new ? 'Soldé intégralement' : 'Solde restant dû');
                    } elseif ($f === 'deposit_requested') {
                        $changeDescriptions[] = "Acompte: " . ($new ? 'Demandé' : 'Non requis');
                    } elseif ($f === 'administrative_comments') {
                        $changeDescriptions[] = "Commentaire administratif mis à jour";
                    }
                }

                $note = "Mise à jour financière : " . implode(', ', $changeDescriptions);

                $history[] = [
                    'id' => count($history) + 1,
                    'case_id' => $case->id,
                    'from_status' => $case->status,
                    'to_status' => 'payment_update',
                    'changed_by_user_name' => $actor->name,
                    'changed_by_user_role' => $actor->role ?? 'reception',
                    'timestamp' => $now->toISOString(),
                    'note' => $note,
                    'changes' => $financialChanges,
                ];

                $case->status_history = $history;
            }

            $case->save();
            return $case;
        });
    }
}
