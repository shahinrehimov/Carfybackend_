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
        $formatted = $this->formatPhone($phone);
        $result = $this->dispatch([$formatted], $message, $purpose);
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

    public function sendBulk(array $phones, string $message, string $purpose): array
    {
        // Map formatted receiver -> list of original phones (several users may share a number).
        $formattedMap = [];
        foreach ($phones as $phone) {
            $formattedMap[$this->formatPhone($phone)][] = $phone;
        }

        $result = $this->dispatch(array_keys($formattedMap), $message, $purpose);
        $status = $result['Status'] ?? null;

        LoggerService::instance()->log('SMS PG365 bulk response', [
            'purpose'     => $purpose,
            'count'       => count($phones),
            'status'      => $status,
            'description' => $result['Description'] ?? null,
            'accepted'    => $result['Result']['ReceiversAccepted'] ?? [],
            'rejected'    => $result['Result']['ReceiversRejected'] ?? [],
        ]);

        // Whole request failed — treat every phone as not sent.
        if ($status !== 200 && $status !== '200') {
            return array_fill_keys($phones, false);
        }

        // Anything the gateway explicitly rejected is marked failed; the rest accepted.
        $rejectedFormatted = [];
        foreach ($result['Result']['ReceiversRejected'] ?? [] as $item) {
            $receiver = is_array($item) ? ($item['Receiver'] ?? null) : $item;
            if ($receiver !== null) {
                $rejectedFormatted[(string) $receiver] = true;
            }
        }

        $out = [];
        foreach ($formattedMap as $formatted => $originals) {
            $accepted = !isset($rejectedFormatted[$formatted]);
            foreach ($originals as $original) {
                $out[$original] = $accepted;
            }
        }

        return $out;
    }

    /**
     * Build the request body and POST it to the PG365 gateway.
     *
     * @param string[] $receivers already-formatted receiver numbers
     */
    private function dispatch(array $receivers, string $message, string $purpose): array
    {
        $body = [
            'Text'      => $message,
            // The PG365 account is only authorized for the INF message purpose,
            // so OTP messages are delivered through the same purpose.
            'Purpose'   => $purpose === 'OTP' ? 'INF' : $purpose,
            'Options'   => array_filter([
                'Originator' => $this->originator ?: null,
                // The account is not authorized for UNICODE; OTP/INF texts are plain Latin.
                'Encoding'   => 'LATIN',
            ]),
            'Receivers' => array_map(fn(string $receiver) => ['Receiver' => $receiver], $receivers),
        ];

        $response = Http::timeout(30)->withHeaders([
            'Authorization' => 'Bearer ' . $this->privateKey,
            'Content-Type'  => 'application/json',
        ])->post($this->baseUrl . '/gateway/api/sms/v1/message/send?publicKey=' . $this->publicKey, $body);

        return $response->json() ?? [];
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
