<?php

use App\Http\Controllers\Api\WidgetBootstrapController;
use App\Http\Controllers\Api\WidgetMessageController;
use Illuminate\Support\Facades\Route;

Route::get('/widget/bootstrap', WidgetBootstrapController::class)->middleware('throttle:widget-bootstrap');
Route::post('/widget/message', WidgetMessageController::class)->middleware('throttle:widget-message');
