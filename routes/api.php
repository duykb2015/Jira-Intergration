<?php

use App\Http\Controllers\ClockifyWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/clockify/{connectionUuid}/{secret}', ClockifyWebhookController::class)
    ->whereUuid('connectionUuid')
    ->name('webhooks.clockify');
