<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendDtPassword extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $password) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nuevo acceso a '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.dt.password',
            with: [
                'password' => $this->password,
                'url' => url('dt/login'),
            ],
        );
    }
}
