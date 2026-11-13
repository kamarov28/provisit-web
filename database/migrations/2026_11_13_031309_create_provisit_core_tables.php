<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Customers
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code', 50)->nullable()->unique();
            $table->string('name');
            $table->string('sector', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Customer PICs
        Schema::create('customer_pics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('name');
            $table->string('position', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        // 3. Principals
        Schema::create('principals', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('country', 100)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Products
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('principal_id')->nullable()->constrained('principals')->nullOnDelete();
            $table->string('catalogue', 100)->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('unit', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 5. Visits
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->string('visit_code', 50)->unique();
            $table->string('title');
            $table->date('visit_date')->index();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name_snapshot');
            $table->foreignId('customer_pic_id')->nullable()->constrained('customer_pics')->nullOnDelete();
            $table->string('pic_name_snapshot')->nullable();
            $table->string('pic_position_snapshot')->nullable();
            $table->foreignId('primary_sales_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visit_type', 50)->default('On-site'); // On-site, Online, Hybrid
            $table->string('visit_purpose', 100)->default('Regular');
            $table->text('agenda')->nullable();
            $table->text('previsit_notes')->nullable();
            $table->text('postvisit_result')->nullable();
            $table->string('status', 50)->default('Planned')->index(); // Planned, Confirmed, In Progress, Completed, Cancelled
            $table->string('calendar_event_id')->nullable();
            $table->timestamp('report_completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });

        // 6. Principal Reviews
        Schema::create('principal_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignId('principal_id')->nullable()->constrained('principals')->nullOnDelete();
            $table->string('principal_name_snapshot');
            $table->string('exposure_type', 50)->nullable(); // Introduction, Demo, Evaluation, Troubleshooting, Follow-up, Commercial
            $table->string('opportunity_status', 50)->nullable(); // No Signal, Interested, Sample, Demo, Quotation, Negotiation, Won, Lost
            $table->text('notes')->nullable();
            $table->text('customer_feedback')->nullable();
            $table->text('next_step')->nullable();
            $table->string('client_key', 50)->nullable();
            $table->timestamps();
        });

        // 7. Principal Review Products
        Schema::create('principal_review_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('principal_review_id')->constrained('principal_reviews')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_catalogue', 100)->nullable();
            $table->text('product_description_snapshot')->nullable();
            $table->timestamps();
        });

        // 8. Action Plans
        Schema::create('action_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->text('task_description');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('priority', 30)->default('Medium'); // Low, Medium, High, Urgent
            $table->string('status', 30)->default('Pending'); // Pending, In Progress, Completed, Cancelled
            $table->timestamp('completed_at')->nullable();
            $table->string('client_key', 50)->nullable();
            $table->timestamps();
        });

        // 9. Audit Logs
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50); // CREATE, UPDATE, DELETE, RESCHEDULE, STATUS_CHANGE
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('action_plans');
        Schema::dropIfExists('principal_review_products');
        Schema::dropIfExists('principal_reviews');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('products');
        Schema::dropIfExists('principals');
        Schema::dropIfExists('customer_pics');
        Schema::dropIfExists('customers');
    }
};
