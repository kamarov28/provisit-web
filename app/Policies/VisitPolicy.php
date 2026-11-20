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
        if (! $user->is_active) {
            return false;
        }

        if ($this->isPrivileged($user) || $user->can_view_all_visits) {
            return true;
        }

        return $this->isParticipant($user, $visit);
    }

    public function create(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function update(User $user, Visit $visit): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($this->isPrivileged($user) || $user->can_edit_all_visits) {
            return true;
        }

        return $this->isParticipant($user, $visit);
    }

    public function archive(User $user, Visit $visit): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $this->isPrivileged($user) || (bool) $user->can_archive_visit;
    }

    public function delete(User $user, Visit $visit): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $this->isPrivileged($user) || (bool) $user->can_delete_visit;
    }

    private function isPrivileged(User $user): bool
    {
        return in_array(strtolower((string) $user->role), ['admin', 'manager'], true)
            || strcasecmp((string) $user->email, 'marketing@prolabios.com') === 0;
    }

    private function isParticipant(User $user, Visit $visit): bool
    {
        if ((int) $visit->primary_sales_user_id === (int) $user->id || (int) $visit->created_by === (int) $user->id) {
            return true;
        }

        $userInitial = strtoupper(trim((string) $user->initial));
        if ($userInitial !== '' && is_array($visit->support_marketing)) {
            $supports = array_map(fn ($i) => strtoupper(trim((string) $i)), $visit->support_marketing);
            if (in_array($userInitial, $supports, true)) {
                return true;
            }
        }

        return false;
    }
}
