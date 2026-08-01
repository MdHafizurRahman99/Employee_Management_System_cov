<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('auth_source')->default('local');
            $table->unsignedBigInteger('odoo_user_id')->nullable()->unique();
            $table->unsignedBigInteger('odoo_employee_id')->nullable();
            $table->unsignedBigInteger('odoo_resource_id')->nullable();
            $table->timestamp('odoo_last_synced_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['odoo_user_id']);
            $table->dropColumn([
                'auth_source',
                'odoo_user_id',
                'odoo_employee_id',
                'odoo_resource_id',
                'odoo_last_synced_at',
            ]);
        });
    }
};
