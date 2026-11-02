<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProcessingResult extends Model
{
    /**
     * Plot images written by the Python pipeline into the run folder (title => file).
     */
    public const PLOTS = [
        'Raw Signal' => 'raw_signal.png',
        'Trimmed Signal' => 'trimmed_signal.png',
        'Bandpass Signal' => 'bandpass_signal.png',
        'STFT Spectrogram' => 'stft_spectrogram.png',
        'Magnitude Analysis' => 'magnitude_analysis.png',
        'FFT Spectrum' => 'fft_spectrum.png',
        'Harmonic Peaks' => 'harmonic_peaks.png',
        'Harmonic Removal' => 'harmonic_removal.png',
        'Fundamental Comparison' => 'fundamental_comparison.png',
        'Waveform Comparison' => 'waveform_comparison.png',
    ];

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

    public function runDirectory(): string
    {
        return dirname($this->original_path);
    }

    /**
     * Plot files that already exist (they are drawn in the background after processing).
     */
    public function readyPlots(): array
    {
        $disk = Storage::disk('public');

        return array_values(array_filter(
            self::PLOTS,
            fn (string $file) => $disk->exists($this->runDirectory() . '/' . $file)
        ));
    }
}