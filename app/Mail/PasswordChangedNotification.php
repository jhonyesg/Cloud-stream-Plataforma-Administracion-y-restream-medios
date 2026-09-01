<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordChangedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $changedBy,
        public ?User $actor,
        public ?string $ip,
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = $this->changedBy === 'admin'
            ? 'Tu contraseña fue restablecida por un administrador'
            : 'Tu contraseña fue actualizada';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.password-changed',
            with: [
                'user' => $this->user,
                'changedBy' => $this->changedBy,
                'actor' => $this->actor,
                'ip' => $this->ip,
                'at' => now(),
            ],
        );
    }
}
