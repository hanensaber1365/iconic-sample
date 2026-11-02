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
        Schema::create('processing_results', function (Blueprint $table) {
            $table->id();

            $table->string('original_filename');
            $table->string('original_path');

            $table->string('processed_audio_path')->nullable();
            $table->string('fundamental_audio_path')->nullable();

            $table->text('python_output')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processing_results');
    }
};

