# Deployment Guide (TinyTales)

TinyTales requires PHP runtime, MySQL, and AI inference backend.

## Fast Path (Railway + External AI Host)

1. Push repository to GitHub.
2. Create Railway app for PHP project.
3. Add managed MySQL service.
4. Configure env vars from `.env.example`.
5. Import `docs/schema.sql` into MySQL.
6. Deploy AI inference separately (Ollama on VPS/container).
7. Point app AI endpoint/envs to deployed inference service.

## Alternative

- Render (PHP app) + external MySQL + dedicated AI service host.

## Post-Deploy Checklist

- auth/login flow works
- story generation returns valid text
- dashboard/history shows saved stories
- export output generated correctly
- error logs are private and sanitized
