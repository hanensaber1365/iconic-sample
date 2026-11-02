# %% [markdown]
# # 🎧 Universal Audio Pre-Processing Pipeline
# 
# **Purpose:** Take an audio recording in **any common format** (MP3, MP4, M4A, OGG, FLAC, AIFF, WMA, WAV, …),
# convert it to a clean WAV, and run it through a focused signal-cleaning pipeline —
# no feature extraction, just the pre-processing you need before any downstream analysis.
# 
# **Pipeline overview:**
# 1. Imports & configuration
# 2. Universal format → WAV conversion
# 3. Audio loading
# 4. Pre-processing (DC offset → trim silence → bandpass filter → noise reduction → internal silence removal → normalization)
# 5. Spectral analysis & visualization
#    - STFT spectrogram
#    - **Magnitude vs. Time** and **Magnitude vs. Angular Frequency** plots
#    - FFT spectrum (Hz) with peak detection
# 6. **Frequency-range threshold + harmonic rejection** — filters out overtone peaks
#    (e.g. a piano tone's 2nd harmonic sitting at ~2× the fundamental) that fall outside
#    the frequency range you care about, so only genuine fundamental peaks survive.
# 7. Export the cleaned WAV
# 
# **Added in this version:** Nyquist-frequency checks (3.1), an anti-aliasing low-pass (3.1),
# harmonic detection + removal with notch filters (6.1), and a fundamental-only band-pass (6.2).
# 

# %% [markdown]
# ---
# ## 1 — Imports & Global Configuration

# %%
# -- Standard scientific stack ----------------------------------------------
import numpy as np                        # numerical arrays and FFT math
import matplotlib.pyplot as plt           # all plotting
import os                                 # path helpers (basename, exists, splitext...)
import shutil                             # simple file copy + PATH lookups
import warnings                           # suppress harmless librosa warnings
import sys
# -- Audio / signal processing -----------------------------------------------
import librosa                            # core audio analysis library (loading, trimming, STFT)
import librosa.display                    # librosa's matplotlib helpers (specshow)           # inline audio player in Jupyter
import soundfile as sf                    # lossless WAV read/write
import noisereduce as nr                  # spectral noise-gate / reduction
from scipy.signal import butter, filtfilt, find_peaks , iirnotch , sosfiltfilt  # bandpass filter design + peak finder

# -- Universal format conversion ---------------------------------------------
# Converting mp3/mp4/m4a/aac/wma/etc. requires an ffmpeg binary somewhere.
# We try, in order:
#   1) imageio-ffmpeg's bundled binary  -> zero setup, just `pip install imageio-ffmpeg`
#   2) a system ffmpeg already on PATH  -> if you installed ffmpeg yourself
# Whichever is found gets wired into pydub automatically.
try:
    from pydub import AudioSegment
    PYDUB_AVAILABLE = True
except ImportError:
    PYDUB_AVAILABLE = False
    print("[warning] pydub not installed - will fall back to librosa/soundfile for conversion.\n"
          "          (pip install pydub for full format support.)")

FFMPEG_EXE = None   # will hold whichever ffmpeg binary we end up using

try:
    import imageio_ffmpeg
    FFMPEG_EXE = imageio_ffmpeg.get_ffmpeg_exe()
    print(f"[ok] Using bundled ffmpeg from imageio-ffmpeg: {FFMPEG_EXE}")
except ImportError:
    system_ffmpeg = shutil.which("ffmpeg")
    if system_ffmpeg:
        FFMPEG_EXE = system_ffmpeg
        print(f"[ok] Using system ffmpeg on PATH: {FFMPEG_EXE}")
    else:
        print(
            "[warning] No ffmpeg found anywhere. You will only be able to load\n"
            "          .wav / .flac / .ogg files without it.\n"
            "          Easiest fix -> pip install imageio-ffmpeg   (no PATH editing needed)\n"
            "          Then restart the kernel and re-run this cell."
        )

# Point pydub at whichever ffmpeg binary we found (imageio-ffmpeg or system).
if PYDUB_AVAILABLE and FFMPEG_EXE:
    AudioSegment.converter = FFMPEG_EXE
    AudioSegment.ffprobe   = FFMPEG_EXE   # ffmpeg binary can also serve as ffprobe for pydub's probing

warnings.filterwarnings('ignore')         # hide librosa's PySoundFile deprecation notices

print("Imports complete.")


# %%
import imageio_ffmpeg
from pydub import AudioSegment

# Force pydub to use the bundled ffmpeg binary
_ffmpeg = imageio_ffmpeg.get_ffmpeg_exe()
AudioSegment.converter = _ffmpeg
AudioSegment.ffprobe   = _ffmpeg

print(f"[ok] ffmpeg wired: {_ffmpeg}")

# %% [markdown]
# ---
# ## ⚙️ USER CONFIGURATION — set your audio path here
# 
# Change `AUDIO_PATH` to point to **any** audio file — `.mp3`, `.mp4`, `.m4a`, `.ogg`, `.flac`,
# `.aac`, `.wma`, `.aiff`, `.wav`, … — the conversion step below handles the rest.
# 

# %%
# -----------------------------------------------------------------------------
#  *  ONLY CHANGE THIS BLOCK  *
# -----------------------------------------------------------------------------

AUDIO_PATH = sys.argv[1] if len(sys.argv) > 1 else None
NOISE_PATH = sys.argv[2] if len(sys.argv) > 2 and sys.argv[2] else None
OUTPUT_DIR = sys.argv[3] if len(sys.argv) > 3 else "output"
FFMPEG_PATH = None
if FFMPEG_PATH is not None:
    if PYDUB_AVAILABLE:
        AudioSegment.converter = FFMPEG_PATH
        AudioSegment.ffprobe   = FFMPEG_PATH
    FFMPEG_EXE = FFMPEG_PATH
    print(f"[ok] Overriding ffmpeg binary -> {FFMPEG_PATH}")

