<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Visit extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'visit_date' => 'date:Y-m-d',
        'support_marketing' => 'array',
        'companion_sales' => 'array',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function principalReviews(): HasMany
    {
        return $this->hasMany(PrincipalReview::class);
    }

    public function actionPlans(): HasMany
    {
        return $this->hasMany(ActionPlan::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest();
    }
}
