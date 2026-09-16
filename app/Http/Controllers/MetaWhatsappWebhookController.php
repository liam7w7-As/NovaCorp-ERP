<?php

namespace App\Http\Controllers;

use App\Services\MetaWhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MetaWhatsappWebhookController extends Controller
{
    public function verificar(Request $request): Response
    {
        $tokenConfigurado = config('services.meta_whatsapp.webhook_verify_token');
        $tokenRecibido = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $modo = $request->query('hub_mode', $request->query('hub.mode'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        abort_unless($modo === 'subscribe' && $tokenConfigurado && hash_equals($tokenConfigurado, (string) $tokenRecibido), 403);

        return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function recibir(Request $request, MetaWhatsappService $whatsapp): JsonResponse
    {
        abort_unless(
            $whatsapp->validarFirma($request->header('X-Hub-Signature-256'), $request->getContent()),
            403
        );

        $resultado = $whatsapp->procesarWebhook($request->json()->all());

        return response()->json([
            'status' => 'EVENT_RECEIVED',
            ...$resultado,
        ]);
    }
}