# -- Bandpass filter: keep only frequencies inside this range (Hz) -----------
LOWCUT  = 200     # remove sub-bass rumble / handling noise below this frequency
HIGHCUT = 8000    # remove high-frequency hiss above this frequency

# -- FFT peak detection -------------------------------------------------------
PEAK_MIN_MAG    = 0.0      # minimum magnitude a peak must reach
PEAK_MAX_MAG    = 1.0      # maximum magnitude cap (1.0 = no real peaks discarded)
PEAK_FREQ_LIMIT = 10000    # Hz - don't even look for peaks above this frequency

# -- Frequency-range threshold + harmonic rejection (Section 6) --------------
# FREQ_MIN / FREQ_MAX define the plausible range for a genuine FUNDAMENTAL tone.
# Any detected peak outside this window is dropped immediately.
FREQ_MIN = 200      # Hz - e.g. below the lowest note you expect
FREQ_MAX = 4000    # Hz - e.g. above the highest fundamental you expect
                   #      (raise/lower this to match your instrument / material)

# A peak is rejected as a HARMONIC (overtone) if it sits within HARMONIC_TOLERANCE
# of an integer multiple (2x, 3x, ...) of an already-accepted, lower fundamental peak.
# This is exactly the "piano tone peak was double the other frequency" case.
HARMONIC_TOLERANCE   = 0.03   # 3% relative tolerance when matching n x f_fundamental
MAX_HARMONIC_ORDER   = 5      # check up to the 5th harmonic (2x, 3x, 4x, 5x)

# -- Nyquist condition + anti-aliasing (Section 3.1) --------------------------
TARGET_SR          = None   # None = keep native sample rate. Set e.g. 16000 to downsample
                            # (the anti-aliasing filter is ALWAYS applied before downsampling)
AA_CUTOFF_FRACTION = 0.9    # AA low-pass cutoff = this fraction x (new) Nyquist frequency
AA_ORDER           = 8      # anti-aliasing filter order (higher = steeper roll-off)
NYQUIST_MARGIN     = 0.95   # every frequency setting must stay below this fraction x Nyquist

# -- Harmonic removal + fundamental-only filter (Sections 6.1 / 6.2) ---------
FUNDAMENTAL_HZ      = None  # None = auto-detect f0 from the spectrum, or force one, e.g. 261.6
FUNDAMENTAL_MIN_REL = 0.05  # auto-detect: a LOWER peak can be the true fundamental if it is
                            # at least this fraction of the strongest peak (overtone > fundamental case)
NOTCH_Q             = 30    # harmonic notch sharpness: notch width = harmonic_freq / Q
FUND_BAND_REL       = 0.05  # keep f0 x (1 +/- 5%); everything else is removed
FUND_FILTER_ORDER   = 4     # order of the final fundamental-only band-pass

# -- Output paths --------------------------------------------------------------
CONVERTED_WAV_PATH = os.path.join(OUTPUT_DIR, "converted.wav")
SAVE_WAV_PATH = os.path.join(OUTPUT_DIR, "processed_audio.wav")
FUNDAMENTAL_WAV_PATH = os.path.join(OUTPUT_DIR, "processed_audio_fundamental_only.wav") # fundamental-only audio

# -----------------------------------------------------------------------------
os.makedirs(OUTPUT_DIR, exist_ok=True)
#------------------------------------------------------------------------------
def save_current_figure(filename):
    path = os.path.join(OUTPUT_DIR, filename)
    plt.savefig(path, dpi=150, bbox_inches="tight")
    print(f"[ok] Plot saved -> {path}")
#------------------------------------------------------------------------------
if AUDIO_PATH is None:
    raise ValueError("No audio file was provided.")

if not os.path.isfile(AUDIO_PATH):
    raise FileNotFoundError(f"Audio file not found: {AUDIO_PATH}")

print(f"[ok] Audio file found: {os.path.basename(AUDIO_PATH)}")

if NOISE_PATH is not None:
    assert os.path.exists(NOISE_PATH), f"Noise file not found: {NOISE_PATH}"
    print(f"[ok] Noise file found: {os.path.basename(NOISE_PATH)}")
else:
    print("[info] NOISE_PATH = None - will use auto noise estimation in the noise-reduction step.")

# %% [markdown]
# # Explanation of Parameters

# %%
# Dependencies are installed by setup.sh / setup.bat into the project virtual environment (.venv).
# Nothing to install here.

#---------------------------------------------------------------------
# %% [markdown]
# ## 2 — Universal Format → WAV Conversion

# %%
def convert_to_wav(input_path, output_path):
    """
    Convert an audio file into a WAV file using the bundled ffmpeg executable.
    The rest of the original signal-processing pipeline remains unchanged.
    """
    import subprocess

    ext = os.path.splitext(input_path)[1].lower()

    # Create the output directory if necessary.
    os.makedirs(os.path.dirname(os.path.abspath(output_path)), exist_ok=True)

    # Already WAV: copy without re-encoding.
    if ext == ".wav":
        if os.path.abspath(input_path) != os.path.abspath(output_path):
            shutil.copy2(input_path, output_path)

        print(f"[info] Input is already WAV: {output_path}")
        return output_path

    # Prefer calling ffmpeg directly. This avoids pydub's ffmpeg discovery.
    ffmpeg_exe = FFMPEG_EXE

    if not ffmpeg_exe:
        try:
            import imageio_ffmpeg
            ffmpeg_exe = imageio_ffmpeg.get_ffmpeg_exe()
        except ImportError:
            ffmpeg_exe = shutil.which("ffmpeg")

    if ffmpeg_exe and os.path.isfile(ffmpeg_exe):
        command = [
            ffmpeg_exe,
            "-hide_banner",
            "-loglevel", "error",
            "-y",
            "-i", input_path,
            "-vn",
            "-ac", "1",
            "-ar", "44100",
            "-c:a", "pcm_s16le",
            output_path,
        ]

        try:
            result = subprocess.run(
                command,
                capture_output=True,
                text=True,
                check=False,
                timeout=120,
            )

            if (
                result.returncode == 0
                and os.path.isfile(output_path)
                and os.path.getsize(output_path) > 44
            ):
                print(
                    f"[ok] Converted {ext} -> WAV using ffmpeg: "
                    f"{output_path}"
                )
                return output_path

            error_message = result.stderr.strip() or (
                f"ffmpeg exited with code {result.returncode}"
            )

            raise RuntimeError(error_message)

        except Exception as exc:
            # Do not silently hide why conversion failed.
            raise RuntimeError(
                f"ffmpeg failed to convert '{input_path}' to WAV.\n"
                f"ffmpeg executable: {ffmpeg_exe}\n"
                f"Details: {exc}"
            ) from exc

    # Fallback for formats that librosa can read without ffmpeg.
    try:
        y_tmp, sr_tmp = librosa.load(
            input_path,
            sr=None,
            mono=True,
        )

        sf.write(output_path, y_tmp, sr_tmp)

        print(
            f"[ok] Converted {ext} -> WAV using librosa/soundfile: "
            f"{output_path}"
        )
        return output_path

    except Exception as exc:
        raise RuntimeError(
            f"Could not convert '{input_path}' to WAV.\n"
            f"File extension: {ext}\n"
            f"FFMPEG_EXE: {ffmpeg_exe!r}\n"
            f"Details: {exc}"
        ) from exc


