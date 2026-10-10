<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drive_items', function (Blueprint $table) {
            $table->string('link_token', 64)->nullable()->unique();
            $table->string('link_permission')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('drive_items', function (Blueprint $table) {
            $table->dropUnique(['link_token']);
            $table->dropColumn(['link_token', 'link_permission']);
        });
    }
};
