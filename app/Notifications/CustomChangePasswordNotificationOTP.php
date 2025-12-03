<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomChangePasswordNotificationOTP extends Notification
{
    private int $otp;

    public function __construct(int $otp)
    {
        $this->otp = $otp;
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Change Password')
            ->line('Please verify your email address with OTP')
            ->line('Your OTP is: ' . $this->otp)
            ->line('If you did not create an account, no further action is required.');
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }
}