WAV_PATH = convert_to_wav(AUDIO_PATH, CONVERTED_WAV_PATH)

#-------------------------------------------------------------------------

# %% [markdown]
# ---
# ## 3 — Loading the Audio

# %%
# Load the (now guaranteed-WAV) file into a 1-D NumPy array `y` (mono, float32 in [-1, 1]).
# sr=None means: use the file's native sample rate - do NOT resample, which keeps
# high-frequency harmonics intact.
y, sr = librosa.load(WAV_PATH, sr=None)

filename = os.path.basename(AUDIO_PATH)   # human-readable name for plot titles

print(f"File        : {filename}")
print(f"Sample rate : {sr} Hz")
print(f"Duration    : {len(y)/sr:.3f} s")
print(f"Samples     : {y.shape[0]}")

# Play the raw audio inline (only works inside Jupyter / JupyterLab)



# %% [markdown]
# ---
# ## 3.1 — Nyquist Condition 
# 
# **Nyquist condition:** a sampled signal can only represent frequencies **below `sr / 2`**.
# Every frequency setting (`HIGHCUT`, `FREQ_MAX`, `PEAK_FREQ_LIMIT`, harmonic checks) is
# validated against it and clamped to `NYQUIST_MARGIN × Nyquist` if it is too high.
# 

# %%
# ---------------------------------------------------------------------------
# Keep the original sampling rate
# No downsampling and no anti-aliasing filter
# ---------------------------------------------------------------------------

print(f"[info] Keeping original sampling rate: {sr} Hz")
print("[info] No downsampling or anti-aliasing filtering applied.")


# ---------------------------------------------------------------------------
# Nyquist condition
# ---------------------------------------------------------------------------

NYQUIST = sr / 2
NYQ_LIMIT = NYQUIST_MARGIN * NYQUIST

print(
    f"\nSample rate {sr} Hz -> "
    f"Nyquist frequency = {NYQUIST:.0f} Hz "
    f"(usable limit {NYQ_LIMIT:.0f} Hz)"
)


def enforce_nyquist(name, value):
    """Clamp a frequency setting so it respects the usable Nyquist limit."""
    if value > NYQ_LIMIT:
        print(
            f"  [nyquist] {name} = {value:.0f} Hz is above the limit "
            f"-> clamped to {NYQ_LIMIT:.0f} Hz"
        )
        return NYQ_LIMIT

    print(f"  [ok] {name} = {value:.0f} Hz (< {NYQ_LIMIT:.0f} Hz)")
    return value


# Check frequency settings
LOWCUT = enforce_nyquist("LOWCUT", LOWCUT)
HIGHCUT = enforce_nyquist("HIGHCUT", HIGHCUT)

FREQ_MIN = enforce_nyquist("FREQ_MIN", FREQ_MIN)
FREQ_MAX = enforce_nyquist("FREQ_MAX", FREQ_MAX)

PEAK_FREQ_LIMIT = enforce_nyquist(
    "PEAK_FREQ_LIMIT", PEAK_FREQ_LIMIT
)


# Validate frequency ranges
assert 0 < LOWCUT < HIGHCUT, (
    f"LOWCUT ({LOWCUT}) must be > 0 and < HIGHCUT ({HIGHCUT})."
)

assert 0 <= FREQ_MIN < FREQ_MAX, (
    f"FREQ_MIN ({FREQ_MIN}) must be >= 0 and < FREQ_MAX ({FREQ_MAX})."
)

assert 0 < PEAK_FREQ_LIMIT < NYQUIST, (
    f"PEAK_FREQ_LIMIT ({PEAK_FREQ_LIMIT}) must be > 0 and < Nyquist ({NYQUIST})."
)

# %% [markdown]
# ---
# ## 4 — Pre-Processing Pipeline
# 
# Each step is applied in order; the result of one step feeds the next.
# Only the **important filters** are kept: a bandpass filter (removes rumble & hiss)
# and spectral noise reduction (removes broadband background noise). Everything else
# here is basic housekeeping (offset removal, trimming, normalization).

# %% [markdown]
# ### 4.1 — Raw Signal Visualization

# %%
# Build a time axis: evenly spaced from 0 seconds to the total duration.
time = np.linspace(0, len(y) / sr, num=len(y))

plt.figure(figsize=(12, 4))
plt.plot(time, y, lw=1, color='#CF0F47')
plt.title(f'Raw Audio Signal: {filename}', fontsize=14)
plt.xlabel('Time (seconds)', fontsize=12)
plt.ylabel('Amplitude', fontsize=12)
plt.grid(True, linestyle='--', alpha=0.5)
plt.tight_layout()
save_current_figure("raw_signal.png")
plt.close()


