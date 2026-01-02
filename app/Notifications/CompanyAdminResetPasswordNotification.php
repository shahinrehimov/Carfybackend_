<?php
namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyAdminResetPasswordNotification extends Notification
{
    public string $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = config('app.company_frontend_url') . '/auth/reset-password?token=' . $this->token . '&email=' . $notifiable->email;

        return (new MailMessage)
            ->subject('Reset Password Notification')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', $url)
            ->line('This password reset link will expire in :count minutes.', ['count' => config('auth.passwords.users.expire')])
            ->line('If you did not request a password reset, no further action is required.');
    }

}
