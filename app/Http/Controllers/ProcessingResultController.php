<?php

namespace App\Http\Controllers;

use App\Models\ProcessingResult;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ProcessingResultController extends Controller
{
    public function index()
    {
        $results = ProcessingResult::latest()->get();

        return view('results', compact('results'));
    }

    /**
     * Export all samples and their measurements as an Excel (.xlsx) file.
     */
    public function export()
    {
        $results = ProcessingResult::oldest()->get();

        $path = storage_path('app/export-' . Str::uuid() . '.xlsx');

        $writer = new Writer();
        $writer->openToFile($path);

        $writer->addRow(Row::fromValues([
            'Sample ID',
            'Recorded at',
            'Original filename',
            'Label',
            'Audio format',
            'Sample rate (Hz)',
            'Duration (s)',
            'Fundamental frequency (Hz)',
            'Original file',
            'Processed audio file',
            'Fundamental-only audio file',
        ]));

        foreach ($results as $result) {
            $writer->addRow(Row::fromValues([
                $result->id,
                $result->created_at->format('Y-m-d H:i:s'),
                $result->original_filename,
                $result->label ?? '',
                $result->audio_format ?? '',
                $result->sample_rate ?? '',
                $result->duration_seconds !== null ? (float) $result->duration_seconds : '',
                $result->fundamental_hz !== null ? (float) $result->fundamental_hz : '',
                $result->original_path,
                $result->processed_audio_path,
                $result->fundamental_audio_path,
            ]));
        }

        $writer->close();

        return response()
            ->download($path, 'audiolab-samples-' . now()->format('Y-m-d_His') . '.xlsx')
            ->deleteFileAfterSend(true);
    }
}