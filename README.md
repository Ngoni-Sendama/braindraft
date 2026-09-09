# BrainDraft

BrainDraft is a Laravel study assistant that generates written exams, marks answers, summarizes notes, creates Mermaid diagrams, and provides an embeddable AI support widget.

## Setup

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

Configure `.env`:

```env
GROQ_API_KEY=your-groq-key
GROQ_MODEL=openai/gpt-oss-120b
OPENAI_API_KEY=your-openai-key
OPENAI_MODEL=gpt-4.1-mini
```

Groq powers exams, marking, summaries, and widget replies. OpenAI powers study diagrams. Keep keys out of source control and avoid duplicate environment variables because the last value wins.

## Running

```bash
composer dev
```

Alternatively run `php artisan serve` and `npm run dev` separately.

## AI Testing

```bash
php artisan groq:test
php artisan openai:test
php artisan groq:test "Explain cloud computing in one sentence."
php artisan openai:test "Return JSON with one key named answer."
```

## Features

- Generate written-answer exams from `.txt`, `.pptx`, and `.pdf` notes.
- Mark student answers with scores, feedback, missing points, and model answers.
- Generate structured topic summaries and sample exam answers.
- Generate Mermaid study diagrams using OpenAI.
- Embed branded AI support bots on external websites.

## Routes

| Method | Path | Purpose |
|---|---|---|
| GET | `/` | Select subject and notes |
| POST | `/cloud-exam/generate` | Generate an exam |
| GET | `/cloud-exam/attempt` | Attempt an exam |
| POST | `/cloud-exam/mark` | Mark answers |
| POST | `/cloud-exam/summarize` | Generate a summary |
| POST | `/cloud-exam/diagrams` | Generate diagrams |
| GET | `/api/widget/bootstrap` | Load bot branding |
| POST | `/api/widget/message` | Send a widget message |

## Embeddable Widget

```html
<script src="https://your-domain.com/widget.js"
        data-bot-id="1001"
        data-api-base="https://your-domain.com/api/widget"></script>
```

Bots may restrict usage with `allowed_domains`, stored as a JSON array such as `["example.com","www.example.com"]`. Widget requests are rate-limited to 60 bootstrap requests and 20 messages per minute.

## Verification

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

## License

This project uses the MIT license.
