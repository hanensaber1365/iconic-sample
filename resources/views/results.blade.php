@extends('layouts.app')

@section('title', 'Processing Results')

@push('styles')

<style>

    .al-pagehead {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 26px;
    }

    .al-pagehead h2 {
        margin: 0 0 4px;
        font-family: var(--display);
        font-size: 30px;
        font-weight: 700;
    }

    .al-pagehead p {
        margin: 0;
        color: var(--muted);
    }

    .al-pagehead-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .al-newbtn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 22px;
        border-radius: 14px;
        background: linear-gradient(135deg, #2ee6da, #14a8a0);
        color: #021012 !important;
        font-family: var(--display);
        font-weight: 700;
        font-size: 14px;
        box-shadow: 0 0 28px var(--teal-glow);
        transition: transform .15s;
    }

    .al-newbtn:hover {
        transform: translateY(-1px);
    }

    .al-exportbtn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 22px;
        border-radius: 14px;
        border: 1px solid var(--line);
        background: var(--teal-soft);
        color: var(--teal) !important;
        font-family: var(--display);
        font-weight: 700;
        font-size: 14px;
        transition: transform .15s;
    }

    .al-exportbtn:hover {
        transform: translateY(-1px);
    }

    .al-run {
        margin-bottom: 30px;
        background: linear-gradient(160deg, var(--panel-2), var(--panel));
        border: 1px solid var(--line);
        border-radius: 22px;
        padding: 26px;
    }

    .al-run-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        flex-wrap: wrap;
        margin-bottom: 24px;
    }

    .al-run-title {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .al-fico {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        display: grid;
        place-items: center;
        background: var(--teal-soft);
        border: 1px solid var(--line);
        flex: none;
    }

    .al-fico svg {
        width: 24px;
        height: 24px;
        stroke: var(--teal);
        fill: none;
        stroke-width: 1.7;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .al-run-title h3 {
        margin: 0 0 5px;
        font-size: 18px;
        font-weight: 600;
    }

    .al-run-title span {
        color: var(--muted);
        font-size: 13px;
    }

    .al-section-title {
        margin: 0 0 14px;
        font-family: var(--display);
        font-size: 18px;
    }

    .al-audio-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 28px;
    }

    .al-audio-card {
        padding: 18px;
        border: 1px solid var(--line);
        border-radius: 16px;
        background: rgba(255,255,255,.02);
    }

    .al-audio-card .label {
        color: var(--muted);
        font-size: 13px;
        margin-bottom: 8px;
    }

    .al-audio-card .filename {
        font-weight: 600;
        margin-bottom: 12px;
        word-break: break-word;
    }

    .al-audio-card audio {
        width: 100%;
        height: 40px;
    }

    .al-download {
        display: inline-block;
        margin-top: 10px;
        padding: 8px 13px;
        border-radius: 10px;
        border: 1px solid var(--line);
        background: var(--teal-soft);
        color: var(--teal) !important;
        font-size: 13px;
        font-weight: 600;
    }

    .al-plots {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .al-plot {
        overflow: hidden;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: rgba(255,255,255,.02);
    }

    .al-plot-title {
        padding: 13px 16px;
        border-bottom: 1px solid var(--line);
        font-size: 14px;
        font-weight: 600;
    }

    .al-plot img {
        display: block;
        width: 100%;
        height: auto;
        cursor: zoom-in;
        transition: transform .2s ease;
    }

    .al-plot img:hover {
        transform: scale(1.02);
    }

    .al-plot-pending {
        display: grid;
        place-items: center;
        aspect-ratio: 16 / 7;
        color: var(--muted);
        font-size: 13px;
        animation: al-pulse 1.4s ease-in-out infinite;
    }

    .al-plot-pending.failed {
        animation: none;
    }

    @keyframes al-pulse {
        50% { opacity: .45; }
    }

    .al-plot-download {
        display: block;
        padding: 10px 16px;
        border-top: 1px solid var(--line);
        color: var(--teal) !important;
        font-size: 13px;
    }

    .al-output {
        margin-top: 26px;
    }

    .al-output pre {
        margin: 0;
        padding: 18px;
        border: 1px solid var(--line);
        border-radius: 16px;
        background: #071417;
        color: #b8efea;
        overflow-x: auto;
        white-space: pre-wrap;
        font-size: 12px;
        line-height: 1.6;
    }

    /* =========================
       DELETE BUTTON
       ========================= */

    .al-delete-btn {
        border: 1px solid rgba(255, 80, 80, .35);
        border-radius: 12px;
        padding: 10px 18px;
        background: rgba(255, 80, 80, .08);
        color: #ff7777;
        font-family: var(--display);
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: all .15s ease;
    }

    .al-delete-btn:hover {
        background: rgba(255, 80, 80, .16);
        border-color: rgba(255, 80, 80, .55);
        transform: translateY(-1px);
    }

    /* =========================
       IMAGE LIGHTBOX
       ========================= */

    .al-lightbox {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 999999;
        background: rgba(0, 0, 0, 0.92);
        align-items: center;
        justify-content: center;
        padding: 40px;
    }

    .al-lightbox.show {
        display: flex;
    }

    .al-lightbox-image {
        display: block;
        max-width: 92vw;
        max-height: 88vh;
        width: auto !important;
        height: auto !important;
        object-fit: contain;
        border-radius: 12px;
        box-shadow: 0 0 50px rgba(0, 0, 0, 0.7);
        cursor: zoom-out;
    }

    .al-lightbox-close {
        position: absolute;
        top: 20px;
        right: 25px;
        width: 48px;
        height: 48px;
        border: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.15);
        color: white;
        font-size: 32px;
        line-height: 1;
        cursor: pointer;
        z-index: 1000000;
        transition: background .2s, transform .2s;
    }

    .al-lightbox-close:hover {
        background: rgba(255, 255, 255, 0.25);
        transform: scale(1.05);
    }

    .al-flash {
        margin-bottom: 22px;
        padding: 14px 18px;
        border-radius: 14px;
        border: 1px solid var(--line);
        background: var(--teal-soft);
        color: var(--teal);
        font-size: 14px;
        font-weight: 600;
    }

    .al-flash.error {
        border-color: rgba(255, 80, 80, .35);
        background: rgba(255, 80, 80, .08);
        color: #ff7777;
    }

    @media (max-width: 900px) {

        .al-audio-grid,
        .al-plots {
            grid-template-columns: 1fr;
        }

        .al-lightbox {
            padding: 20px;
        }

        .al-lightbox-image {
            max-width: 95vw;
            max-height: 85vh;
        }
    }

</style>

@endpush

@section('content')

<div class="al-pagehead">

    <div>

        <h2>Processing Results</h2>

        <p>
            All generated audio files, plots, and processing output.
        </p>

    </div>

    <div class="al-pagehead-actions">

        @if ($results->count())

            <a href="{{ route('results.export') }}" class="al-exportbtn">
                Export to Excel
            </a>

        @endif

        <a href="/" class="al-newbtn">
            + Process new audio
        </a>

    </div>

</div>

@if (session('success'))
    <div class="al-flash">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="al-flash error">{{ $errors->first() }}</div>
@endif

@if ($results->count())

@foreach ($results as $result)

    @php

        $runDirectory = $result->runDirectory();

        $readyPlots = $result->readyPlots();

        $plotsPending = count($readyPlots) < count(\App\Models\ProcessingResult::PLOTS);

    @endphp

    <section class="al-run">

        <div class="al-run-head">

            <div class="al-run-title">

                <div class="al-fico">

                    <svg viewBox="0 0 24 24">

                        <path d="M2 12h3l2-6 4 12 3-9 2 3h6"/>

                    </svg>

                </div>

                <div>

                    <h3>
                        {{ $result->original_filename }}
                    </h3>

                    <span>
                        Processed
                        {{ $result->created_at->format('d M Y · H:i') }}
                    </span>

                </div>

            </div>

            {{-- Delete this processing result --}}

            <form
                action="{{ route('results.destroy', $result) }}"
                method="POST"
                onsubmit="if (!confirm('Are you sure you want to delete this processing result and all its files?')) return false; this.querySelector('button').disabled = true; return true;"
            >

                @csrf

                @method('DELETE')

                <button
                    type="submit"
                    class="al-delete-btn"
                >
                    Delete
                </button>

            </form>

        </div>

        <h3 class="al-section-title">
            Audio outputs
        </h3>

        <div class="al-audio-grid">

            <div class="al-audio-card">

                <div class="label">
                    Processed audio
                </div>

                <div class="filename">
                    processed_audio.wav
                </div>

                <audio
                    controls
                    preload="none"
                    src="{{ asset('storage/' . $result->processed_audio_path) }}"
                >
                </audio>

                <a
                    class="al-download"
                    href="{{ asset('storage/' . $result->processed_audio_path) }}"
                    download
                >
                    Download processed audio
                </a>

            </div>

            <div class="al-audio-card">

                <div class="label">
                    Fundamental-only audio
                </div>

                <div class="filename">
                    processed_audio_fundamental_only.wav
                </div>

                <audio
                    controls
                    preload="none"
                    src="{{ asset('storage/' . $result->fundamental_audio_path) }}"
                >
                </audio>

                <a
                    class="al-download"
                    href="{{ asset('storage/' . $result->fundamental_audio_path) }}"
                    download
                >
                    Download fundamental audio
                </a>

            </div>

        </div>

        <h3 class="al-section-title">
            Signal processing plots
        </h3>

        <div
            class="al-plots"
            @if ($plotsPending)
                data-plots-status="{{ route('results.plots', $result) }}"
            @endif
        >

            @foreach (\App\Models\ProcessingResult::PLOTS as $title => $filename)

                @php

                    $plotPath = $runDirectory . '/' . $filename;

                    $plotUrl = asset('storage/' . $plotPath);

                    $plotReady = in_array($filename, $readyPlots, true);

                @endphp

                <div class="al-plot" data-plot-file="{{ $filename }}">

                    <div class="al-plot-title">
                        {{ $title }}
                    </div>

                    @if ($plotReady)

                        <img
                            src="{{ $plotUrl }}"
                            alt="{{ $title }}"
                            loading="lazy"
                            onclick="openImageViewer('{{ $plotUrl }}')"
                        >

                        <a
                            class="al-plot-download"
                            href="{{ $plotUrl }}"
                            download
                        >
                            Download image
                        </a>

                    @else

                        {{-- Still being drawn in the background; swapped in by the script below. --}}
                        <div class="al-plot-pending" data-plot-url="{{ $plotUrl }}" data-plot-title="{{ $title }}">
                            Generating plot…
                        </div>

                    @endif

                </div>

            @endforeach

        </div>

        @if ($result->python_output)

            <div class="al-output">

                <h3 class="al-section-title">
                    Processing output
                </h3>

                <pre>{{ $result->python_output }}</pre>

            </div>

        @endif

    </section>

@endforeach

@else

<div class="al-run">

    <h3>No processing results yet</h3>

    <p style="color:var(--muted);">
        Process your first audio sample to see all generated outputs here.
    </p>

    <a href="/" class="al-newbtn">
        + Process new audio
    </a>

</div>

@endif

{{-- Image Lightbox --}}

<div
    class="al-lightbox"
    id="alLightbox"
    onclick="closeImageViewer(event)"
>

    <button
        type="button"
        class="al-lightbox-close"
        onclick="closeImageViewer(event)"
    >
        &times;
    </button>

    <img
        id="alLightboxImage"
        class="al-lightbox-image"
        src=""
        alt="Image preview"
        onclick="event.stopPropagation()"
    >

</div>

@endsection

@push('scripts')

<script>

    function openImageViewer(imageUrl) {

        const lightbox = document.getElementById('alLightbox');

        const image = document.getElementById('alLightboxImage');

        if (!lightbox || !image) {
            return;
        }

        image.src = imageUrl;

        lightbox.classList.add('show');

        document.body.style.overflow = 'hidden';

    }


    function closeImageViewer(event) {

        if (event) {
            event.stopPropagation();
        }

        const lightbox = document.getElementById('alLightbox');

        const image = document.getElementById('alLightboxImage');

        if (!lightbox || !image) {
            return;
        }

        lightbox.classList.remove('show');

        image.src = '';

        document.body.style.overflow = '';

    }


    document.addEventListener('keydown', function(event) {

        if (event.key === 'Escape') {
            closeImageViewer();
        }

    });


    // Plots are drawn in the background after processing. Ask the server which
    // ones are ready and swap them in, until all are there or we give up.
    function showReadyPlot(placeholder) {

        const url = placeholder.dataset.plotUrl;

        const image = document.createElement('img');
        image.src = url + '?v=' + Date.now();
        image.alt = placeholder.dataset.plotTitle;
        image.onclick = () => openImageViewer(url);

        const download = document.createElement('a');
        download.className = 'al-plot-download';
        download.href = url;
        download.download = '';
        download.textContent = 'Download image';

        placeholder.replaceWith(image, download);

    }


    document.querySelectorAll('[data-plots-status]').forEach(function (grid) {

        const statusUrl = grid.dataset.plotsStatus;
        const startedAt = Date.now();
        // Long enough for the server to restart a background run that died.
        const giveUpAfterMs = 5 * 60 * 1000;

        async function poll() {

            try {
                const response = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });

                if (response.ok) {
                    const { ready } = await response.json();

                    ready.forEach(function (file) {
                        const placeholder = grid.querySelector(
                            '[data-plot-file="' + file + '"] .al-plot-pending'
                        );

                        if (placeholder) {
                            showReadyPlot(placeholder);
                        }
                    });
                }
            } catch (error) {
                // Network hiccup: just try again on the next tick.
            }

            const pending = grid.querySelectorAll('.al-plot-pending');

            if (pending.length === 0) {
                return;
            }

            if (Date.now() - startedAt > giveUpAfterMs) {
                pending.forEach(function (placeholder) {
                    placeholder.classList.add('failed');
                    placeholder.textContent = 'Plot not available.';
                });
                return;
            }

            setTimeout(poll, 2000);

        }

        setTimeout(poll, 1500);

    });

</script>

@endpush