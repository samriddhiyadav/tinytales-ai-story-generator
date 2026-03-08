# API / Generation Contract

TinyTales primarily uses internal script invocation for generation.

## Script

`generate.py`

### Arguments
1. `prompt` (string)
2. `word_count` (int)
3. `genre` (optional string)

### Output
- stdout: generated story text
- stderr: retry/error diagnostics

### Failure Behavior
- retries on transient request issues
- returns fallback error narrative if generation fails
