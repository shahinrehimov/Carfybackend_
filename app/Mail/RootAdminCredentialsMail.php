<?php

namespace App\Mail;

use App\Enum\TranslationGroupEnum;
use App\Models\Company;
use App\Services\LangService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RootAdminCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    private string $emailSubject;
    private string $title;
    private string $content;

    public function __construct(
        private readonly Company $company,
        private readonly string $username,
        private readonly string $adminEmail,
        private readonly string $password,
    ) {
        $companyName = $company->getLang('title');

        $this->emailSubject = LangService::instance()
            ->setGroup(TranslationGroupEnum::ADMIN->value)
            ->setDefault('Your seller panel credentials — @company_name')
            ->getLang('email_root_admin_credentials_subject', [
                '@company_name' => $companyName,
            ]);

        $this->title = LangService::instance()
            ->setGroup(TranslationGroupEnum::ADMIN->value)
            ->setDefault('Seller Panel — Account Credentials')
            ->getLang('email_root_admin_credentials_title');

        $this->content = LangService::instance()
            ->setGroup(TranslationGroupEnum::ADMIN->value)
            ->setDefault(
                'Hello @username,<br><br>' .
                'Your seller account has been created for <strong>@company_name</strong>.<br>' .
                'Use the credentials below to log in to the seller panel.<br><br>' .
                '<strong>Email:</strong> @email<br>' .
                '<strong>Username:</strong> @username<br>' .
                '<strong>Password:</strong> @password<br><br>' .
                '<em>Please change your password after your first login.</em>'
            )
            ->getLang('email_root_admin_credentials_content', [
                '@company_name' => $companyName,
                '@username'     => $this->username,
                '@email'        => $this->adminEmail,
                '@password'     => $this->password,
            ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    public function build(): self
    {
        return $this->markdown('emails.root-admin-credentials')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->subject($this->emailSubject)
            ->with([
                'title'       => $this->title,
                'content'     => $this->content,
                'companyName' => $this->company->getLang('title'),
            ]);
    }
}
