@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
<style>
    .al-card {
        background: linear-gradient(160deg, var(--panel-2), var(--panel));
        border: 1px solid var(--line);
        border-radius: 20px;
        padding: 26px;
    }

    .al-card h3 {
        margin: 0 0 4px;
        font-family: var(--display);
        font-size: 18px;
        font-weight: 600;
    }

    .al-card p.al-sub {
        margin: 0 0 22px;
        color: var(--muted);
        font-size: 14px;
    }

    /* Hero */
    .al-hero {
        position: relative;
        overflow: hidden;
        border-radius: 26px;
        border: 1px solid var(--line);
        background: linear-gradient(135deg, #0b1a1c 0%, var(--ink) 70%);
        padding: 44px 44px 0;
        margin-bottom: 26px;
        box-shadow: 0 0 60px rgba(31, 209, 198, .08);
    }

    .al-hero h2 {
        margin: 0 0 12px;
        max-width: 620px;
        font-family: var(--display);
        font-weight: 700;
        font-size: clamp(28px, 4vw, 44px);
        line-height: 1.15;
    }

    .al-hero p {
        margin: 0;
        max-width: 520px;
        color: var(--muted);
        font-size: 16px;
    }

    .al-hero canvas {
        display: block;
        width: 100%;
        height: 230px;
        margin-top: 18px;
    }

    .al-legend {
        position: absolute;
        right: 30px;
        top: 30px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        font-size: 13px;
        color: var(--muted);
    }

    .al-legend i {
        display: inline-block;
        width: 22px;
        height: 3px;
        border-radius: 2px;
        margin-right: 8px;
        vertical-align: middle;
    }

    /* Statistics */
    .al-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 26px;
    }

    .al-stat {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .al-stat .al-label {
        color: var(--muted);
        font-size: 14px;
    }

    .al-stat .al-value {
        font-family: var(--display);
        font-size: 38px;
        font-weight: 700;
        line-height: 1.1;
    }

    .al-stat .al-ico {
        width: 54px;
        height: 54px;
        border-radius: 16px;
        display: grid;
        place-items: center;
        background: var(--teal-soft);
        border: 1px solid var(--line);
        box-shadow: 0 0 22px rgba(31, 209, 198, .12);
    }

    .al-stat .al-ico svg {
        width: 26px;
        height: 26px;
        stroke: var(--teal);
        fill: none;
        stroke-width: 1.7;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    /* Lower section */
    .al-grid {
        display: grid;
        grid-template-columns: 1.15fr .85fr;
        gap: 20px;
    }

    /* Pipeline */
    .al-steps {
        list-style: none;
        margin: 0;
        padding: 0;
        counter-reset: s;
    }

    .al-steps li {
        counter-increment: s;
        display: flex;
        gap: 14px;
        align-items: flex-start;
        padding: 12px 0;
        position: relative;
    }

    .al-steps li::before {
        content: counter(s);
        flex: none;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        border: 1px solid var(--teal);
        color: var(--teal);
        font-family: var(--display);
        font-size: 13px;
        font-weight: 600;
        background: var(--teal-soft);
        z-index: 1;
    }

    .al-steps li:not(:last-child)::after {
        content: "";
        position: absolute;
        left: 14.5px;
        top: 42px;
        bottom: -12px;
        width: 1px;
        background: var(--line);
    }

    .al-steps b {
        display: block;
        font-weight: 600;
    }

    .al-steps span {
        color: var(--muted);
        font-size: 14px;
    }

    /* Upload */
    .al-drop {
        display: block;
        cursor: pointer;
        text-align: center;
        border: 1.5px dashed rgba(31, 209, 198, .4);
        border-radius: 16px;
        padding: 30px 16px;
        margin-bottom: 16px;
        background: var(--teal-soft);
        transition: border-color .2s, box-shadow .2s;
    }

    .al-drop:hover,
    .al-drop:focus-within {
        border-color: var(--teal);
        box-shadow: 0 0 26px rgba(31, 209, 198, .18);
    }

    .al-drop svg {
        width: 34px;
        height: 34px;
        stroke: var(--teal);
        fill: none;
        stroke-width: 1.6;
        stroke-linecap: round;
        stroke-linejoin: round;
        margin-bottom: 8px;
    }

    .al-drop strong {
        display: block;
    }

    .al-drop small {
        color: var(--muted);
    }

    .al-drop input {
        position: absolute;
        opacity: 0;
        width: 1px;
        height: 1px;
    }

    .al-file-name {
        margin-top: 8px;
        color: var(--teal);
        font-size: 14px;
        word-break: break-all;
    }

    .al-field-label {
        display: block;
        margin: 4px 0 8px;
        font-weight: 500;
        font-size: 14px;
    }

    .al-field-label em {
        color: var(--muted);
        font-style: normal;
        font-weight: 400;
    }

    .al-input {
        width: 100%;
        padding: 11px 14px;
        margin-bottom: 20px;
        background: var(--ink);
        color: var(--text);
        border: 1px solid var(--steel);
        border-radius: 12px;
        font: inherit;
    }

    .al-input::file-selector-button {
        margin-right: 12px;
        padding: 6px 12px;
        border: 0;
        border-radius: 8px;
        background: var(--steel);
        color: var(--text);
        font: inherit;
        cursor: pointer;
    }

    .al-btn {
        width: 100%;
        padding: 14px 20px;
        border: 0;
        border-radius: 14px;
        cursor: pointer;
        background: linear-gradient(135deg, #2ee6da, #14a8a0);
        color: #021012;
        font-family: var(--display);
        font-weight: 700;
        font-size: 15px;
        box-shadow: 0 0 28px var(--teal-glow);
        transition: transform .15s, box-shadow .2s;
    }

    .al-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 0 40px rgba(31, 209, 198, .55);
    }

    .al-btn:disabled {
        transform: none;
        cursor: wait;
    }

    /* Source tabs and recorder */
    .al-tabs {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px;
        padding: 5px;
        margin-bottom: 18px;
        background: var(--ink);
        border: 1px solid var(--line);
        border-radius: 14px;
    }

    .al-tab {
        padding: 10px;
        border: 0;
        border-radius: 10px;
        background: transparent;
        color: var(--muted);
        font: inherit;
        font-weight: 600;
        cursor: pointer;
        transition: background .2s, color .2s;
    }

    .al-tab[aria-selected="true"] {
        background: var(--teal-soft);
        color: var(--teal);
        box-shadow: inset 0 0 0 1px var(--line);
    }

    .al-panel[hidden] {
        display: none;
    }

    .al-rec {
        text-align: center;
        padding: 22px 16px;
        margin-bottom: 16px;
        border: 1px solid var(--line);
        border-radius: 16px;
        background: var(--teal-soft);
    }

    .al-rec canvas {
        width: 100%;
        height: 70px;
        display: block;
        margin-bottom: 14px;
    }

    .al-timer {
        font-family: var(--display);
        font-size: 34px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    .al-hint {
        color: var(--muted);
        font-size: 14px;
        min-height: 22px;
        margin: 4px 0 16px;
    }

    .al-hint.err {
        color: var(--danger);
    }

    .al-mic {
        width: 76px;
        height: 76px;
        border-radius: 50%;
        cursor: pointer;
        border: 2px solid var(--teal);
        background: var(--ink);
        display: inline-grid;
        place-items: center;
        box-shadow: 0 0 28px var(--teal-glow);
        transition: transform .15s, background .2s, border-color .2s;
    }

    .al-mic:hover {
        transform: scale(1.05);
    }

    .al-mic svg {
        width: 30px;
        height: 30px;
        stroke: var(--teal);
        fill: none;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .al-mic .stop {
        display: none;
        width: 22px;
        height: 22px;
        border-radius: 5px;
        background: var(--danger);
    }

    .al-mic.live {
        border-color: var(--danger);
        box-shadow: 0 0 0 0 rgba(255, 107, 107, .5);
        animation: al-pulse 1.4s infinite;
    }

    .al-mic.live svg {
        display: none;
    }

    .al-mic.live .stop {
        display: block;
    }

    @keyframes al-pulse {
        70% { box-shadow: 0 0 0 18px rgba(255, 107, 107, 0); }
        100% { box-shadow: 0 0 0 0 rgba(255, 107, 107, 0); }
    }

    .al-preview {
        margin-top: 16px;
    }

    .al-preview audio {
        width: 100%;
    }

    .al-link {
        background: none;
        border: 0;
        color: var(--teal);
        font: inherit;
        cursor: pointer;
        padding: 6px;
    }

    /* Recent results */
    .al-empty {
        text-align: center;
        padding: 26px 10px;
        color: var(--muted);
        border: 1px dashed var(--steel);
        border-radius: 14px;
    }

    .al-empty b {
        display: block;
        color: var(--text);
        margin-bottom: 4px;
    }

    /* Processing overlay */
    .al-processing-overlay {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: rgba(3, 12, 14, 0.94);
        backdrop-filter: blur(10px);
    }

    .al-processing-overlay.active {
        display: flex;
    }

    .al-processing-box {
        width: 100%;
        max-width: 460px;
        padding: 38px 28px;
        text-align: center;
        background: linear-gradient(160deg, var(--panel-2), var(--panel));
        border: 1px solid var(--line);
        border-radius: 24px;
        box-shadow: 0 0 60px rgba(31, 209, 198, 0.12);
    }

    .al-processing-icon {
        width: 76px;
        height: 76px;
        margin: 0 auto 24px;
        border: 3px solid rgba(31, 209, 198, 0.18);
        border-top-color: var(--teal);
        border-radius: 50%;
        animation: al-spin 1s linear infinite;
    }

    .al-processing-box h2 {
        margin: 0 0 12px;
        font-family: var(--display);
        font-size: 25px;
    }

    .al-processing-box p {
        margin: 0;
        color: var(--muted);
        line-height: 1.7;
    }

    .al-processing-status {
        margin-top: 24px;
        color: var(--teal);
        font-size: 13px;
        letter-spacing: 1px;
    }

    .al-processing-dots::after {
        content: "";
        animation: al-dots 1.5s steps(4, end) infinite;
    }

    @keyframes al-spin {
        to { transform: rotate(360deg); }
    }

    @keyframes al-dots {
        0% { content: ""; }
        25% { content: "."; }
        50% { content: ".."; }
        75%, 100% { content: "..."; }
    }

    @media (prefers-reduced-motion: reduce) {
        .al-processing-icon,
        .al-processing-dots::after {
            animation: none;
        }
    }

    @media (max-width: 1100px) {
        .al-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 700px) {
        .al-stats {
            grid-template-columns: 1fr;
        }

        .al-hero {
            padding: 28px 22px 0;
        }

        .al-legend {
            display: none;
        }
    }
</style>
@endpush

@section('content')

<!-- Processing overlay -->
<div
    class="al-processing-overlay"
    id="processing-overlay"
    role="status"
    aria-live="polite"
    aria-hidden="true"
>
    <div class="al-processing-box">
        <div class="al-processing-icon" aria-hidden="true"></div>

        <h2>Processing your audio</h2>

        <p>
            Please wait while AudioLab cleans your recording,
            analyzes its frequency, and isolates the fundamental sound.
        </p>

        <div class="al-processing-status">
            SIGNAL PROCESSING IN PROGRESS<span class="al-processing-dots"></span>
        </div>
    </div>
</div>

<!-- Hero -->
<section class="al-hero">
    <h2>Isolate the fundamental frequency of any sound.</h2>

    <p>
        Upload a sample. AudioLab cleans it, finds f0, removes the
        harmonics and returns a pure fundamental-only WAV.
    </p>

    <div class="al-legend">
        <span><i style="background:#1fd1c6"></i>Fundamental (f0)</span>
        <span><i style="background:#2a3438"></i>Harmonics (removed)</span>
    </div>

    <canvas
        id="spectrum"
        aria-label="Animated spectrum showing the fundamental frequency and its harmonics"
    ></canvas>
</section>

<!-- Statistics -->
<section class="al-stats">
    <div class="al-card al-stat">
        <div>
            <div class="al-label">Total samples</div>
            <div class="al-value">{{ $totalSamples }}</div>
        </div>

        <div class="al-ico">
            <svg viewBox="0 0 24 24">
                <rect x="9" y="2" width="6" height="12" rx="3"/>
                <path d="M5 11a7 7 0 0 0 14 0M12 18v4"/>
            </svg>
        </div>
    </div>

    <div class="al-card al-stat">
        <div>
            <div class="al-label">Labeled samples</div>
            <div class="al-value">{{ $labeledSamples }}</div>
        </div>

        <div class="al-ico">
            <svg viewBox="0 0 24 24">
                <path d="M2 12h3l2-6 4 12 3-9 2 3h6"/>
            </svg>
        </div>
    </div>

    <div class="al-card al-stat">
        <div>
            <div class="al-label">Processed today</div>
            <div class="al-value">{{ $todaySamples }}</div>
        </div>

        <div class="al-ico">
            <svg viewBox="0 0 24 24">
                <path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>
            </svg>
        </div>
    </div>
</section>

<section class="al-grid">

    <!-- Upload -->
    <div class="al-card">
        <h3>Process new audio</h3>

        <p class="al-sub">
            Upload a sample to start the preprocessing pipeline.
        </p>

        @if ($errors->any())
            <div
                class="al-empty"
                style="border-color:var(--danger);margin-bottom:16px;color:var(--danger)"
            >
                {{ $errors->first() }}
            </div>
        @endif

        <form
            method="POST"
            action="/process-audio"
            enctype="multipart/form-data"
        >
            @csrf

            <div class="al-tabs" role="tablist">
                <button
                    type="button"
                    class="al-tab"
                    role="tab"
                    id="tab-record"
                    aria-selected="true"
                >
                    Record now
                </button>

                <button
                    type="button"
                    class="al-tab"
                    role="tab"
                    id="tab-upload"
                    aria-selected="false"
                >
                    Upload file
                </button>
            </div>

            <!-- Record -->
            <div
                class="al-panel"
                id="panel-record"
                role="tabpanel"
            >
                <div class="al-rec">
                    <canvas id="meter" aria-hidden="true"></canvas>

                    <div class="al-timer" id="timer">00:00</div>

                    <div class="al-hint" id="rec-hint">
                        Press the microphone to start recording.
                    </div>

                    <button
                        type="button"
                        class="al-mic"
                        id="mic"
                        aria-label="Start recording"
                    >
                        <svg viewBox="0 0 24 24">
                            <rect x="9" y="2" width="6" height="12" rx="3"/>
                            <path d="M5 11a7 7 0 0 0 14 0M12 18v4"/>
                        </svg>

                        <span class="stop"></span>
                    </button>

                    <div class="al-preview" id="preview" hidden>
                        <audio id="player" controls></audio>

                        <button type="button" class="al-link" id="redo">
                            Record again
                        </button>
                    </div>
                </div>
            </div>

            <!-- Upload -->
            <div
                class="al-panel"
                id="panel-upload"
                role="tabpanel"
                hidden
            >
                <label class="al-drop" for="audio">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 16V4M7 9l5-5 5 5M4 20h16"/>
                    </svg>

                    <strong>Choose an audio file</strong>

                    <small>WAV, MP3, M4A or FLAC</small>

                    <div class="al-file-name" id="audio-name"></div>

                    <input
                        type="file"
                        name="audio"
                        id="audio"
                        accept=".wav,.mp3,.m4a,.flac,.webm,.ogg"
                    >
                </label>
            </div>

            <label class="al-field-label" for="label">
                Sample label <em>(optional)</em>
            </label>

            <input
                class="al-input"
                type="text"
                name="label"
                id="label"
                maxlength="100"
                placeholder="e.g. class A"
                value="{{ old('label') }}"
            >

            <label class="al-field-label" for="noise">
                Background noise file <em>(optional)</em>
            </label>

            <input
                class="al-input"
                type="file"
                name="noise"
                id="noise"
                accept=".wav,.mp3,.m4a,.flac"
            >

            <button type="submit" class="al-btn" id="submit">
                Process audio
            </button>
        </form>
    </div>

    <!-- Pipeline -->
    <div class="al-card">
        <h3>What happens to your audio</h3>

        <p class="al-sub">
            The same pipeline runs for every sample.
        </p>

        <ol class="al-steps">
            <li>
                <div>
                    <b>Clean</b>
                    <span>Convert to WAV, remove DC offset, trim silence.</span>
                </div>
            </li>

            <li>
                <div>
                    <b>Filter</b>
                    <span>Band-pass 200–8000 Hz and reduce noise.</span>
                </div>
            </li>

            <li>
                <div>
                    <b>Analyze</b>
                    <span>FFT spectrum, peak detection, find f0.</span>
                </div>
            </li>

            <li>
                <div>
                    <b>Remove harmonics</b>
                    <span>Notch filters up to the 5th harmonic.</span>
                </div>
            </li>

            <li>
                <div>
                    <b>Export</b>
                    <span>Fundamental-only WAV and the detected frequency.</span>
                </div>
            </li>
        </ol>
    </div>
</section>

<!-- Recent results -->
<section class="al-card" style="margin-top:20px">
    <h3>Recent results</h3>

    <p class="al-sub">
        The last 5 processed samples and their fundamental frequency.
    </p>

    @if ($recentResults->count())

        @foreach ($recentResults as $recent)
            <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;padding:12px 0;border-bottom:1px solid var(--line);">
                <div>
                    <b style="word-break:break-all;">{{ $recent->original_filename }}</b>

                    <div style="color:var(--muted);font-size:13px;">
                        {{ $recent->created_at->format('d M Y · H:i') }}
                        @if ($recent->label)
                            · {{ $recent->label }}
                        @endif
                    </div>
                </div>

                <div style="color:var(--teal);font-family:var(--display);font-weight:600;">
                    {{ $recent->fundamental_hz !== null
                        ? number_format((float) $recent->fundamental_hz, 1) . ' Hz'
                        : '—' }}
                </div>
            </div>
        @endforeach

        <a
            href="/results"
            style="display:inline-block;margin-top:14px;color:var(--teal);font-weight:600;"
        >
            View all results
        </a>

    @else

        <div class="al-empty">
            <b>No results yet</b>
            Upload your first audio sample to see its fundamental frequency.
        </div>

    @endif
</section>

@endsection

@push('scripts')
<script>
    // Show the chosen file name inside the drop zone
    document.getElementById('audio').addEventListener('change', function () {
        document.getElementById('audio-name').textContent =
            this.files[0] ? this.files[0].name : '';
    });

    // ---- Source tabs ----
    const tabs = {
        record: ['tab-record', 'panel-record'],
        upload: ['tab-upload', 'panel-upload']
    };

    function showTab(name) {
        for (const key in tabs) {
            const on = key === name;

            document.getElementById(tabs[key][0])
                .setAttribute('aria-selected', on);

            document.getElementById(tabs[key][1]).hidden = !on;
        }
    }

    document.getElementById('tab-record').onclick = () => showTab('record');
    document.getElementById('tab-upload').onclick = () => showTab('upload');

    // ---- Direct recording ----
    // The recorded file is saved into the same input named "audio".
    (function () {
        const mic = document.getElementById('mic');
        const timerEl = document.getElementById('timer');
        const hint = document.getElementById('rec-hint');
        const preview = document.getElementById('preview');
        const player = document.getElementById('player');
        const input = document.getElementById('audio');
        const meter = document.getElementById('meter');
        const mctx = meter.getContext('2d');

        let rec, stream, chunks = [], t0, tick, raf, actx, analyser;

        function say(msg, err) {
            hint.textContent = msg;
            hint.classList.toggle('err', !!err);
        }

        function fmt(ms) {
            const seconds = Math.floor(ms / 1000);

            return String(Math.floor(seconds / 60)).padStart(2, '0')
                + ':'
                + String(seconds % 60).padStart(2, '0');
        }

        function drawMeter() {
            const r = devicePixelRatio || 1;
            const w = meter.clientWidth;
            const h = meter.clientHeight;

            meter.width = w * r;
            meter.height = h * r;

            mctx.setTransform(r, 0, 0, r, 0, 0);

            const data = new Uint8Array(analyser.fftSize);

            analyser.getByteTimeDomainData(data);
            mctx.clearRect(0, 0, w, h);

            mctx.strokeStyle = '#1fd1c6';
            mctx.lineWidth = 2;
            mctx.shadowColor = '#1fd1c6';
            mctx.shadowBlur = 10;

            mctx.beginPath();

            for (let i = 0; i < data.length; i++) {
                const x = i / data.length * w;
                const y = (data[i] / 255) * h;

                i ? mctx.lineTo(x, y) : mctx.moveTo(x, y);
            }

            mctx.stroke();
            raf = requestAnimationFrame(drawMeter);
        }

        async function start() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    audio: {
                        echoCancellation: false,
                        noiseSuppression: false,
                        autoGainControl: false
                    }
                });
            } catch (e) {
                say(
                    'Microphone access was blocked. Allow it in the browser settings and try again.',
                    true
                );
                return;
            }

            chunks = [];
            preview.hidden = true;
            input.value = '';

            rec = new MediaRecorder(stream);

            rec.ondataavailable = e => {
                if (e.data.size) chunks.push(e.data);
            };

            rec.onstop = finish;

            actx = new AudioContext();
            analyser = actx.createAnalyser();
            analyser.fftSize = 1024;

            actx.createMediaStreamSource(stream).connect(analyser);

            rec.start();

            t0 = Date.now();

            tick = setInterval(() => {
                timerEl.textContent = fmt(Date.now() - t0);
            }, 200);

            drawMeter();

            mic.classList.add('live');
            mic.setAttribute('aria-label', 'Stop recording');

            say('Recording… press again to stop.');
        }

        function finish() {
            clearInterval(tick);
            cancelAnimationFrame(raf);

            stream.getTracks().forEach(track => track.stop());
            actx.close();

            mctx.clearRect(0, 0, meter.width, meter.height);

            const type = rec.mimeType || 'audio/webm';
            const ext = type.includes('ogg') ? 'ogg' : 'webm';

            const blob = new Blob(chunks, { type });

            const file = new File(
                [blob],
                'recording-' + Date.now() + '.' + ext,
                { type }
            );

            const dt = new DataTransfer();

            dt.items.add(file);
            input.files = dt.files;

            player.src = URL.createObjectURL(blob);
            preview.hidden = false;

            mic.classList.remove('live');
            mic.setAttribute('aria-label', 'Start recording');

            say('Recording saved. Listen to it, then press Process audio.');
        }

        mic.onclick = () => {
            if (rec && rec.state === 'recording') {
                rec.stop();
            } else {
                start();
            }
        };

        document.getElementById('redo').onclick = () => {
            input.value = '';
            preview.hidden = true;
            timerEl.textContent = '00:00';

            say('Press the microphone to start recording.');
        };

        // Validate the audio before submitting the form.
        document.querySelector('form[action="/process-audio"]')
            .addEventListener('submit', function (event) {
                if (!input.files.length) {
                    event.preventDefault();
                    say('Record or upload an audio sample first.', true);
                }
            });
    })();

    // ---- Spectrum animation ----
    // Harmonics fade in and out while the fundamental stays bright.
    (function () {
        const cv = document.getElementById('spectrum');
        const ctx = cv.getContext('2d');

        const reduce = matchMedia(
            '(prefers-reduced-motion: reduce)'
        ).matches;

        let w, h, t = 0;

        function resize() {
            const r = devicePixelRatio || 1;

            w = cv.clientWidth;
            h = cv.clientHeight;

            cv.width = w * r;
            cv.height = h * r;

            ctx.setTransform(r, 0, 0, r, 0, 0);
        }

        addEventListener('resize', resize);
        resize();

        const f0 = 0.16;

        const peak = (x, center, spread) =>
            Math.exp(-Math.pow((x - center) / spread, 2));

        function draw() {
            ctx.clearRect(0, 0, w, h);

            const n = 160;
            const bw = w / n;

            const cycle = (Math.sin(t * 0.6) + 1) / 2;

            for (let i = 0; i < n; i++) {
                const x = i / n;

                let v = 0.06 + 0.05 *
                    Math.abs(Math.sin(i * 12.9 + t * 2));

                let harm = 0;

                for (let k = 2; k <= 5; k++) {
                    harm += peak(x, f0 * k, 0.006)
                        * (0.75 / k) * cycle;
                }

                const fund = peak(x, f0, 0.007)
                    * (0.9 + 0.05 * Math.sin(t * 3));

                const bh = (v + harm + fund) * (h - 24);
                const isF = fund > 0.25;

                ctx.fillStyle = isF
                    ? '#1fd1c6'
                    : (harm > 0.05 ? '#2a3438' : 'rgba(31,209,198,.18)');

                if (isF) {
                    ctx.shadowColor = '#1fd1c6';
                    ctx.shadowBlur = 18;
                } else {
                    ctx.shadowBlur = 0;
                }

                ctx.fillRect(
                    i * bw + 1,
                    h - bh,
                    Math.max(bw - 2, 1),
                    bh
                );
            }

            ctx.shadowBlur = 0;

            if (!reduce) {
                t += 0.02;
                requestAnimationFrame(draw);
            }
        }

        draw();
    })();

    // ---- Processing screen ----
    (function () {
        const form = document.querySelector('form[action="/process-audio"]');
        const overlay = document.getElementById('processing-overlay');
        const submitButton = document.getElementById('submit');
        const audioInput = document.getElementById('audio');

        form.addEventListener('submit', function (event) {
            // Do not show the overlay when no audio was selected or recorded.
            if (!audioInput.files.length) {
                return;
            }

            // Prevent duplicate submissions.
            if (form.dataset.processing === 'true') {
                event.preventDefault();
                return;
            }

            form.dataset.processing = 'true';

            overlay.classList.add('active');
            overlay.setAttribute('aria-hidden', 'false');

            submitButton.disabled = true;
            submitButton.textContent = 'Processing...';
            submitButton.style.opacity = '0.7';
            submitButton.style.cursor = 'wait';
        });
    })();
</script>
@endpush