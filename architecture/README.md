# Architecture Notes

## Components

- `index.php`, `public/*`: presentation layer and user flows
- `includes/config.php`: DB/session/app configuration
- `generate.py`: AI generation worker script
- `docs/schema.sql`: relational model

## Data Flow

1. User submits generation request.
2. PHP validates request and invokes generator.
3. Python script calls Ollama API.
4. Generated story is returned and persisted.
5. Story is shown on dashboard/history and can be exported.
