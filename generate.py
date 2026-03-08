import os
import sys
import requests
import json
import traceback
import time
from datetime import datetime

OLLAMA_ENDPOINT = os.getenv("OLLAMA_ENDPOINT", "http://localhost:11434/api/generate")
MODEL_NAME = os.getenv("MODEL_NAME", "tinyllama")
MAX_RETRIES = int(os.getenv("MAX_RETRIES", "3"))
RETRY_DELAY = int(os.getenv("RETRY_DELAY", "5"))
TIMEOUT = int(os.getenv("TIMEOUT", "120"))

PROMPT_TEMPLATE = """Write a creative and engaging short story with the following requirements:
- Title: Create an interesting title
- Characters: Develop 2-3 main characters with distinct personalities
- Setting: Describe the time and place where the story occurs
- Plot: Include a clear beginning, middle with conflict, and satisfying resolution
- Theme: Convey a meaningful message or lesson
- Style: Use vivid descriptions and natural dialogue

Story topic: {user_input}
"""


def build_prompt(prompt: str, word_count: int = 100, genre: str | None = None) -> str:
    full_prompt = PROMPT_TEMPLATE.format(user_input=prompt)
    if genre:
        full_prompt += f"\nGenre: {genre.capitalize()} (follow genre conventions)"

    full_prompt += (
        f"\nWord count: Approximately {word_count} words\n"
        "\nAdditional instructions:\n"
        "- Avoid cliches and overused tropes\n"
        "- Show character emotions through actions and dialogue\n"
        "- Maintain consistent pacing throughout the story\n"
        "- End with a satisfying conclusion that ties up main plot points"
    )
    return full_prompt


def generate_story(prompt, word_count=100, genre=None, retries=MAX_RETRIES):
    attempt = 0
    last_error = None
    full_prompt = build_prompt(prompt, word_count, genre)

    while attempt < retries:
        attempt += 1
        try:
            print(f"Attempt {attempt}/{retries}: Generating story...", file=sys.stderr)
            response = requests.post(
                OLLAMA_ENDPOINT,
                json={
                    "model": MODEL_NAME,
                    "prompt": full_prompt,
                    "stream": False,
                    "options": {
                        "temperature": 0.7,
                        "num_ctx": 2048
                    }
                },
                timeout=TIMEOUT,
            )
            response.raise_for_status()
            result = response.json()

            text = result.get("response", "").strip()
            if not text:
                raise ValueError("No response from AI model")
            return text

        except (requests.exceptions.RequestException, json.JSONDecodeError, ValueError) as e:
            last_error = e
            print(f"Attempt {attempt} failed: {str(e)}", file=sys.stderr)
            if attempt < retries:
                print(f"Retrying in {RETRY_DELAY} seconds...", file=sys.stderr)
                time.sleep(RETRY_DELAY)
        except Exception as e:
            last_error = e
            print(f"Unexpected error: {str(e)}", file=sys.stderr)
            break

    return (
        "Could not generate story due to technical difficulties. "
        f"Original prompt: '{prompt}'. Error: {str(last_error)}"
    )


def main():
    try:
        prompt = sys.argv[1] if len(sys.argv) > 1 else "random story"
        word_count = int(sys.argv[2]) if len(sys.argv) > 2 else 100
        genre = sys.argv[3] if len(sys.argv) > 3 else None

        print(f"Generating story for prompt: '{prompt}' at {datetime.now()}", file=sys.stderr)
        print(f"Word count: {word_count}, Genre: {genre or 'None'}", file=sys.stderr)

        story = generate_story(prompt, word_count, genre)
        print(story)

    except Exception as e:
        print(f"Error: {str(e)}\n{traceback.format_exc()}", file=sys.stderr)
        print("Failed to generate story. Please try again later.")
        sys.exit(1)


if __name__ == "__main__":
    main()
