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
                // Never leak OTP codes; mask any digits in the message.
                'message' => preg_replace('/\d/', '*', $message),
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

    /**
     * Send the same informational message to many phones in one gateway request.
     *
     * @param string[] $phones
     * @return array<string,bool> map of original phone => accepted
     */
    public function sendInfoBulk(array $phones, string $message): array
    {
        if (empty($phones)) {
            return [];
        }

        if (config('sms.debug')) {
            LoggerService::instance()->log('SMS bulk debug mode — not sent', [
                'count'   => count($phones),
                'message' => preg_replace('/\d/', '*', $message),
                'purpose' => 'INF',
            ]);

            return array_fill_keys($phones, true);
        }

        try {
            return $this->provider->sendBulk($phones, $message, 'INF');
        } catch (\Throwable $e) {
            LoggerService::instance()->log('SMS bulk send failed', [
                'count' => count($phones),
                'error' => $e->getMessage(),
            ], true);

            return array_fill_keys($phones, false);
        }
    }

    private function resolveProvider(): SmsProviderInterface
    {
        return match (config('sms.provider')) {
            'pg365' => new SmsPg365Service(),
            default => throw new \RuntimeException('Unsupported SMS provider: ' . config('sms.provider')),
        };
    }
}
