<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use ZipArchive;

class CloudExamController extends Controller
{
    private array $subjects = [
        'CHE 110' => 'CHE 110',
        'CAP 404' => 'CAP 404',
        'CAP 213' => 'CAP 213',
    ];

    /*
    |--------------------------------------------------------------------------
    | Views
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $subjects = $this->subjects;
        $selectedSubject = request('subject', array_key_first($subjects));

        if (! array_key_exists($selectedSubject, $subjects)) {
            $selectedSubject = array_key_first($subjects);
        }

        $basePath = $this->subjectBasePath($selectedSubject);
        $folder = public_path($basePath);

        if (! File::isDirectory($folder)) {
            File::makeDirectory($folder, 0755, true);
        }

        $files = collect(File::files($folder))
            ->filter(fn ($file) => in_array(strtolower($file->getExtension()), ['pptx', 'pdf', 'txt']))
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'path' => $basePath.'/'.$file->getFilename(),
                'size' => round($file->getSize() / 1024 / 1024, 2),
            ])
            ->values();

        return view('cloud-exam.index', compact('files', 'subjects', 'selectedSubject'));
    }

    public function attempt()
    {
        $questions = session('cloud_exam.questions', []);

        if (empty($questions)) {
            return redirect()
                ->route('cloud.exam.index')
                ->withErrors(['exam' => 'Generate an exam first.']);
        }

        return view('cloud-exam.attempt', compact('questions'));
    }

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */

    public function generate(Request $request)
    {
        $request->validate([
            'subject' => ['required', 'string', 'in:'.implode(',', array_keys($this->subjects))],
            'sources' => ['required', 'array', 'min:1'],
            'sources.*' => ['string'],
            'question_count' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $notes = $this->collectNotes($request->subject, $request->sources);

        $prompt = $this->questionPrompt($request->subject, $notes, (int) $request->question_count);

        $response = $this->askGroqJson($prompt);

        if (! is_array($response) || ! isset($response['questions'])) {
            return back()->withErrors(['ai' => 'Failed to generate questions.']);
        }

        session([
            'cloud_exam.questions' => $response['questions'],
            'cloud_exam.subject' => $request->subject,
            'cloud_exam.sources' => $request->sources,
        ]);

        return redirect()->route('cloud.exam.attempt');
    }

    public function mark(Request $request)
    {
        $questions = session('cloud_exam.questions', []);

        if (empty($questions)) {
            return redirect()
                ->route('cloud.exam.index')
                ->withErrors(['exam' => 'Generate an exam first.']);
        }

        $request->validate([
            'answers' => ['required', 'array'],
        ]);

        $payload = $this->buildMarkingPayload($questions, $request->answers);

        $result = $this->askGroqJson($this->markingPrompt($payload));

        if (! is_array($result)) {
            return back()->withErrors(['ai' => 'Failed to mark exam.']);
        }

        return view('cloud-exam.result', compact('result'));
    }

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

    private function buildMarkingPayload(array $questions, array $answers): array
    {
        $payload = [];

        foreach ($questions as $index => $q) {
            $payload[] = [
                'number' => $index + 1,
                'question' => $q['question'] ?? '',
                'marks' => $q['marks'] ?? 2,
                'topic' => $q['topic'] ?? '',
                'expected_points' => $q['expected_points'] ?? [],
                'student_answer' => $answers[$index] ?? '',
            ];
        }

        return $payload;
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

    /*
    |--------------------------------------------------------------------------
    | Prompts (clean + readable)
    |--------------------------------------------------------------------------
    */

    private function questionPrompt(string $subject, string $notes, int $count): string
    {
        return <<<PROMPT
You are a {$subject} university exam setter.

Generate exactly {$count} written-answer questions from the provided notes.

STRICT QUESTION RULES:
- Written-answer questions only.
- No MCQs.
- Each question must test ONLY ONE main idea.
- Do NOT combine many tasks in one question.
- Do NOT write questions like: "Contrast..., discuss..., determine..., establish..."
- Use only ONE command verb per question.
- Questions must be short, clear, and exam-like.
- Each question must carry 2 to 8 marks only.

MARKING DEPTH RULES:
- 2 marks: define, identify, list, outline ONE small concept.
- 3 to 4 marks: explain or describe ONE concept.
- 5 to 6 marks: discuss or examine ONE concept in detail.
- 7 to 8 marks: one broad concept only, not multiple unrelated tasks.

GOOD QUESTION EXAMPLES:
- Define cloud computing. [2 marks]
- Explain the characteristics of cloud computing. [4 marks]
- Describe the role of virtualization in cloud computing. [5 marks]
- Compare public and private cloud deployment models. [6 marks]
- Discuss the benefits of Platform as a Service for developers. [6 marks]

BAD QUESTION EXAMPLES:
- Explain cloud computing, discuss benefits, compare with traditional computing, and describe service models.
- Contrast deployment models and discuss advantages and disadvantages and determine security impacts.

Before returning:
- Check every question has only ONE task.
- If a question has multiple tasks, split it or simplify it.
- Make sure marks match the question depth.

Return JSON only.

JSON format:
{
  "questions": [
    {
      "question": "Explain one important concept from the notes.",
      "marks": 4,
      "topic": "Topic from {$subject}",
      "expected_points": [
        "On-demand self-service",
        "Broad network access",
        "Resource pooling",
        "Rapid elasticity"
      ]
    }
  ]
}

Notes:
{$notes}
PROMPT;
    }

    private function markingPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT);

        return <<<PROMPT
You are a strict but fair university examiner.

Mark the student's written answers.

MARKING RULES:
- Award partial marks fairly.
- Use the question marks as the maximum score.
- Accept equivalent correct definitions even if wording differs from the model answer.
- Prioritize understanding of the concept over exact terminology.
- Award full marks if the student captures the core concept accurately, even if minor details are missing.
- Only reduce marks if essential concepts are incorrect or missing.
- Do not give full marks for vague or incorrect answers.
- Do NOT penalize for missing optional or extra elaboration.
- Missing points must be critical, not just additional detail.
- Be specific in feedback.
- Clearly state what was correct and what essential part was missing.
- If the answer is empty, award 0.
- The model answer should include only the essential points required for full marks.

FEEDBACK STYLE:
Bad feedback:
"The answer lacks clarity."

Good feedback:
"You correctly mentioned resource sharing, but you missed rapid elasticity and measured service."

Return JSON only.

JSON format:
{
  "total_score": 0,
  "total_marks": 0,
  "percentage": 0,
  "grade_comment": "Overall comment",
  "questions": [
    {
      "number": 1,
      "question": "...",
      "marks": 4,
      "student_score": 2,
      "feedback": "You correctly explained ..., but missed ...",
      "missing_points": ["...", "..."],
      "model_answer": "..."
    }
  ]
}

Student answers:
{$json}
PROMPT;
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
}
