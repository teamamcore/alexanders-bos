<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Communication\RecordInboundMessage;
use App\Actions\Messaging\IngestInboundEmail;
use App\Actions\Portals\ImportPortalWorkOrder;
use App\Actions\Portals\RecordPortalPayment;
use App\Models\PortalSyncEvent;
use App\Models\PortalWorkOrder;
use App\Models\VendorPortalIntegration;
use App\Platform\Portals\PortalWorkRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

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

    /** SOW §3.14 — a portal pushes a work request or a payment notice (per-integration token). */
    public function portal(Request $request, VendorPortalIntegration $integration): JsonResponse
    {
        $token = (string) ($request->header('X-Portal-Token') ?? $request->input('token'));
        if ($token === '' || ! $integration->webhook_token || ! hash_equals($integration->webhook_token, $token)) {
            return response()->json(['ok' => false, 'error' => 'invalid token'], 403);
        }
        app()->instance('currentTenantId', $integration->tenant_id);

        $data = $request->validate([
            'type' => ['required', Rule::in(['work_request', 'payment', 'status'])],
            'external_id' => ['required', 'string', 'max:80'],
        ]);

        if ($data['type'] === 'work_request') {
            $pwo = ImportPortalWorkOrder::run($integration, PortalWorkRequest::fromArray($request->all()));

            return response()->json(['ok' => true, 'id' => $pwo->id, 'work_order' => $pwo->workOrder?->number]);
        }

        $pwo = PortalWorkOrder::query()->where('integration_id', $integration->id)->where('external_id', $data['external_id'])->first();
        if (! $pwo instanceof PortalWorkOrder) {
            return response()->json(['ok' => false, 'error' => 'unknown work request'], 404);
        }

        if ($data['type'] === 'payment') {
            RecordPortalPayment::run($pwo, $request->input('paid_at'), $request->input('reference'));
        } else {
            $pwo->forceFill(['external_status' => (string) $request->input('status', $pwo->external_status), 'last_synced_at' => now()])->save();
            PortalSyncEvent::log($integration, 'inbound', 'status', 'ok', 'Portal status: '.$pwo->external_status, $pwo, $request->all());
        }

        return response()->json(['ok' => true, 'id' => $pwo->id, 'status' => $pwo->fresh()?->status]);
    }

    private function authorized(Request $request): bool
    {
        $token = (string) ($request->header('X-Messaging-Token') ?? $request->input('token'));

        return $token !== '' && hash_equals((string) config('messaging.inbound_token'), $token);
    }
}
