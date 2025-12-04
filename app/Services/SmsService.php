<?php

namespace App\Services;

class SmsService
{
    private static ?SmsService $instance = null;

    public static function instance(): SmsService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function send(string $phone, string $message): void
    {
        // TODO:: sms service

        LoggerService::instance()->log('OTP sent!', [
            'phone' => $phone,
            'message' => $message,
        ]);
    }
}
