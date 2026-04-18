<?php

namespace App\Services;

use App\Interfaces\Sms\SmsProviderInterface;
use Illuminate\Support\Facades\Http;

class SmsPg365Service implements SmsProviderInterface
{
    private ?string $baseUrl;
    private ?string $publicKey;
    private ?string $privateKey;
    private ?string $originator;

    public function __construct()
    {
        $this->baseUrl    = config('sms.providers.pg365.base_url');
        $this->publicKey  = config('sms.providers.pg365.public_key');
        $this->privateKey = config('sms.providers.pg365.private_key');
        $this->originator = config('sms.providers.pg365.originator');
    }

    public function send(string $phone, string $message, string $purpose): void
    {
        $body = [
            'Text'      => $message,
            'Purpose'   => $purpose,
            'Options'   => array_filter([
                'Originator' => $this->originator ?: null,
                'Encoding'   => 'UNICODE',
            ]),
            'Receivers' => [
                ['Receiver' => $this->formatPhone($phone)],
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->privateKey,
            'Content-Type'  => 'application/json',
        ])->post($this->baseUrl . '/gateway/api/sms/v1/message/send?publicKey=' . $this->publicKey, $body);

        $result = $response->json();
        $status = $result['Status'] ?? null;

        LoggerService::instance()->log('SMS PG365 response', [
            'phone'       => $phone,
            'purpose'     => $purpose,
            'status'      => $status,
            'description' => $result['Description'] ?? null,
            'accepted'    => $result['Result']['ReceiversAccepted'] ?? [],
            'rejected'    => $result['Result']['ReceiversRejected'] ?? [],
        ]);

        if ($status !== 200 && $status !== '200') {
            throw new \RuntimeException('SMS göndərilmədi: ' . ($result['Description'] ?? 'Xəta baş verdi'));
        }
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '994' . substr($phone, 1);
        }

        if (!str_starts_with($phone, '994')) {
            $phone = '994' . $phone;
        }

        return $phone;
    }
}
