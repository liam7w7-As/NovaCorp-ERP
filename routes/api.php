<?php

use App\Http\Controllers\MetaWhatsappWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/webhooks/meta/whatsapp', [MetaWhatsappWebhookController::class, 'verificar'])
    ->name('meta.whatsapp.webhook.verify');
Route::post('/webhooks/meta/whatsapp', [MetaWhatsappWebhookController::class, 'recibir'])
    ->name('meta.whatsapp.webhook.receive');

Route::get('/whatsapp/webhook', [MetaWhatsappWebhookController::class, 'verificar'])
    ->name('meta.whatsapp.legacy.verify');
Route::post('/whatsapp/webhook', [MetaWhatsappWebhookController::class, 'recibir'])
    ->name('meta.whatsapp.legacy.receive');
