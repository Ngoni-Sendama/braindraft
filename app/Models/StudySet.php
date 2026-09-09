<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudySet extends Model
{
    protected $fillable = ['user_id', 'subject_id', 'title', 'description', 'visibility', 'source_type', 'status'];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
