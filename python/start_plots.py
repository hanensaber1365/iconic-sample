# Start the plot-only run of process_audio.py as a fully detached background
# process and return at once. Used by process_audio.py itself (deferred mode)
# and by Laravel to restart plots whose background run died.
#
# Usage: python start_plots.py <audio_path> <noise_path or ""> <output_dir>
# Output of the background run is appended to the file in AUDIO_PLOTS_LOG.

import os
import subprocess
import sys

SCRIPT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "process_audio.py")


def start_background_plots(audio_path, noise_path, output_dir):
    # Unbuffered, so the log shows how far a run got even if it is killed.
    env = dict(os.environ, AUDIO_PLOTS="only", PYTHONUNBUFFERED="1")
    log_path = os.environ.get("AUDIO_PLOTS_LOG") or os.devnull

    options = {}
    if os.name == "nt":
        options["creationflags"] = subprocess.DETACHED_PROCESS | subprocess.CREATE_NEW_PROCESS_GROUP
    else:
        options["start_new_session"] = True

    try:
        log = open(log_path, "a", encoding="utf-8")
    except OSError:
        log = open(os.devnull, "w")

    with log:
        log.write(f"\n=== plot run started for {output_dir} ===\n")
        log.flush()
        subprocess.Popen(
            [sys.executable, SCRIPT, audio_path, noise_path or "", output_dir],
            env=env,
            stdin=subprocess.DEVNULL,
            stdout=log,
            stderr=subprocess.STDOUT,
            close_fds=True,
            **options,
        )


if __name__ == "__main__":
    if len(sys.argv) != 4:
        sys.exit("Usage: python start_plots.py <audio_path> <noise_path or \"\"> <output_dir>")
    start_background_plots(*sys.argv[1:4])