# %% [markdown]
# ### 4.2 — DC Offset Removal

# %%
# DC offset = a non-zero mean value in the signal caused by microphone/ADC bias.
# If not removed, it shows up as a spike at 0 Hz in the FFT and skews every
# magnitude value. Subtracting the mean re-centres the waveform on 0.
y = y - np.mean(y)

print(f"DC offset removed. New mean: {np.mean(y):.2e}  (should be ~0)")


# %% [markdown]
# ### 4.3 — Trim Leading / Trailing Silence

# %%
# librosa.effects.trim removes silent regions from both ends of the recording.
# top_db=20 means: anything quieter than (peak - 20 dB) counts as silence.
y_trimmed, trim_index = librosa.effects.trim(y, top_db=20)

time_trimmed = np.linspace(0, len(y_trimmed) / sr, num=len(y_trimmed))

plt.figure(figsize=(12, 4))
plt.plot(time_trimmed, y_trimmed, lw=1, color='#A5158C')
plt.title(
    f'Trimmed Audio Waveform (Duration: {len(y)/sr:.2f}s -> {len(y_trimmed)/sr:.2f}s)',
    fontsize=14
)
plt.xlabel('Time (seconds)', fontsize=12)
plt.ylabel('Amplitude', fontsize=12)
plt.grid(True, linestyle='--', alpha=0.5)
plt.tight_layout()
save_current_figure("trimmed_signal.png")
plt.close()




# %% [markdown]
# ### 4.4 — Bandpass Filter *(important filter #1)*
# 
# Keeps only frequencies between `LOWCUT` and `HIGHCUT` Hz. A 4th-order Butterworth
# IIR filter is applied with `filtfilt` (zero-phase — it doesn't shift the signal in
# time, which preserves the exact onset shape).

# %%
def bandpass_filter(signal, sr, lowcut, highcut, order=4):
    """
    Apply a zero-phase Butterworth bandpass filter.

    Parameters
    ----------
    signal  : 1-D NumPy array - input audio samples
    sr      : int   - sample rate in Hz
    lowcut  : float - lower cutoff frequency (Hz)
    highcut : float - upper cutoff frequency (Hz)
    order   : int   - filter order (higher = sharper roll-off)

    Returns
    -------
    1-D NumPy array - filtered signal, same length as input
    """
    nyq = 0.5 * sr
    # Nyquist condition: cutoffs must satisfy 0 < lowcut < highcut < sr/2
    if highcut >= nyq:
        print(f"[nyquist] highcut {highcut:.0f} Hz >= Nyquist {nyq:.0f} Hz -> clamped to {0.99*nyq:.0f} Hz")
        highcut = 0.99 * nyq
    if not (0 < lowcut < highcut):
        raise ValueError(f"Need 0 < lowcut < highcut, got lowcut={lowcut}, highcut={highcut}")
    low  = lowcut  / nyq
    high = highcut / nyq
    b, a = butter(order, [low, high], btype='band')
    return filtfilt(b, a, signal)

y_filtered = bandpass_filter(y_trimmed, sr, lowcut=LOWCUT, highcut=HIGHCUT)

time_filtered = np.linspace(0, len(y_filtered) / sr, num=len(y_filtered))

plt.figure(figsize=(12, 4))
plt.plot(time_filtered, y_filtered, lw=1, color='#0B6E4F')
plt.title(f'Band-Pass Filtered Waveform ({LOWCUT}-{HIGHCUT} Hz)', fontsize=14)
plt.xlabel('Time (seconds)', fontsize=12)
plt.ylabel('Amplitude', fontsize=12)
plt.grid(True, linestyle='--', alpha=0.5)
plt.tight_layout()
save_current_figure("bandpass_signal.png")
plt.close()


# %% [markdown]
# ### 4.5 — Spectral Noise Reduction *(important filter #2)*
# 
# `noisereduce` estimates a noise floor (from `NOISE_PATH` if given, otherwise from
# the quietest parts of the signal itself) and attenuates any frequency bin that
# falls below it — removing broadband background noise while preserving the loud
# tap/tone and its harmonics.

# %%
if NOISE_PATH is not None:
    # Load and prep the noise clip through the same DC-offset + bandpass steps
    # as the main signal, so the noise profile lives in the same frequency space.
    y_noise_raw, sr_noise = librosa.load(NOISE_PATH, sr=sr)
    y_noise_raw = y_noise_raw - np.mean(y_noise_raw)
    y_noise_profile = bandpass_filter(y_noise_raw, sr_noise, lowcut=LOWCUT, highcut=HIGHCUT)

    reduced_noise = nr.reduce_noise(y=y_filtered, sr=sr, y_noise=y_noise_profile)
    print("[ok] Noise reduction done (using explicit noise file).")
else:
    reduced_noise = nr.reduce_noise(y=y_filtered, sr=sr)
    print("[ok] Noise reduction done (auto noise estimation).")


# %% [markdown]
# ### 4.6 — Internal Silence Removal

# %%
# librosa.effects.split finds all segments louder than (peak - top_db dB).
# We keep both the segment boundaries (tap_segments) and the concatenated audio.
intervals        = librosa.effects.split(reduced_noise, top_db=20)
tap_segments     = [(start, end) for start, end in intervals]
non_silent_audio = np.concatenate([reduced_noise[start:end] for start, end in intervals])

print(f"[ok] Silence removal done. Kept {len(non_silent_audio)/sr:.2f}s from {len(tap_segments)} segment(s).")


# %% [markdown]
# ### 4.7 — Peak Normalization

# %%
# Scale the signal so the loudest sample reaches exactly +/-1.0.
peak = np.max(np.abs(non_silent_audio))
if peak != 0:
    normalized_audio = non_silent_audio / peak
else:
    normalized_audio = non_silent_audio   # leave as-is if audio is completely silent

print(f"[ok] Normalisation done. Peak amplitude: {np.max(np.abs(normalized_audio)):.4f} (should be 1.0)")


