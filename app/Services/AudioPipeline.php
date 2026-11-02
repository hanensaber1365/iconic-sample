<?php

namespace App\Services;

use App\Models\ProcessingResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Shared setup for running the Python pipeline (python/process_audio.py).
 */
class AudioPipeline
{
    /**
     * Seconds without any new plot file before a background plot run counts as dead.
     */
    private const PLOTS_STALLED_AFTER = 45;

    /**
     * How many times the plots of one result may be restarted.
     */
    private const MAX_PLOT_RESTARTS = 3;

    public function python(): string
    {
        return config('audio.python');
    }

    public function script(): string
    {
        return config('audio.script');
    }

    /**
     * Locate the bundled ffmpeg executable using the same Python environment.
     *
     * Starting Python just to ask for this path took seconds on every upload,
     * so the answer is cached per interpreter and only looked up again if the
     * cached file disappears (e.g. the virtual environment was recreated).
     */
    public function ffmpegPath(): string
    {
        $python = $this->python();

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
     * Environment variables for a Python run.
     */
    public function environment(string $ffmpegPath = ''): array
    {
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
            // Output of the background plot runs.
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

        return $env;
    }

    /**
     * Start drawing the missing plots again when their background run died
     * (for example killed when the machine ran out of memory): plots are
     * missing and no plot file has been written for a while.
     */
    public function restartStalledPlots(ProcessingResult $result): void
    {
        $disk = Storage::disk('public');
        $runDirectory = $result->runDirectory();

        if (!$disk->exists($result->processed_audio_path)) {
            return;
        }

        $lastActivity = collect($disk->files($runDirectory))
            ->map(fn (string $file) => $disk->lastModified($file))
            ->max();

        if ($lastActivity === null || time() - $lastActivity < self::PLOTS_STALLED_AFTER) {
            return;
        }

        // Only one restart at a time per result, and a limited number in total.
        // The lock is not released: it expires on its own, which gives the new run
        // time to write its first plot before it could be judged stalled again.
        $lock = Cache::lock("audio.plots_restart.{$result->id}", self::PLOTS_STALLED_AFTER);

        if (!$lock->get()) {
            return;
        }

        $attemptsKey = "audio.plots_restarts.{$result->id}";
        $attempts = (int) Cache::get($attemptsKey, 0);

        if ($attempts >= self::MAX_PLOT_RESTARTS) {
            return;
        }

        Cache::forever($attemptsKey, $attempts + 1);

        // Same inputs as the original run.
        $outputDir = storage_path("app/public/{$runDirectory}");
        $audioPath = storage_path("app/public/{$result->original_path}");

        $noisePath = collect(['noise.wav', 'noise_original.wav'])
            ->map(fn (string $file) => "{$outputDir}/{$file}")
            ->first(fn (string $file) => is_file($file), '');

        // start_plots.py only launches the detached plot run, so this returns quickly.
        $process = new Process(
            [$this->python(), dirname($this->script()) . '/start_plots.py', $audioPath, $noisePath, $outputDir],
            base_path()
        );

        $process->setEnv($this->environment());
        $process->setTimeout(30);
        $process->run();

        Log::warning('Restarted stalled plot drawing.', [
            'result_id' => $result->id,
            'attempt' => $attempts + 1,
            'started' => $process->isSuccessful(),
            'details' => trim($process->getErrorOutput()),
        ]);
    }
}
