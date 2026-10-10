<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drive_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['drive_item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_access_requests');
    }
};