# %% [markdown]
# ---
# ## 5 — Spectral Analysis & Visualization

# %% [markdown]
# ### 5.1 — STFT Spectrogram (time × frequency energy map)

# %%
# Short-Time Fourier Transform: slides a short window along the signal and
# computes the FFT at each position, showing how frequencies evolve over time.
S    = librosa.stft(normalized_audio)
S_db = librosa.amplitude_to_db(np.abs(S), ref=np.max)

fig, ax = plt.subplots(figsize=(14, 6))
img = librosa.display.specshow(S_db, sr=sr, x_axis='time', y_axis='log', ax=ax, cmap='magma')
ax.set_title('Log-Frequency Spectrogram (STFT)', fontsize=15)
ax.set_xlabel('Time (s)', fontsize=12)
ax.set_ylabel('Frequency (Hz)', fontsize=12)
cbar = fig.colorbar(img, ax=ax, format='%+0.1f dB')
cbar.set_label('Intensity (dB)', fontsize=11)
ax.grid(alpha=0.2, linestyle='--')
plt.tight_layout()
save_current_figure("stft_spectrogram.png")
plt.close()


# %% [markdown]
# ### 5.2 — Magnitude vs. Time **and** Magnitude vs. Angular Frequency
# 
# Two panels on the final, cleaned signal:
# - **Top:** amplitude (magnitude) of the waveform over **time**.
# - **Bottom:** normalized magnitude of the spectrum over **angular frequency**
#   ω = 2πf (rad/s), instead of the usual Hz axis.

# %%
N_final = len(normalized_audio)
t_final = np.arange(N_final) / sr

# Windowed FFT to reduce spectral leakage before looking at the spectrum shape.
window            = np.hanning(N_final)
windowed_signal   = normalized_audio * window
fft_result_final  = np.fft.rfft(windowed_signal)
magnitude_final   = np.abs(fft_result_final)
magnitude_final   = magnitude_final / np.max(magnitude_final)          # normalize to [0, 1]
frequencies_final = np.fft.rfftfreq(N_final, d=1/sr)
angular_frequency = 2 * np.pi * frequencies_final                      # omega = 2*pi*f (rad/s)

fig, axes = plt.subplots(2, 1, figsize=(12, 8))

# -- Panel 1: Magnitude vs. Time ---------------------------------------------
axes[0].plot(t_final, normalized_audio, lw=0.8, color='#CF0F47')
axes[0].set_title(f'Magnitude vs. Time (Processed Signal): {filename}', fontsize=13)
axes[0].set_xlabel('Time (s)', fontsize=11)
axes[0].set_ylabel('Amplitude', fontsize=11)
axes[0].grid(True, linestyle='--', alpha=0.5)

# -- Panel 2: Magnitude vs. Angular Frequency --------------------------------
axes[1].plot(angular_frequency, magnitude_final, lw=0.8, color='#1f3b73')
axes[1].set_title('Magnitude vs. Angular Frequency (Processed Signal)', fontsize=13)
axes[1].set_xlabel('Angular Frequency omega (rad/s)', fontsize=11)
axes[1].set_ylabel('Normalized Magnitude', fontsize=11)
axes[1].set_xlim(0, 2 * np.pi * PEAK_FREQ_LIMIT)
axes[1].grid(True, linestyle='--', alpha=0.5)

plt.tight_layout()
save_current_figure("magnitude_analysis.png")
plt.close()


# %% [markdown]
# ### 5.3 — FFT Spectrum (Hz) & Peak Detection

# %%
# Global FFT of the whole cleaned signal - this is what step 6 filters next.
n         = len(normalized_audio)
fft_vals  = np.fft.rfft(normalized_audio)
fft_freqs = np.fft.rfftfreq(n, d=1/sr)
magnitude = np.abs(fft_vals) / n * 2      # undo FFT scaling, recover full-amplitude estimate

plt.figure(figsize=(14, 6))
plt.plot(fft_freqs, magnitude, lw=1, color='#1f3b73')
plt.title('Frequency Spectrum (FFT - Full Signal)', fontsize=15)
plt.xlabel('Frequency (Hz)', fontsize=12)
plt.ylabel('Amplitude', fontsize=12)
plt.xlim(0, PEAK_FREQ_LIMIT)
plt.grid(alpha=0.3, linestyle='--')

min_dist = int(50 / (sr / 2) * len(fft_freqs))
peaks, _ = find_peaks(
    magnitude,
    height=(PEAK_MIN_MAG, PEAK_MAX_MAG),
    distance=min_dist,
    prominence=magnitude.max() * 0.05
)
peaks     = [p for p in peaks if fft_freqs[p] <= PEAK_FREQ_LIMIT]
all_peaks = sorted(peaks, key=lambda p: magnitude[p], reverse=True)

for p in all_peaks:
    freq, mag = fft_freqs[p], magnitude[p]
    plt.plot(freq, mag, 'k.', markersize=8)
    plt.annotate(f'{freq:.0f} Hz', xy=(freq, mag), xytext=(0, 10),
                 textcoords='offset points', ha='center', fontsize=9,
                 bbox=dict(boxstyle='round,pad=0.3', fc='yellow', alpha=0.7))

plt.tight_layout()
save_current_figure("fft_spectrum.png")
plt.close()

print(f"\nTotal peaks detected: {len(all_peaks)}")
print(f"{'Rank':<6} {'Frequency (Hz)':<18} {'Amplitude'}")
print("-" * 38)
for rank, p in enumerate(all_peaks, 1):
    print(f"{rank:<6} {fft_freqs[p]:<18.1f} {magnitude[p]:.5f}")


