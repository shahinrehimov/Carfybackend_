<?php

namespace App\Interfaces\Sms;

interface SmsProviderInterface
{
    public function send(string $phone, string $message, string $purpose): void;
}
