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
            'sources' => ['nullable', 'array'],
            'passage' => ['nullable', 'string', 'max:12000'],
        ]);

        if (! trim((string) $request->input('passage')) && empty($request->input('sources'))) {
            return back()->withErrors(['sources' => 'Provide a passage or select at least one source note.']);
        }

        $notes = trim((string) $request->input('passage')) ?: $this->collectNotes($request->subject, $request->sources);

        $result = $this->askOpenAiJson($this->diagramPrompt($request->subject, $notes));

        if (! is_array($result)) {
            return back()->withErrors(['ai' => 'Failed to generate diagrams. Check OPENAI_API_KEY.']);
        }

        return view('cloud-exam.diagrams', compact('result'));
    }

    private function summaryPrompt(string $subject, string $notes): string
    {
        return <<<PROMPT
You are an expert {$subject} university tutor preparing a beginner for a multiple-choice examination.

Treat the notes below as the authoritative source of truth for content and scope.

CORE RULES:
1. Identify the main topics in the notes and preserve all examinable details. Group related sub-points sensibly; do not oversimplify or omit numbers, dates, names, classifications, examples, functions, causes, effects or distinctions.
2. Stay strictly within {$subject}. Do not introduce unrelated topics, tools, or concepts.
3. If notes are thin, supplement only with established {$subject} knowledge. Do not contradict the notes.
4. If notes are unclear, incomplete, or garbled, reconstruct the intended meaning and explain the inference in assumptions. Never silently invent facts.
5. Write for a true beginner. Explain simply first, then give exam-level detail. Define technical terms when first used, avoid unexplained acronyms, and include a "Do not confuse" section for similar concepts.
6. Treat the syllabus as the scope when it is included among the sources. Treat sample-paper content as evidence of question style and likely emphasis, not as permission to invent facts.
7. If the sample paper is scanned or unreadable, say so in assumptions.
8. Return JSON only. No text before or after the JSON.

LENGTH AND DEPTH RULES:
- summary: 120 to 220 words.
- key_points: 6 to 12 specific, exam-checkable facts.
- important_facts: all important numbers, dates, names, classifications and technical details.
- mcq_traps: common confusions and likely distractors.
- Provide a simple explanation and an exam-level explanation for every topic.
- real_world_examples: 2 to 4 concrete examples relevant to {$subject}.

FORMAT:
{
  "topics": [
    {
      "title": "string",
      "summary": "string",
      "simple_explanation": "string",
      "exam_level_explanation": "string",
      "key_points": ["string", "..."],
      "important_facts": ["string", "..."],
      "mcq_traps": ["string", "..."],
      "how_to_answer": {
        "5_mark_structure": "brief structure guidance",
        "10_mark_structure": "structure guidance with sub-points, examples, or comparisons"
      },
      "real_world_examples": ["string", "..."],
      "assumptions": "empty string if notes were clear; otherwise state what was inferred"
    }
  ]
}

NOTES ({$subject}):
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