# %% [markdown]
# ---
# ## 6 — Frequency-Range Threshold + Harmonic Rejection
# 
# This is the check you asked for: on a piano tone, the FFT often shows a strong
# peak at the true fundamental **and another peak at ~2× that frequency** (the
# first harmonic/overtone). We don't want to keep that doubled peak as if it were
# its own independent tone.
# 
# Two filters are applied to the peaks detected above, in order:
# 
# 1. **Range threshold** — any peak outside `[FREQ_MIN, FREQ_MAX]` is dropped immediately.
# 2. **Harmonic threshold** — going from the lowest surviving peak upward, if a peak's
#    frequency lands within `HARMONIC_TOLERANCE` of `n ×` an already-accepted
#    (lower) fundamental peak (for `n = 2 … MAX_HARMONIC_ORDER`), it is treated as an
#    overtone of that fundamental and rejected — **it "passes" the harmonic test, so
#    we don't take it with us.**

# %%
def filter_fundamental_peaks(peak_freqs, peak_mags, freq_min, freq_max,
                              harmonic_tolerance=0.03, max_harmonic_order=5):
    """
    Detect harmonic relationships among ALL in-range peaks. Keep the FUNDAMENTAL
    and its confirmed harmonics (n x multiples). Reject only peaks that aren't
    part of any detected harmonic series (stray/unrelated peaks) or are out of range.

    This generalizes to any tone: nothing is hardcoded to a specific frequency.
    For every pair of peaks (f_low, f_high), if f_high ~= n * f_low
    (n = 2..max_harmonic_order) within tolerance, f_low is the fundamental and
    f_high is one of its harmonics - both are kept. A peak with NO harmonic
    relationship to anything else in range is rejected, since it can't be
    confirmed as part of the tone you're analyzing.

    Parameters
    ----------
    peak_freqs, peak_mags : arrays of the detected peak frequencies/magnitudes
    freq_min, freq_max    : float - valid frequency range (Hz) to search within
    harmonic_tolerance    : float - relative tolerance (e.g. 0.03 = 3%) when
                             checking whether f_high ~= n * f_low
    max_harmonic_order    : int   - highest multiple 'n' to check (2, 3, 4, 5...)

    Returns
    -------
    accepted : list of (freq, mag) - the fundamental + its confirmed harmonics
    rejected : list of (freq, mag, reason) - out-of-range or unrelated stray peaks
    """
    # Step 1 - range threshold ----------------------------------------------------
    candidates, rejected = [], []
    for f, m in zip(peak_freqs, peak_mags):
        if freq_min <= f <= freq_max:
            candidates.append((f, m))
        else:
            rejected.append((f, m, f"outside range [{freq_min}, {freq_max}] Hz"))

    candidates.sort(key=lambda x: x[0])   # ascending: lowest frequency first

    # Step 2 - find harmonic relationships between every pair of candidates.
    # is_a_fundamental[i]  -> True if some HIGHER peak is a multiple of peak i
    # harmonic_of[i]       -> (j, n) if peak i itself is the n-th harmonic of a lower peak j
    is_a_fundamental = [False] * len(candidates)
    harmonic_of      = [None]  * len(candidates)

    for i in range(len(candidates)):
        f_i, _ = candidates[i]
        for j in range(i):                      # only compare against LOWER peaks
            f_j, _ = candidates[j]
            if f_j <= 0:
                continue
            for n in range(2, max_harmonic_order + 1):
                if abs(f_i - n * f_j) / (n * f_j) < harmonic_tolerance:
                    harmonic_of[i] = (j, n)
                    is_a_fundamental[j] = True
                    break
            if harmonic_of[i] is not None:
                break

    # Step 3 - build accepted / rejected lists.
    # A peak is ACCEPTED if it's either a confirmed harmonic OR the fundamental
    # that harmonic came from. Only peaks with NO harmonic relationship at all
    # (not a fundamental, not a harmonic) are rejected as unrelated stray peaks.
    accepted = []
    for i, (f, m) in enumerate(candidates):
        if harmonic_of[i] is not None:
            # Confirmed harmonic of a lower fundamental -> keep it.
            accepted.append((f, m))
        elif is_a_fundamental[i]:
            # This peak IS the fundamental that has harmonic(s) above it -> keep it too.
            accepted.append((f, m))
        else:
            # No harmonic relationship to anything -> unrelated stray peak -> drop it.
            rejected.append((f, m, "standalone peak, not part of any harmonic series - rejected"))

    return accepted, rejected


peak_freqs_arr = np.array([fft_freqs[p] for p in all_peaks])
peak_mags_arr  = np.array([magnitude[p]  for p in all_peaks])

accepted, rejected = filter_fundamental_peaks(
    peak_freqs_arr, peak_mags_arr,
    freq_min=FREQ_MIN, freq_max=FREQ_MAX,
    harmonic_tolerance=HARMONIC_TOLERANCE,
    max_harmonic_order=MAX_HARMONIC_ORDER
)

print(f"Kept {len(accepted)} peak(s):")
for f, m in sorted(accepted, key=lambda x: x[1], reverse=True):
    print(f"    {f:8.1f} Hz   | amplitude {m:.5f}")

print(f"\nRejected {len(rejected)} peak(s):")
for f, m, reason in rejected:
    print(f"    {f:8.1f} Hz   | amplitude {m:.5f}   | reason: {reason}")

# -- Visualize accepted vs rejected peaks on the spectrum --------------------
plt.figure(figsize=(14, 6))
plt.plot(fft_freqs, magnitude, lw=1, color='#1f3b73', alpha=0.8)
plt.axvspan(FREQ_MIN, FREQ_MAX, color='green', alpha=0.07, label=f'Valid range [{FREQ_MIN}, {FREQ_MAX}] Hz')

for f, m in accepted:
    plt.plot(f, m, 'o', color='green', markersize=9, zorder=5)
    plt.annotate(f'{f:.0f} Hz\n(kept)', xy=(f, m), xytext=(0, 12),
                 textcoords='offset points', ha='center', fontsize=9, color='green', fontweight='bold')

for f, m, reason in rejected:
    plt.plot(f, m, 'x', color='red', markersize=9, zorder=5)
    plt.annotate(f'{f:.0f} Hz\n(rejected)', xy=(f, m), xytext=(0, -22),
                 textcoords='offset points', ha='center', fontsize=8, color='red')

