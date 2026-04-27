<?php

namespace App\Mail;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly User $user,
        private readonly Notification $notification,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notification->getLang('title'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
            with: [
                'title' => $this->notification->getLang('title'),
                'body'  => $this->notification->getLang('body'),
                'image' => $this->notification->image,
            ],
        );
    }
}
