<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = ['user_id', 'name', 'description', 'color'];

    public function studySets()
    {
        return $this->hasMany(StudySet::class);
    }
}