plt.title('Fundamental + Harmonics Kept (green) vs. Unrelated Peaks Dropped (red)', fontsize=13)
plt.xlabel('Frequency (Hz)', fontsize=12)
plt.ylabel('Amplitude', fontsize=12)
plt.xlim(0, PEAK_FREQ_LIMIT)
plt.legend()
plt.grid(alpha=0.3, linestyle='--')
plt.tight_layout()
save_current_figure("harmonic_peaks.png")
plt.close()

# %% [markdown]
# ---
# ## 6.1 — Detect the Fundamental & Remove All Its Harmonics
# 
# 1. **Find f0** — strongest peak in `[FREQ_MIN, FREQ_MAX]` (or the weaker lower peak it is a multiple of), or set `FUNDAMENTAL_HZ` manually.
# 2. **Detect harmonics** — peaks at `n × f0`, `n = 2 … MAX_HARMONIC_ORDER`, within `HARMONIC_TOLERANCE`. Orders at/above the Nyquist limit are skipped.
# 3. **Remove them all** — one zero-phase notch filter per detected harmonic.

# %%
def find_fundamental(peak_freqs, peak_mags, freq_min, freq_max,
                     tol=0.03, max_order=5, min_rel_amp=0.05):
    """
    Pick the fundamental frequency f0 from the detected FFT peaks.
      1) start from the strongest peak inside [freq_min, freq_max]
      2) if that peak is actually the n-th harmonic of a weaker, LOWER peak
         (piano-style: the overtone is louder than the fundamental), use the
         lower one instead.  'Weaker' peaks below min_rel_amp x strongest are ignored.
    """
    peak_freqs, peak_mags = np.asarray(peak_freqs), np.asarray(peak_mags)
    mask = (peak_freqs >= freq_min) & (peak_freqs <= freq_max)
    if not mask.any():
        raise ValueError("No peaks inside [FREQ_MIN, FREQ_MAX] - cannot pick a fundamental.")
    f_in, m_in = peak_freqs[mask], peak_mags[mask]

    f0 = f_in[np.argmax(m_in)]
    for f_low, m_low in sorted(zip(f_in, m_in)):                  # lowest first
        if f_low >= f0 or m_low < min_rel_amp * m_in.max():
            continue
        if any(abs(f0 - n * f_low) / (n * f_low) < tol for n in range(2, max_order + 1)):
            f0 = f_low
            break
    return f0


def find_harmonics(f0, peak_freqs, peak_mags, tol, max_order, nyquist_limit):
    """
    Look for peaks sitting at n x f0 (n = 2..max_order).
    Nyquist condition: a harmonic at or above the Nyquist limit cannot be
    represented (it would alias), so those orders are skipped.
    Returns list of (n, detected_freq_Hz, magnitude).
    """
    peak_freqs, peak_mags = np.asarray(peak_freqs), np.asarray(peak_mags)
    found = []
    for n in range(2, max_order + 1):
        fh = n * f0
        if fh >= nyquist_limit:
            print(f"  [nyquist] harmonic {n}x = {fh:.0f} Hz >= {nyquist_limit:.0f} Hz -> not checked")
            continue
        rel_err = np.abs(peak_freqs - fh) / fh
        i = np.argmin(rel_err)
        if rel_err[i] < tol:
            found.append((n, float(peak_freqs[i]), float(peak_mags[i])))
    return found


def remove_harmonics(signal, sr, harmonic_freqs, q=30):
    """Cascade of zero-phase notch filters, one per detected harmonic."""
    out = np.asarray(signal, dtype=float).copy()
    for fh in harmonic_freqs:
        b, a = iirnotch(fh, q, fs=sr)           # notch width = fh / q
        out = filtfilt(b, a, out)
    return out


# -- run ---------------------------------------------------------------------
peak_freqs_all = np.array([fft_freqs[p] for p in all_peaks])
peak_mags_all  = np.array([magnitude[p]  for p in all_peaks])

if FUNDAMENTAL_HZ is not None:
    f0 = float(FUNDAMENTAL_HZ)
    print(f"[info] Using manually-set fundamental: {f0:.1f} Hz")
else:
    f0 = find_fundamental(peak_freqs_all, peak_mags_all, FREQ_MIN, FREQ_MAX,
                          HARMONIC_TOLERANCE, MAX_HARMONIC_ORDER, FUNDAMENTAL_MIN_REL)
    print(f"[ok] Fundamental frequency detected: {f0:.1f} Hz")

harmonics = find_harmonics(f0, peak_freqs_all, peak_mags_all,
                           HARMONIC_TOLERANCE, MAX_HARMONIC_ORDER, NYQ_LIMIT)

print(f"\nDetected {len(harmonics)} harmonic(s) of {f0:.1f} Hz:")
for n, fh, mh in harmonics:
    print(f"    {n}x  ->  {fh:8.1f} Hz   | amplitude {mh:.5f}")

harmonic_freqs = [fh for _, fh, _ in harmonics]
if harmonic_freqs:
    y_no_harm = remove_harmonics(normalized_audio, sr, harmonic_freqs, q=NOTCH_Q)
    print(f"\n[ok] Removed {len(harmonic_freqs)} harmonic(s) with notch filters (Q = {NOTCH_Q}).")
else:
    y_no_harm = normalized_audio.copy()
    print("\n[info] No harmonics detected - signal left unchanged.")

# -- before / after spectrum -------------------------------------------------
def amp_spectrum(x):
    return np.abs(np.fft.rfft(x)) / len(x) * 2

spec_before = amp_spectrum(normalized_audio)
spec_after  = amp_spectrum(y_no_harm)

plt.figure(figsize=(14, 6))
plt.plot(fft_freqs, spec_before, lw=1, color='#1f3b73', alpha=0.6, label='Before harmonic removal')
plt.plot(fft_freqs, spec_after,  lw=1, color='#CF0F47', label='After harmonic removal')
plt.axvline(f0, color='green', ls='-', lw=1.5, label=f'Fundamental {f0:.0f} Hz')
for k, (n, fh, _) in enumerate(harmonics):
    plt.axvline(fh, color='red', ls='--', lw=1, label='Removed harmonics' if k == 0 else None)
