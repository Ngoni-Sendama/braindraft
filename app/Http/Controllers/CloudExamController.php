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
                'options' => $q['options'] ?? [],
                'correct_answer' => $q['correct_answer'] ?? '',
                'topic' => $q['topic'] ?? '',
                'explanation' => $q['explanation'] ?? '',
                'marks' => $q['marks'] ?? 5,
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
        // CHE 110 is MCQ-based; CAP 404 and CAP 213 are subjective.
        $isMcq = $subject === 'CHE 110';
        $questionType = $isMcq ? 'multiple-choice questions (MCQs)' : 'subjective written-answer questions';
        $format = $isMcq
            ? '- Every question must have exactly four options: A, B, C and D. Only one option may be correct.\n- Test definitions, numbers, dates, classifications, examples, functions, causes, effects and distinctions.'
            : '- Questions must require written explanations appropriate for a 100% subjective examination.\n- Test definitions, explanations, comparisons, applications, causes, effects and analysis.';
        $jsonQuestion = $isMcq
            ? '"options": {"A": "Option one", "B": "Option two", "C": "Option three", "D": "Option four"},\n      "correct_answer": "B",\n      "explanation": "Brief explanation based only on the notes.",'
            : '"marks": 5,\n      "expected_points": ["Essential point one", "Essential point two"],';
        $detailRule = $isMcq
            ? '- Use plausible distractors based on common confusions.'
            : '- Match the marks to the depth of the written answer.';

        return <<<PROMPT
You are a {$subject} university examiner preparing {$questionType}.

Generate exactly {$count} questions from the provided notes.

STRICT QUESTION RULES:
{$format}
- Include difficult but source-supported details that could reasonably appear in the examination.
- {$detailRule}
- Do not test information that is absent from the notes.
- Each question must test ONLY ONE main idea.
- Avoid trick wording, double negatives and ambiguous options.
- Make distractors plausible and based on common confusions.
- Keep the wording beginner-friendly but preserve technical accuracy.

Before returning:
- Check that the format matches the subject: four options and one correct answer for MCQs; marks and essential points for subjective questions.
- Check that the answer is supported by the notes.

Return JSON only.

JSON format:
{
  "questions": [
    {
      "question": "Which statement is correct according to the notes?",
      {$jsonQuestion}
      "topic": "Topic from {$subject}",
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
        $isMcq = ! empty($payload[0]['correct_answer']);
        $markingMode = $isMcq
            ? '- Award 1 mark for a correct option and 0 for an incorrect or unanswered option. Use correct_answer as the answer key.'
            : '- Award partial marks fairly using marks and expected_points. Accept equivalent correct wording.';

        return <<<PROMPT
You are a strict but fair university examiner.

Mark the student answers.

MARKING RULES:
- {$markingMode}
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
