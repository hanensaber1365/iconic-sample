<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processing_results', function (Blueprint $table) {
            $table->string('label')->nullable();
            $table->string('audio_format', 10)->nullable();
            $table->unsignedInteger('sample_rate')->nullable();
            $table->decimal('duration_seconds', 8, 3)->nullable();
            $table->decimal('fundamental_hz', 8, 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('processing_results', function (Blueprint $table) {
            $table->dropColumn([
                'label',
                'audio_format',
                'sample_rate',
                'duration_seconds',
                'fundamental_hz',
            ]);
        });
    }
};