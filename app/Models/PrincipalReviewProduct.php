<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrincipalReviewProduct extends Model
{
    protected $guarded = ['id'];

    public function principalReview(): BelongsTo
    {
        return $this->belongsTo(PrincipalReview::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
