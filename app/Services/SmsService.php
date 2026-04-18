<?php

namespace App\Services;

use App\Interfaces\Sms\SmsProviderInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SmsService
{
    private static ?SmsService $instance = null;
    private SmsProviderInterface $provider;

    private function __construct()
    {
        $this->provider = $this->resolveProvider();
    }

    public static function instance(): SmsService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function send(string $phone, string $message, string $purpose = 'OTP'): void
    {
        if (config('sms.debug')) {
            LoggerService::instance()->log('SMS debug mode — not sent', [
                'phone'   => $phone,
                'message' => $message,
                'purpose' => $purpose,
            ]);

            return;
        }

        try {
            $this->provider->send($phone, $message, $purpose);
        } catch (\Throwable $e) {
            LoggerService::instance()->log('SMS send failed', [
                'phone'   => $phone,
                'purpose' => $purpose,
                'error'   => $e->getMessage(),
            ], true);

            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('SMS could not be sent. Please try again later.')
                    ->getLang('sms_send_failed')
            );
        }
    }

    public function sendOtp(string $phone, string $message): void
    {
        $this->send($phone, $message, 'OTP');
    }

    public function sendInfo(string $phone, string $message): void
    {
        $this->send($phone, $message, 'INF');
    }

    private function resolveProvider(): SmsProviderInterface
    {
        return match (config('sms.provider')) {
            'pg365' => new SmsPg365Service(),
            default => throw new \RuntimeException('Unsupported SMS provider: ' . config('sms.provider')),
        };
    }
}
