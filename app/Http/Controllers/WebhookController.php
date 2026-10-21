<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Communication\RecordInboundMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Inbound texting-platform webhook (SOW §3.4). Shared-token protected; no session. */
final class WebhookController extends Controller
{
    public function inboundSms(Request $request): JsonResponse
    {
        $token = (string) ($request->header('X-Messaging-Token') ?? $request->input('token'));
        if ($token === '' || ! hash_equals((string) config('messaging.inbound_token'), $token)) {
            return response()->json(['ok' => false, 'error' => 'invalid token'], 403);
        }

        $data = $request->validate([
            'from' => ['required', 'string', 'max:40'],
            'body' => ['required', 'string', 'max:2000'],
            'to' => ['nullable', 'string', 'max:40'],
            'ref' => ['nullable', 'string', 'max:120'],
        ]);

        $message = RecordInboundMessage::run($data['from'], $data['body'], $data['to'] ?? null, $data['ref'] ?? null);

        if ($message === null) {
            Log::notice('Inbound SMS from unknown number', ['from' => $data['from']]);

            return response()->json(['ok' => true, 'matched' => false], 202);
        }

        return response()->json(['ok' => true, 'matched' => true, 'id' => $message->id]);
    }
}
