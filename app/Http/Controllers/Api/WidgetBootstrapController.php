<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WidgetBootstrapController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $botId = $request->query('bot_id');
        $domain = strtolower(trim((string) $request->query('domain')));

        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $domain)) {
            return response()->json(['message' => 'Invalid domain.'], 422);
        }

        $bot = Bot::where('bot_number', $botId)->first();

        if (! $bot || ! $bot->is_active || ($bot->allowed_domains && ! in_array($domain, array_map('strtolower', $bot->allowed_domains), true))) {
            return response()->json([
                'status' => 'inactive',
                'message' => 'Bot not found or inactive.',
            ], 404);
        }

        return response()->json([
            'status' => 'active',
            'brand' => [
                'title' => $bot->title,
                'subtitle' => $bot->subtitle,
                'primary_color' => $bot->primary_color,
                'logo_url' => $bot->logo_url,
                'greeting' => $bot->greeting,
            ],
            'bot' => [
                'id' => $bot->bot_number,
            ],
            'domain' => $domain,
        ]);
    }
}
