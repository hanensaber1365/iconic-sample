<?php

return [
    // Python interpreter. Default: a venv inside the Laravel project.
    'python' => env('AUDIO_PYTHON', base_path(
        PHP_OS_FAMILY === 'Windows'
            ? 'python/.venv/Scripts/python.exe'
            : 'python/.venv/bin/python'
    )),

    'script' => base_path('python/process_audio.py'),

    'timeout' => (int) env('AUDIO_TIMEOUT', 300),
];