plt.title('Step 6.1 - Harmonics of the Fundamental Removed', fontsize=14)
plt.xlabel('Frequency (Hz)', fontsize=12); plt.ylabel('Amplitude', fontsize=12)
plt.xlim(0, PEAK_FREQ_LIMIT); plt.grid(alpha=0.3, linestyle='--'); plt.legend()
plt.tight_layout()
save_current_figure("harmonic_removal.png")
plt.close()


# %% [markdown]
# ---
# ## 6.2 — Filter Out Everything Except the Fundamental
# 
# A narrow zero-phase Butterworth band-pass centred on f0 (`f0 × (1 ± FUND_BAND_REL)`) removes
# any remaining frequency: leftover harmonics, noise and stray peaks.

# %%
def fundamental_only_filter(signal, sr, f0, rel_bandwidth=0.05, order=4, nyquist_limit=None):
    """
    Narrow zero-phase Butterworth band-pass centred on f0:
        passband = f0 x (1 - rel_bandwidth)  ...  f0 x (1 + rel_bandwidth)
    Everything outside that band (leftover harmonics, noise, stray peaks) is rejected.
    """
    low  = f0 * (1 - rel_bandwidth)
    high = f0 * (1 + rel_bandwidth)
    if nyquist_limit is not None:
        high = min(high, nyquist_limit)           # Nyquist condition
    if not (0 < low < high):
        raise ValueError(f"Invalid passband [{low:.1f}, {high:.1f}] Hz for f0 = {f0:.1f} Hz.")
    sos = butter(order, [low, high], btype='band', fs=sr, output='sos')
    return sosfiltfilt(sos, signal), (low, high)

y_fund, (band_lo, band_hi) = fundamental_only_filter(
    y_no_harm, sr, f0, rel_bandwidth=FUND_BAND_REL,
    order=FUND_FILTER_ORDER, nyquist_limit=NYQ_LIMIT
)

# Re-normalise so the result is comparable / playable
pk = np.max(np.abs(y_fund))
y_fund_norm = y_fund / pk if pk > 0 else y_fund
print(f"[ok] Fundamental-only band-pass applied: {band_lo:.1f} - {band_hi:.1f} Hz (order {FUND_FILTER_ORDER})")

# -- how much energy is left OUTSIDE the fundamental band? ------------------
def out_of_band_ratio(x):
    X = np.abs(np.fft.rfft(x)) ** 2
    inside = (fft_freqs >= band_lo) & (fft_freqs <= band_hi)
    return 10 * np.log10(X[~inside].sum() / X[inside].sum() + 1e-20)

print(f"Out-of-band / in-band energy :  before = {out_of_band_ratio(normalized_audio):6.1f} dB"
      f"   ->   after = {out_of_band_ratio(y_fund_norm):6.1f} dB")

# -- three-panel comparison -------------------------------------------------
fig, ax = plt.subplots(3, 1, figsize=(14, 10), sharex=True)
for a, x, title, col in zip(
        ax,
        [normalized_audio, y_no_harm, y_fund_norm],
        ['Original (after pre-processing)', 'After harmonic removal', 'After fundamental-only filter'],
        ['#1f3b73', '#CF0F47', '#0B6E4F']):
    a.plot(fft_freqs, amp_spectrum(x), lw=1, color=col)
    a.axvspan(band_lo, band_hi, color='green', alpha=0.1)
    a.set_title(title, fontsize=12); a.set_ylabel('Amplitude'); a.grid(alpha=0.3, linestyle='--')
ax[-1].set_xlabel('Frequency (Hz)'); ax[-1].set_xlim(0, PEAK_FREQ_LIMIT)
plt.tight_layout()
save_current_figure("fundamental_comparison.png")
plt.close()

# -- time-domain comparison -------------------------------------------------
t_f = np.arange(len(y_fund_norm)) / sr
plt.figure(figsize=(12, 4))
plt.plot(t_f, normalized_audio, lw=0.8, color='#1f3b73', alpha=0.5, label='Before')
plt.plot(t_f, y_fund_norm,      lw=1.0, color='#0B6E4F',           label=f'Fundamental only ({f0:.0f} Hz)')
plt.title('Waveform: Original vs. Fundamental-Only', fontsize=13)
plt.xlabel('Time (s)'); plt.ylabel('Amplitude'); plt.grid(alpha=0.5, linestyle='--'); plt.legend()
plt.tight_layout()
save_current_figure("waveform_comparison.png")
plt.close()

sf.write(FUNDAMENTAL_WAV_PATH, y_fund_norm, sr)
print(f"[ok] Fundamental-only audio saved -> {FUNDAMENTAL_WAV_PATH}")


# %% [markdown]
# ---
# ## 7 — Export the Processed Audio

# %%
# soundfile writes a lossless WAV file - the cleaned, filtered, normalised signal.
sf.write(SAVE_WAV_PATH, normalized_audio, sr)
print(f"[ok] Processed audio saved -> {SAVE_WAV_PATH}")




# %% [markdown]
# ---
# ## ✅ Pipeline Complete
# 
# | Output | Description |
# |---|---|
# | `*_converted.wav` | Raw input, losslessly converted from any source format |
# | `processed_audio_*.wav` | Filtered, denoised, silence-trimmed, normalised audio |
# | `processed_audio_fundamental_only.wav` | Harmonics notched out, then band-passed to the fundamental only |
# 
# **To process another recording:** change `AUDIO_PATH` in the config cell (any
# format) and re-run all cells. Tune `FREQ_MIN` / `FREQ_MAX` / `HARMONIC_TOLERANCE`
# in the config cell to match the instrument or material you're analyzing.
# 
# **Format conversion setup:** if you saw a warning about ffmpeg in Section 1,
# run `pip install imageio-ffmpeg` once, restart the kernel, and re-run all cells —
# no PATH changes needed.


