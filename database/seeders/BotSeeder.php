<?php

namespace Database\Seeders;

use App\Models\Bot;
use Illuminate\Database\Seeder;

class BotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Bot::create([
            'bot_number' => '1001',
            'title' => 'BrainDraft Support',
            'subtitle' => 'Ask us anything',
            'primary_color' => '#2563eb',
            'greeting' => 'Hi! Welcome to BrainDraft.',
        ]);
        Bot::create([
            'bot_number' => '1002',
            'title' => 'Another Support',
            'subtitle' => 'Ask us anything',
            'primary_color' => '#cd7006',
            'greeting' => 'Hi! Welcome to Another Support.',
        ]);
    }
}
