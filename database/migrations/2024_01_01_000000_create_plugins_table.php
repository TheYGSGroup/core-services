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
        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('title');
            $table->string('version');
            $table->text('description')->nullable();
            $table->string('author')->nullable();
            $table->string('main_class');
            $table->boolean('is_active')->default(false);
            $table->json('metadata')->nullable(); // Full plugin.json data
            $table->json('requirements')->nullable();
            $table->string('root_path'); // Path to plugin directory
            $table->timestamp('installed_at');
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plugins');
    }
};

