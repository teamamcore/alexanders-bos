<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\CustomerMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** SOW §3.4 — an email update to a customer (scheduled day, arrival, status). */
final class CustomerMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly CustomerMessage $message) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->message->subject ?: (config('app.name').' update'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.customer-message', with: ['body' => $this->message->body]);
    }
}
