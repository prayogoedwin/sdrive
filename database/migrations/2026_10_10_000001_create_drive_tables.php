<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('drive_items')->nullOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('mime_type')->nullable();
            $table->string('extension', 32)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('storage_path')->nullable();
            $table->text('path')->nullable();
            $table->uuid('trash_batch')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'parent_id']);
            $table->index('deleted_at');
        });

        Schema::create('drive_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drive_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('permission');
            $table->timestamps();

            $table->unique(['drive_item_id', 'user_id']);
        });

        Schema::create('drive_stars', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drive_item_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'drive_item_id']);
        });

        Schema::create('drive_opens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drive_item_id')->constrained()->cascadeOnDelete();
            $table->timestamp('opened_at');

            $table->unique(['user_id', 'drive_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_opens');
        Schema::dropIfExists('drive_stars');
        Schema::dropIfExists('drive_shares');
        Schema::dropIfExists('drive_items');
    }
};
