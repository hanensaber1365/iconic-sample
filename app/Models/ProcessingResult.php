<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessingResult extends Model
{
    protected $fillable = [
        'original_filename',
        'original_path',
        'processed_audio_path',
        'fundamental_audio_path',
        'python_output',
        'label',
        'audio_format',
        'sample_rate',
        'duration_seconds',
        'fundamental_hz',
    ];
}