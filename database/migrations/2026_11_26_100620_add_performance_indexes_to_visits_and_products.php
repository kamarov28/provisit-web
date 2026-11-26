<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->index(['is_archived', 'visit_date'], 'visits_archived_date_index');
            $table->index(['is_archived', 'status'], 'visits_archived_status_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['principal_id', 'is_active', 'catalogue'], 'products_principal_active_catalogue_index');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex('visits_archived_date_index');
            $table->dropIndex('visits_archived_status_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_principal_active_catalogue_index');
        });
    }
};