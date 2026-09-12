<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyDiagram extends Model
{
    protected $fillable = ['subject_code', 'title', 'status', 'path', 'error'];
}
