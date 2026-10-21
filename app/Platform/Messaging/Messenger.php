<?php

declare(strict_types=1);

namespace App\Platform\Messaging;

use App\Mail\CustomerMessageMail;
use App\Models\CustomerMessage;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends a stored CustomerMessage over its channel and records the outcome.
 * SMS goes through the configured SmsDriver; email through Laravel Mail.
 */
final readonly class Messenger
{
    public function __construct(private SmsDriver $sms) {}

    public function deliver(CustomerMessage $message): CustomerMessage
    {
        try {
            if ($message->channel === 'sms') {
                $body = $message->body;
                $footer = (string) config('messaging.sms_footer', '');
                if ($footer !== '' && ! str_contains($body, $footer)) {
                    $body .= "\n".$footer;
                }
                $ref = $this->sms->send((string) $message->to, $body, (string) config('messaging.sms_from'));
            } else {
                Mail::to((string) $message->to)->send(new CustomerMessageMail($message));
                $ref = 'mail-'.$message->id;
            }

            $message->forceFill(['status' => 'sent', 'sent_at' => now(), 'provider_ref' => $ref, 'error' => null])->save();
        } catch (Throwable $e) {
            $message->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)])->save();
        }

        return $message;
    }
}
