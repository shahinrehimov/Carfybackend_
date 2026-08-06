<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomChangeEmailNotificationOTP extends Notification
{
    private int $otp;

    public function __construct(int $otp)
    {
        $this->otp = $otp;
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm Your New Email Address')
            ->line('Please confirm your new email address with OTP')
            ->line('Your OTP is: ' . $this->otp)
            ->line('If you did not request this change, no further action is required.');
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }
}
