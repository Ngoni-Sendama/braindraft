<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bot extends Model
{
    protected $fillable = [
        'bot_number',
        'title',
        'subtitle',
        'primary_color',
        'logo_url',
        'greeting',
        'is_active',
        'allowed_domains',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'allowed_domains' => 'array'];
    }
}
