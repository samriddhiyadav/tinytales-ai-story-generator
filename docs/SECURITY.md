# Security Checklist

## Key Risks

- DB credentials in config if env is not used correctly.
- User-uploaded files can become abuse vectors.
- Story generation endpoint can be spammed without throttling.

## Required Hardening

1. Store secrets only in environment variables.
2. Enforce strict upload validation (type + size).
3. Add rate limiting for generation requests.
4. Use generic user-facing errors and private server logs.
5. Apply CSRF protection for state-changing forms.

## Production Notes

- HTTPS only
- least-privilege DB user
- periodic dependency and secret scans
