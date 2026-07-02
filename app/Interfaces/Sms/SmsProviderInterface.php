<?php

namespace App\Interfaces\Sms;

interface SmsProviderInterface
{
    public function send(string $phone, string $message, string $purpose): void;

    /**
     * Send the same message to many phones in a single request.
     *
     * @param string[] $phones original (unformatted) phone numbers
     * @return array<string,?string> map of original phone => error message (null = accepted)
     */
    public function sendBulk(array $phones, string $message, string $purpose): array;
}
