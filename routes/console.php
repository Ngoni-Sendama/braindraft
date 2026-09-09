<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('groq:test {prompt?}', function (?string $prompt = null) {
    $key = config('services.groq.key');

    if (! $key) {
        $this->error('GROQ_API_KEY is not configured.');

        return 1;
    }

    $response = Http::timeout(30)->withToken($key)->post('https://api.groq.com/openai/v1/chat/completions', [
        'model' => config('services.groq.model'),
        'temperature' => 0.2,
        'messages' => [
            ['role' => 'user', 'content' => $prompt ?: 'Reply with: Groq connection successful.'],
        ],
    ]);

    if (! $response->successful()) {
        $this->error('Groq request failed: HTTP '.$response->status());

        return 1;
    }

    $this->info('Groq connection successful.');
    $this->line($response->json('choices.0.message.content', 'No response content returned.'));

    return 0;
})->purpose('Test the Groq API connection');

Artisan::command('openai:test {prompt?}', function (?string $prompt = null) {
    $key = config('services.openai.key');

    if (! $key) {
        $this->error('OPENAI_API_KEY is not configured.');

        return 1;
    }

    $response = Http::timeout(30)->withToken($key)->post('https://api.openai.com/v1/chat/completions', [
        'model' => config('services.openai.model'),
        'temperature' => 0.2,
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            ['role' => 'user', 'content' => $prompt ?: 'Return JSON with {"status":"OpenAI connection successful"}.'],
        ],
    ]);

    if (! $response->successful()) {
        $this->error('OpenAI request failed: HTTP '.$response->status());

        return 1;
    }

    $this->info('OpenAI connection successful.');
    $this->line($response->json('choices.0.message.content', 'No response content returned.'));

    return 0;
})->purpose('Test the OpenAI API connection');
