<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drive_items', function (Blueprint $table) {
            $table->string('space')->default('personal')->after('type');
            $table->index(['space', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('drive_items', function (Blueprint $table) {
            $table->dropIndex(['space', 'parent_id']);
            $table->dropColumn('space');
        });
    }
};
