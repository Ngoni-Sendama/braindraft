<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WidgetMessageController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bot_id' => ['required', 'string', 'max:64'],
            'message' => ['required', 'string', 'max:2000'],
            'domain' => ['required', 'string', 'max:255', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i'],
        ]);
        $bot = Bot::where('bot_number', $data['bot_id'])->where('is_active', true)->first();

        if (! $bot || ($bot->allowed_domains && ! in_array(strtolower($data['domain']), array_map('strtolower', $bot->allowed_domains), true))) {
            return response()->json(['message' => 'Bot unavailable.'], 404);
        }

        $response = Http::timeout(30)->withToken(config('services.groq.key'))->post('https://api.groq.com/openai/v1/chat/completions', [
            'model' => config('services.groq.model'), 'temperature' => 0.3,
            'messages' => [['role' => 'system', 'content' => $bot->greeting ?: 'You are a helpful support assistant.'], ['role' => 'user', 'content' => $data['message']]],
        ]);

        return response()->json([
            'reply' => $response->successful() ? ($response->json('choices.0.message.content') ?: 'I could not generate a response.') : 'The assistant is temporarily unavailable.',
        ]);
    }
}
