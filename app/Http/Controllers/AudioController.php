<?php

namespace App\Http\Controllers;

use App\Models\ProcessingResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class AudioController extends Controller
{
    public function index()
    {
        // Real numbers for the dashboard instead of fixed zeros.
        return view('audio', [
            'totalSamples' => ProcessingResult::count(),
            'labeledSamples' => ProcessingResult::whereNotNull('label')->count(),
            'todaySamples' => ProcessingResult::whereDate('created_at', today())->count(),
            'recentResults' => ProcessingResult::latest()->take(5)->get(),
        ]);
    }

    public function process(Request $request)
    {
        // 51200 KB = 50 MB per file.
        $request->validate([
            'audio' => 'required|file|max:51200',
            'noise' => 'nullable|file|max:51200',
            'label' => 'nullable|string|max:100',
        ]);

        // Only accept known audio extensions, so nothing executable or
        // viewable as a web page can be stored in the public folder.
        $allowedExtensions = ['wav', 'mp3', 'm4a', 'flac', 'webm', 'ogg'];

        $extension = strtolower(
            $request->file('audio')->getClientOriginalExtension()
        );

        if (!in_array($extension, $allowedExtensions, true)) {
            return back()->withErrors([
                'audio' => 'Unsupported audio type. Allowed: WAV, MP3, M4A, FLAC, WebM, OGG.',
            ]);
        }

        if ($request->hasFile('noise')) {
            $noiseExtension = strtolower(
                $request->file('noise')->getClientOriginalExtension()
            );

            if (!in_array($noiseExtension, $allowedExtensions, true)) {
                return back()->withErrors([
                    'audio' => 'Unsupported noise file type. Allowed: WAV, MP3, M4A, FLAC, WebM, OGG.',
                ]);
            }
        }

        // Python interpreter and processing script come from config/audio.php
        // (AUDIO_PYTHON in .env), so nothing here is tied to one machine.
        $python = config('audio.python');
        $script = config('audio.script');

        if (!is_file($python) || !is_file($script)) {
            return back()->withErrors([
                'audio' => 'Python is not configured on this machine. Check AUDIO_PYTHON in the .env file.',
            ]);
        }

        // Let PHP wait for the whole Python run instead of stopping at its default limit.
        set_time_limit(config('audio.timeout') + 60);

        $runId = (string) Str::uuid();

        $runDirectory = "processing/{$runId}";

        $outputDir = storage_path("app/public/processing/{$runId}");

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        // The user's filename is kept only for display in the database.
        $originalFilename = $request->file('audio')->getClientOriginalName();

        // On disk the file always gets a fixed safe name, so it can never
        // collide with the pipeline outputs (converted.wav, processed_audio.wav, ...).
        $originalRelativePath = $request->file('audio')->storeAs(
            "processing/{$runId}",
            "original.{$extension}",
            'public'
        );

        $audioPath = storage_path("app/public/{$originalRelativePath}");

        $ffmpegPath = $this->ffmpegPath($python);

        $command = [
            $python,
            $script,
            $audioPath,
        ];

        // Optional noise reference: keep it inside the sample folder with a real
        // audio extension, and convert it to WAV so Python can always read it.
        $noisePath = '';

        if ($request->hasFile('noise')) {
            $noiseRelativePath = $request->file('noise')->storeAs(
                $runDirectory,
                "noise_original.{$noiseExtension}",
                'public'
            );

            $noisePath = storage_path("app/public/{$noiseRelativePath}");

            if ($noiseExtension !== 'wav') {
                $noiseWavPath = $outputDir . DIRECTORY_SEPARATOR . 'noise.wav';
                $noiseConverted = false;

                if ($ffmpegPath !== '' && is_file($ffmpegPath)) {
                    $noiseProcess = new Process([
                        $ffmpegPath,
                        '-hide_banner',
                        '-loglevel', 'error',
                        '-y',
                        '-i', $noisePath,
                        '-vn',
                        '-ac', '1',
                        '-c:a', 'pcm_s16le',
                        $noiseWavPath,
                    ]);

                    $noiseProcess->setTimeout(120);

                    try {
                        $noiseProcess->run();

                        $noiseConverted =
                            $noiseProcess->isSuccessful() && is_file($noiseWavPath);
                    } catch (ProcessTimedOutException $exception) {
                        $noiseConverted = false;
                    }
                }

                if (!$noiseConverted) {
                    Log::error('Noise file conversion failed.', [
                        'run_id' => $runId,
                        'noise_extension' => $noiseExtension,
                    ]);

                    Storage::disk('public')->deleteDirectory($runDirectory);

                    return back()->withErrors([
                        'audio' => 'The background noise file could not be read. Please try a WAV file.',
                    ]);
                }

                $noisePath = $noiseWavPath;
            }
        }

        $command[] = $noisePath;

        $command[] = $outputDir;

        $process = new Process($command, base_path());

        // Writable folder for matplotlib / numba caches, independent of the user account.
        $cacheDir = storage_path('app/python-cache');

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }

        // Take these from the current machine instead of hardcoding a user profile.
        $home = getenv('USERPROFILE') ?: (getenv('HOME') ?: $cacheDir);
        $temp = getenv('TEMP') ?: (getenv('TMP') ?: $cacheDir);
        $path = getenv('PATH') ?: '';

        $env = [
            'PYTHONHASHSEED' => '0',
            'PYTHONHOME' => '',
            'PYTHONPATH' => '',
            'USERPROFILE' => $home,
            'HOME' => $home,
            'TEMP' => $temp,
            'TMP' => $temp,
            'MPLBACKEND' => 'Agg',
            'MPLCONFIGDIR' => $cacheDir,
            'NUMBA_CACHE_DIR' => $cacheDir,
            // Return as soon as the audio is processed; the plots are drawn by a
            // background Python run and appear on the results page when ready.
            'AUDIO_PLOTS' => 'deferred',
            'AUDIO_PLOTS_LOG' => storage_path('logs/plots.log'),
        ];

        if (PHP_OS_FAMILY === 'Windows') {
            $windowsDir = getenv('SYSTEMROOT') ?: 'C:\\Windows';

            $env['SYSTEMROOT'] = $windowsDir;
            $env['WINDIR'] = $windowsDir;

            if ($path === '') {
                $path = $windowsDir . '\\System32;' . $windowsDir;
            }
        }

        if ($ffmpegPath !== '' && is_file($ffmpegPath)) {
            $env['IMAGEIO_FFMPEG_EXE'] = $ffmpegPath;

            $path = dirname($ffmpegPath) . PATH_SEPARATOR . $path;
        }

        $env['PATH'] = $path;

        $process->setEnv($env);
        $process->setTimeout(config('audio.timeout'));

        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            // Remove the upload and any partial outputs of this failed run.
            Storage::disk('public')->deleteDirectory($runDirectory);

            Log::warning('Audio processing timed out.', [
                'run_id' => $runId,
                'original_filename' => $originalFilename,
            ]);

            return back()->withErrors([
                'audio' => 'Processing took too long and was stopped. Please try a shorter recording.',
            ]);
        }

        $processedAudioPath =
            "{$runDirectory}/processed_audio.wav";

        $fundamentalAudioPath =
            "{$runDirectory}/processed_audio_fundamental_only.wav";

        $outputsExist =
            Storage::disk('public')->exists($processedAudioPath) &&
            Storage::disk('public')->exists($fundamentalAudioPath);

        if (!$process->isSuccessful() || !$outputsExist) {
            $errorOutput = trim($process->getErrorOutput());
            $standardOutput = trim($process->getOutput());

            $details = $errorOutput !== ''
                ? $errorOutput
                : $standardOutput;

            // Full technical details go to storage/logs/laravel.log, not to the user.
            Log::error('Audio processing failed.', [
                'run_id' => $runId,
                'original_filename' => $originalFilename,
                'details' => $details,
            ]);

            // Remove the upload and any partial outputs of this failed run.
            Storage::disk('public')->deleteDirectory($runDirectory);

            $message = str_contains($details, 'No peaks inside')
                ? 'No clear tone was detected between 200 and 4000 Hz. Please record a clearer sound and try again.'
                : 'Audio processing failed. Please try another recording.';

            return back()->withErrors([
                'audio' => $message,
            ]);
        }

        // Hide machine-specific folders from the output shown on the results page.
        $hiddenPaths = [
            $outputDir => '[sample]',
            storage_path() => '[storage]',
            base_path() => '[project]',
        ];

        if ($ffmpegPath !== '') {
            $hiddenPaths = [dirname($ffmpegPath) => '[ffmpeg]'] + $hiddenPaths;
        }

        $pythonOutput = str_replace(
            array_keys($hiddenPaths),
            array_values($hiddenPaths),
            $process->getOutput()
        );

        // Read the measurements that the Python pipeline already prints,
        // so they are stored as real columns (used later for the Excel export).
        $rawOutput = $process->getOutput();

        $sampleRate = preg_match('/Sample rate\s*:\s*(\d+)\s*Hz/', $rawOutput, $match)
            ? (int) $match[1]
            : null;

        $durationSeconds = preg_match('/Duration\s*:\s*([\d.]+)\s*s/', $rawOutput, $match)
            ? (float) $match[1]
            : null;

        $fundamentalHz = preg_match(
            '/(?:Fundamental frequency detected|manually-set fundamental):\s*([\d.]+)\s*Hz/',
            $rawOutput,
            $match
        )
            ? (float) $match[1]
            : null;

        ProcessingResult::create([
            'label' => $request->input('label'),
            'audio_format' => $extension,
            'sample_rate' => $sampleRate,
            'duration_seconds' => $durationSeconds,
            'fundamental_hz' => $fundamentalHz,
            'original_filename' => $originalFilename,
            'original_path' => $originalRelativePath,
            'processed_audio_path' => $processedAudioPath,
            'fundamental_audio_path' => $fundamentalAudioPath,
            'python_output' => $pythonOutput,
        ]);

        return redirect()
            ->route('results')
            ->with('success', 'Audio processed successfully.');
    }

    /**
     * Locate the bundled ffmpeg executable using the same Python environment.
     *
     * Starting Python just to ask for this path took seconds on every upload,
     * so the answer is cached per interpreter and only looked up again if the
     * cached file disappears (e.g. the virtual environment was recreated).
     */
    private function ffmpegPath(string $python): string
    {
        $cacheKey = 'audio.ffmpeg_path.' . md5($python);

        $cached = Cache::get($cacheKey);

        if (is_string($cached) && is_file($cached)) {
            return $cached;
        }

        $ffmpegProcess = new Process([
            $python,
            '-c',
            'import imageio_ffmpeg; print(imageio_ffmpeg.get_ffmpeg_exe())',
        ]);

        $ffmpegProcess->setTimeout(30);
        $ffmpegProcess->run();

        $ffmpegPath = $ffmpegProcess->isSuccessful()
            ? trim($ffmpegProcess->getOutput())
            : '';

        if ($ffmpegPath !== '' && is_file($ffmpegPath)) {
            Cache::forever($cacheKey, $ffmpegPath);
        }

        return $ffmpegPath;
    }

    /**
     * Delete one sample and all files belonging to its processing run.
     */
    public function destroy(ProcessingResult $result)
    {
        $runDirectory = dirname($result->original_path);

        if (
            !preg_match('#\Aprocessing/[a-f0-9-]{36}\z#i', $runDirectory) ||
            dirname($result->processed_audio_path) !== $runDirectory ||
            dirname($result->fundamental_audio_path) !== $runDirectory
        ) {
            abort(404);
        }

        $deleted = Storage::disk('public')->deleteDirectory($runDirectory);

        if (!$deleted) {
            return back()->withErrors([
                'delete' => 'Could not delete the sample files. Please try again.',
            ]);
        }

        $result->delete();

        return redirect()
            ->route('results')
            ->with('success', 'Sample and all its files deleted successfully.');
    }
}