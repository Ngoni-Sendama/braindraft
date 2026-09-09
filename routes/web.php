<?php

use App\Http\Controllers\CloudExamController;
use App\Http\Controllers\CloudNotesSummaryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CloudExamController::class, 'index'])->name('cloud.exam.index');
Route::post('/cloud-exam/generate', [CloudExamController::class, 'generate'])->name('cloud.exam.generate');
Route::get('/cloud-exam/attempt', [CloudExamController::class, 'attempt'])->name('cloud.exam.attempt');
Route::post('/cloud-exam/mark', [CloudExamController::class, 'mark'])->name('cloud.exam.mark');

Route::post('/cloud-exam/summarize', [CloudNotesSummaryController::class, 'summarize'])
    ->name('cloud.exam.summarize');
Route::post('/cloud-exam/diagrams', [CloudNotesSummaryController::class, 'diagrams'])
    ->name('cloud.exam.diagrams');
