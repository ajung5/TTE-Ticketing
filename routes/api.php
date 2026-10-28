<?php

use App\Http\Controllers\ZohoWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/tickets/zoho', [ZohoWebhookController::class, 'store'])
    ->middleware('throttle:30,1');
