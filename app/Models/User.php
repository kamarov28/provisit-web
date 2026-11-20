<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'team',
    'phone',
    'is_active',
    'initial',
    'position',
    'notes',
    'can_view_all_visits',
    'can_edit_all_visits',
    'can_archive_visit',
    'can_delete_visit',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'can_view_all_visits' => 'boolean',
            'can_edit_all_visits' => 'boolean',
            'can_archive_visit' => 'boolean',
            'can_delete_visit' => 'boolean',
        ];
    }
}