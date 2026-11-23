<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Visit;

class VisitPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function view(User $user, Visit $visit): bool
    {
        return (bool) $user->is_active;
    }

    public function create(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function update(User $user, Visit $visit): bool
    {
        return (bool) $user->is_active;
    }

    public function delete(User $user, Visit $visit): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $visit->created_by === $user->id
            || $visit->primary_sales_user_id === $user->id
            || (bool) $user->can_delete_visit
            || in_array(strtolower((string) $user->role), ['admin', 'admin / product manager', 'marketing_master'], true)
            || $user->email === 'marketing@prolabios.com';
    }
}