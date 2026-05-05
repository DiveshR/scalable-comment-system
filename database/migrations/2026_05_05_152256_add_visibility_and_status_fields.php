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
            $table->boolean('is_active')->default(true)->after('is_admin');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_visible')->default(true)->after('body');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->boolean('is_visible')->default(true)->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('is_visible');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn('is_visible');
        });
    }
};
