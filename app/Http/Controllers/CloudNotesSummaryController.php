<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use ZipArchive;

class CloudNotesSummaryController extends Controller
{
    private array $subjects = [
        'CHE 110' => 'CHE 110',
        'CAP 404' => 'CAP 404',
        'CAP 213' => 'CAP 213',
    ];

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function subjectBasePath(string $subject): string
    {
        return 'subjects/'.$subject;
    }

    private function collectNotes(string $subject, array $sources): string
    {
        $text = '';
        $allowedBasePath = $this->subjectBasePath($subject).'/';

        foreach ($sources as $source) {
            if (! str_starts_with($source, $allowedBasePath) || str_contains($source, '..')) {
                continue;
            }

            $path = public_path($source);

            if (! File::exists($path)) {
                continue;
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            $text .= "\n\nSOURCE: {$source}\n\n";

            match ($ext) {
                'pptx' => $text .= $this->extractPptxText($path),
                'txt' => $text .= File::get($path),
                'pdf' => $text .= rescue(fn () => (new Parser)->parseFile($path)->getText(), ''),
                default => $text .= "Unsupported format.\n",
            };
        }

        return Str::limit($text, 45000, '');
    }

    /*
    |--------------------------------------------------------------------------
    | AI
    |--------------------------------------------------------------------------
    */

    private function askGroqJson(string $prompt): mixed
    {
        $response = Http::timeout(90)
            ->withToken(config('services.groq.key'))
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => config('services.groq.model'),
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Return valid JSON only.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        return json_decode($response->json('choices.0.message.content'), true);
    }

    private function askOpenAiJson(string $prompt): mixed
    {
        $response = Http::timeout(90)
            ->withToken(config('services.openai.key'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.model'),
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Return valid JSON only.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        return json_decode($response->json('choices.0.message.content'), true);
    }

    /*
    |--------------------------------------------------------------------------
    | File Extraction
    |--------------------------------------------------------------------------
    */

    private function extractPptxText(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return '';
        }

        $text = '';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (str_starts_with($name, 'ppt/slides/') && str_ends_with($name, '.xml')) {
                $xml = $zip->getFromIndex($i);
                $xml = strip_tags($xml);
                $xml = html_entity_decode($xml);
                $text .= "\n".preg_replace('/\s+/', ' ', $xml);
            }
        }

        $zip->close();

        return $text;
    }

    public function summarize(Request $request)
    {
        $request->validate([
            'subject' => ['required', 'string', 'in:'.implode(',', array_keys($this->subjects))],
            'sources' => ['required', 'array', 'min:1'],
        ]);

        $notes = $this->collectNotes($request->subject, $request->sources);

        $prompt = $this->summaryPrompt($request->subject, $notes);

        $result = $this->askGroqJson($prompt);

        if (! is_array($result)) {
            return back()->withErrors(['ai' => 'Failed to generate summary.']);
        }

        return view('cloud-exam.summary', compact('result'));
    }

    public function diagrams(Request $request)
    {
        $request->validate([
            'subject' => ['required', 'string', 'in:'.implode(',', array_keys($this->subjects))],
            'sources' => ['required', 'array', 'min:1'],
        ]);

        $notes = $this->collectNotes($request->subject, $request->sources);

        $result = $this->askOpenAiJson($this->diagramPrompt($request->subject, $notes));

        if (! is_array($result)) {
            return back()->withErrors(['ai' => 'Failed to generate diagrams. Check OPENAI_API_KEY.']);
        }

        return view('cloud-exam.diagrams', compact('result'));
    }

    private function summaryPrompt(string $subject, string $notes): string
    {
        return <<<PROMPT
You are a {$subject} university tutor.

Use the notes below as the official guide.

VERY IMPORTANT RULES:
- Identify the main topics from the notes.
- Create one section for each major topic.
- Do not invent topics that are unrelated to the selected subject.
- If the notes have limited content for a topic, explain it using the notes and general {$subject} knowledge.
- Make the explanation useful for exam preparation.
- Return JSON only.

FORMAT:
{
  "topics": [
    {
      "title": "Service Providers",
      "summary": "...",
      "key_points": ["...", "..."],
      "how_to_answer": "...",
      "real_world_examples": ["...", "..."],
      "sample_answer": "..."
    }
  ]
}

FOR EACH TOPIC:
1. summary:
- Explain clearly.
- Use beginner-friendly language.
- Add enough detail for understanding.

2. key_points:
- List important exam points.
- These should be points a student must mention for marks.

3. how_to_answer:
- Explain how to structure a 5 to 10 mark answer.
- Use this structure:
  Introduction
  Main explanation
  Example
  Conclusion

4. real_world_examples:
- Give practical examples.
- Use examples like AWS, Azure, Google Cloud, Hadoop, Google Search, Netflix, banking systems, school systems, etc.

5. sample_answer:
- Write a strong exam-style answer.
- The answer should be suitable for 5 to 10 marks.
- Do not make it too short.

NOTES:
{$notes}
PROMPT;
    }

    private function diagramPrompt(string $subject, string $notes): string
    {
        return <<<PROMPT
You are a {$subject} university tutor who creates clear visual study diagrams.

Use the notes below as the official guide.

Create Mermaid diagrams that explain the main topics visually.

STRICT RULES:
- Return JSON only.
- Generate 3 to 8 diagrams depending on the notes.
- Each diagram must explain one topic or process clearly.
- Use Mermaid flowchart syntax only.
- Do not wrap Mermaid code in markdown fences.
- Use simple node labels that Mermaid can render safely.
- Do not include unsupported Mermaid features.

FORMAT:
{
  "diagrams": [
    {
      "title": "Topic title",
      "description": "Short explanation of what the diagram shows.",
      "mermaid": "flowchart TD\nA[Start] --> B[Main idea]"
    }
  ]
}

NOTES:
{$notes}
PROMPT;
    }
}
