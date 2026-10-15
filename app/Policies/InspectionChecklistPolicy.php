<?php

namespace App\Policies;

use App\Models\InspectionChecklist;
use App\Models\User;

class InspectionChecklistPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_checklists');
    }

    public function view(User $user, InspectionChecklist $model): bool
    {
        return $user->can('manage_checklists');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_checklists');
    }

    public function update(User $user, InspectionChecklist $model): bool
    {
        return $user->can('manage_checklists');
    }

    public function delete(User $user, InspectionChecklist $model): bool
    {
        return $user->can('manage_checklists');
    }
}
