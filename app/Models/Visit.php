<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visit extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'visit_date' => 'date:Y-m-d',
        'report_completed_at' => 'datetime',
        'is_archived' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerPic(): BelongsTo
    {
        return $this->belongsTo(CustomerPic::class, 'customer_pic_id');
    }

    public function primarySalesUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_sales_user_id');
    }

    public function principalReviews(): HasMany
    {
        return $this->hasMany(PrincipalReview::class);
    }

    public function actionPlans(): HasMany
    {
        return $this->hasMany(ActionPlan::class);
    }
}
