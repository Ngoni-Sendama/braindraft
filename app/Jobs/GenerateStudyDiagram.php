<?php

namespace App\Jobs;

use App\Models\StudyDiagram;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateStudyDiagram implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $diagramId, public string $subject, public string $notes) {}

    public function handle(): void
    {
        $diagram = StudyDiagram::findOrFail($this->diagramId);
        $prompt = "Create a clean educational study diagram for {$this->subject}. Use readable labels, arrows and high contrast. Include only concepts supported by this material: ".Str::limit($this->notes, 12000, '');

        $response = Http::timeout(120)->withToken(config('services.openai.key'))->post('https://api.openai.com/v1/images/generations', [
            'model' => config('services.openai.image_model', 'gpt-image-1-mini'),
            'prompt' => $prompt,
            'size' => '1536x1024',
            'quality' => 'medium',
            'output_format' => 'png',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Image API request failed: '.$response->body());
        }

        $image = $response->json('data.0');
        $contents = ! empty($image['b64_json'])
            ? base64_decode($image['b64_json'], true)
            : Http::timeout(120)->get($image['url'] ?? '')->body();

        if (! $contents) {
            throw new \RuntimeException('The image API returned no image data.');
        }

        $path = 'study-diagrams/'.strtolower($this->subject).'-'.$diagram->id.'-'.Str::random(8).'.png';
        Storage::disk('public')->put($path, $contents);
        $diagram->update(['status' => 'ready', 'path' => $path]);
    }

    public function failed(\Throwable $exception): void
    {
        StudyDiagram::whereKey($this->diagramId)->update(['status' => 'failed', 'error' => Str::limit($exception->getMessage(), 1000)]);
    }
}
