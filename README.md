# AudioLab — Audio Recording and Preprocessing Application

AudioLab is a Laravel application for recording or uploading audio samples, running them through a Python signal-processing pipeline, viewing the results, and exporting the sample data to Excel for data collection.

It prepares and analyzes audio data. It does **not** train or evaluate an AI model.

## What it does

1. Records audio from the microphone in the browser, or accepts an uploaded audio file.
2. Sends the audio to the Python script `python/process_audio.py`.
3. The script converts the audio to WAV, cleans it, analyzes its spectrum, detects the fundamental frequency, and removes the harmonics.
4. Shows two processed audio files, ten plots, and the processing log for each sample.
5. Stores each sample with its label, format, sample rate, duration, and fundamental frequency.
6. Exports all samples to an Excel (`.xlsx`) file.

## Requirements

| Tool | Version |
| --- | --- |
| PHP | 8.3 or newer |
| Composer | 2.x |
| Node.js and npm | any recent LTS version |
| Python | 3.10 (the version the project was tested with) |

| Database | MySQL (used during development) or SQLite |

FFmpeg does not need to be installed separately. It comes with the Python package `imageio-ffmpeg`.

## Setup on a new machine

Run the commands from the project folder, one at a time (Windows PowerShell).

### 1. Install the PHP packages and create the settings file

```
composer install
copy .env.example .env
php artisan key:generate
```

### 2. Choose the database

Open `.env` and set the database lines using **one** of these two options.

**Option A — SQLite (no database server needed):**

```
DB_CONNECTION=sqlite
```

Remove or comment out the other `DB_` lines (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).

**Option B — MySQL:**

First create an empty database named `iconic` (for example from phpMyAdmin), then set:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=iconic
DB_USERNAME=root
DB_PASSWORD=
```

Use the username and password of your own MySQL server.

### 3. Create the tables and finish the Laravel setup

```
php artisan migrate
php artisan storage:link
npm install
npm run build
```

With SQLite, `php artisan migrate` asks whether to create the database file. Answer `yes`.

### 4. Create the Python environment

```
python -m venv python\.venv
python\.venv\Scripts\python.exe -m pip install -r python\requirements.txt
```

Installing the Python packages takes several minutes.

No path needs to be edited: by default the application uses the Python environment in `python/.venv` inside the project.

## Running

```
php artisan serve
```

Then open `http://127.0.0.1:8000` in the browser.

Microphone recording works only on `localhost` / `127.0.0.1` or over HTTPS. This is a browser rule.

The first processing run after setup is slower than later runs, because the Python libraries build their caches.

## Configuration (`.env`)

| Variable | Meaning | Default |
| --- | --- | --- |
| `AUDIO_PYTHON` | Full path to a Python interpreter. Leave empty to use `python/.venv`. | empty |
| `AUDIO_TIMEOUT` | Maximum processing time for one sample, in seconds. | `300` |

After changing `.env`, run `php artisan config:clear`.

## Using the application

- **Dashboard (`/`)**: record or upload a sample, optionally add a label and a background-noise file, then press "Process audio".
- **Results (`/results`)**: listen to and download the processed audio, view the plots, read the processing log, or delete a sample.
- **Export to Excel**: downloads one row per sample.

Accepted audio types: WAV, MP3, M4A, FLAC, WebM, OGG. Maximum size: 50 MB per file.

### Excel columns

Sample ID, Recorded at, Original filename, Label, Audio format, Sample rate (Hz), Duration (s), Fundamental frequency (Hz), Original file, Processed audio file, Fundamental-only audio file.

## Where things are stored

| Item | Location |
| --- | --- |
| Each sample's files | `storage/app/public/processing/<sample-id>/` |
| Original recording | `original.<ext>` inside the sample folder |
| Processed audio | `processed_audio.wav` and `processed_audio_fundamental_only.wav` |
| Plots | ten `.png` files inside the sample folder |
| Sample data | `processing_results` table in the database |
| Error details | `storage/logs/laravel.log` |

## Main files

| File | Role |
| --- | --- |
| `app/Http/Controllers/AudioController.php` | Receives the audio, runs Python, saves the sample, deletes a sample |
| `app/Http/Controllers/ProcessingResultController.php` | Results page and Excel export |
| `app/Models/ProcessingResult.php` | Sample model |
| `config/audio.php` | Python path and timeout |
| `python/process_audio.py` | Signal-processing pipeline |
| `python/requirements.txt` | Python packages |
| `resources/views/audio.blade.php` | Dashboard, recorder, upload form |
| `resources/views/results.blade.php` | Results page |

## Known behavior

- **Delete** removes the whole sample: the original recording, all outputs, and the database record.
- A recording with no clear tone between 200 and 4000 Hz cannot be processed. The application shows a message and stores nothing.
- When processing fails or times out, the sample's files are removed and the details are written to the log.
- Non-WAV recordings are converted to mono 44100 Hz WAV before processing.
- The application has no login. It is intended to run locally on one machine.