
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Legacy USER_MASTER
            $table->string('initial', 10)->nullable();
            $table->string('position', 100)->nullable();
            $table->text('notes')->nullable();

            // Legacy access permissions
            $table->boolean('can_view_all_visits')->nullable();
            $table->boolean('can_edit_all_visits')->nullable();
            $table->boolean('can_archive_visit')->nullable();
            $table->boolean('can_delete_visit')->nullable();

            $table->index('initial', 'users_initial_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            // Legacy Customer_by_Company
            $table->string('customer_id_real', 50)->nullable();
            $table->string('sales_initial', 10)->nullable();
            $table->string('pic_group', 50)->nullable();
            $table->string('account_status', 50)->nullable();
            $table->date('lead_since')->nullable();

            $table->index('sales_initial', 'customers_sales_initial_index');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_sales_initial_index');

            $table->dropColumn([
                'customer_id_real',
                'sales_initial',
                'pic_group',
                'account_status',
                'lead_since',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_initial_index');

            $table->dropColumn([
                'initial',
                'position',
                'notes',
                'can_view_all_visits',
                'can_edit_all_visits',
                'can_archive_visit',
                'can_delete_visit',
            ]);
        });
    }
};
