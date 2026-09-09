<?php

use App\Models\Bot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns branding for an active bot on an allowed domain', function () {
    $bot = Bot::create([
        'bot_number' => 'test-1001',
        'title' => 'Test bot',
        'allowed_domains' => ['example.com'],
    ]);

    $this->getJson('/api/widget/bootstrap?bot_id='.$bot->bot_number.'&domain=example.com')
        ->assertOk()
        ->assertJsonPath('brand.title', 'Test bot');
});

it('rejects widget requests from unapproved domains', function () {
    $bot = Bot::create([
        'bot_number' => 'test-1002',
        'title' => 'Test bot',
        'allowed_domains' => ['example.com'],
    ]);

    $this->getJson('/api/widget/bootstrap?bot_id='.$bot->bot_number.'&domain=attacker.example')
        ->assertNotFound();
});

it('validates and limits widget messages', function () {
    $this->postJson('/api/widget/message', [
        'bot_id' => 'test-1003',
        'message' => str_repeat('x', 2001),
        'domain' => 'example.com',
    ])->assertUnprocessable();
});
