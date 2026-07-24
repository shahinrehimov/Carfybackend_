<?php

namespace App\Mail;

use App\Models\Company;
use App\Services\LangService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerNewOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    private string $emailSubject;
    private string $title;
    private string $content;

    public function __construct(
        private readonly Company $company,
        private readonly int $itemCount,
    ) {
        $storeName = $this->company->getLang('title') ?: $this->company->store_code;

        $this->emailSubject = LangService::instance()
            ->setDefault('New order — @store')
            ->getLang('seller_new_order_email_subject', ['@store' => $storeName]);

        $this->title = LangService::instance()
            ->setDefault('You have a new order')
            ->getLang('seller_new_order_email_title');

        $this->content = LangService::instance()
            ->setDefault(
                'Salam,<br><br>' .
                '<strong>@store</strong> magazaniza yeni sifaris daxil oldu.<br>' .
                'Sifarisdeki mehsul sayi: <strong>@count</strong>.<br><br>' .
                'Sifarisi emal etmek ucun seller panele daxil olun.'
            )
            ->getLang('seller_new_order_email_content', [
                '@store' => $storeName,
                '@count' => (string) $this->itemCount,
            ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.seller-new-order',
            with: [
                'title'     => $this->title,
                'body'      => $this->content,
                'storeName' => $this->company->getLang('title') ?: $this->company->store_code,
            ],
        );
    }
}
