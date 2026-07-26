<?php

namespace App\Policies;

use App\Domain\Labeling\Models\Label;
use App\Models\User;

class LabelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('labels.view');
    }

    public function view(User $user, Label $label): bool
    {
        return $user->can('labels.view');
    }

    public function create(User $user): bool
    {
        return $user->can('labels.manage');
    }

    public function print(User $user, Label $label): bool
    {
        return $user->can('labels.manage');
    }
}
