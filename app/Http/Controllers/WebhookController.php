<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Communication\RecordInboundMessage;
use App\Actions\Messaging\IngestInboundEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Inbound texting-platform webhook (SOW §3.4). Shared-token protected; no session. */
final class WebhookController extends Controller
{
    public function inboundSms(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
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

    /** SOW §3.12 — the Client's inbound email address, forwarded by the mail provider. */
    public function inboundEmail(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
            return response()->json(['ok' => false, 'error' => 'invalid token'], 403);
        }

        $data = $request->validate([
            'from' => ['required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:160'],
            'to' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:500'],
            'text' => ['nullable', 'string', 'max:200000'],
            'html' => ['nullable', 'string', 'max:400000'],
            'message_id' => ['nullable', 'string', 'max:255'],
        ]);

        $body = $data['text'] ?? ($data['html'] ? trim(strip_tags((string) $data['html'])) : null);
        $email = IngestInboundEmail::run($data['from'], $data['from_name'] ?? null, $data['to'] ?? null, $data['subject'] ?? null, $body, $data['message_id'] ?? null);

        return response()->json(['ok' => true, 'id' => $email->id, 'routed' => $email->status === 'routed']);
    }

    private function authorized(Request $request): bool
    {
        $token = (string) ($request->header('X-Messaging-Token') ?? $request->input('token'));

        return $token !== '' && hash_equals((string) config('messaging.inbound_token'), $token);
    }
}
