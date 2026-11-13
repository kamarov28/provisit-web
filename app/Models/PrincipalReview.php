<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrincipalReview extends Model
{
    protected $guarded = ['id'];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(Principal::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(PrincipalReviewProduct::class);
    }
}
