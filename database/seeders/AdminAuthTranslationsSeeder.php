<?php

namespace Database\Seeders;

use App\Enum\TranslationGroupEnum;
use App\Models\Language;
use App\Models\Translation;
use App\Services\LangService;
use Illuminate\Database\Seeder;

/**
 * Translation keys for the control panel login / forgot password / reset password screens.
 *
 * Seeded into the admin group only, because routes/control.php runs behind
 * general_access:admin and GeneralAccessMiddleware scopes lookups to that group.
 *
 * Only missing keys are inserted, so re-running never overwrites a text an
 * admin has edited through the translations module.
 */
class AdminAuthTranslationsSeeder extends Seeder
{
    /**
     * [key => [az, en, ru]]
     */
    private const KEYS = [
        // Shared auth form labels used by the sign in screen.
        'login' => [
            'Giriş',
            'Login',
            'Вход',
        ],
        'username' => [
            'İstifadəçi adı',
            'Username',
            'Имя пользователя',
        ],
        'email' => [
            'Email',
            'Email',
            'Электронная почта',
        ],
        'password' => [
            'Şifrə',
            'Password',
            'Пароль',
        ],
        'password_confirmation' => [
            'Şifrəni təsdiqlə',
            'Confirm password',
            'Подтвердите пароль',
        ],
        'send' => [
            'Göndər',
            'Send',
            'Отправить',
        ],

        // Forgot password screen.
        'forgot_password' => [
            'Şifrəni unutmuşam',
            'Forgot password',
            'Забыли пароль',
        ],
        'forgot_password_question' => [
            'Şifrəni unutmusan?',
            'Forgot password?',
            'Забыли пароль?',
        ],
        'forgot_password_description' => [
            'Email ünvanınızı daxil edin, şifrə bərpası linkini sizə göndərək.',
            'Enter your email address and we will send you a password reset link.',
            'Введите адрес электронной почты, и мы отправим вам ссылку для сброса пароля.',
        ],
        'back_to_login' => [
            'Girişə qayıt',
            'Back to login',
            'Вернуться ко входу',
        ],

        // Reset password screen.
        'reset_password' => [
            'Şifrəni yenilə',
            'Reset password',
            'Сброс пароля',
        ],
        'invalid_reset_link' => [
            'Link yanlışdır və ya vaxtı bitib. Zəhmət olmasa yeni şifrə bərpası tələb edin.',
            'Invalid or expired reset link. Please request a new password reset.',
            'Ссылка недействительна или истекла. Пожалуйста, запросите сброс пароля заново.',
        ],

        // Client side form validation.
        'required_field' => [
            'Bu sahə mütləqdir',
            'This field is required',
            'Это поле обязательно',
        ],
        'invalid_email' => [
            'Email ünvanı yanlışdır',
            'Invalid email address',
            'Неверный адрес электронной почты',
        ],
        'password_min_length' => [
            'Şifrə ən azı 8 simvol olmalıdır',
            'Password must be at least 8 characters',
            'Пароль должен содержать не менее 8 символов',
        ],
        'passwords_must_match' => [
            'Şifrələr uyğun gəlmir',
            'Passwords must match',
            'Пароли должны совпадать',
        ],

        // Messages returned by the forgot / reset password endpoints.
        'forgot_password_sent_reset_link' => [
            'Şifrə bərpası linki email ünvanınıza göndərildi',
            'Password reset link has been sent to your email',
            'Ссылка для сброса пароля отправлена на вашу электронную почту',
        ],
        'forgot_password_not_sent_reset_link' => [
            'Bərpa linki göndərilə bilmədi',
            'Unable to send reset link',
            'Не удалось отправить ссылку для сброса пароля',
        ],
        'reset_password_password_reset_success' => [
            'Şifrəniz uğurla yeniləndi',
            'Password has been reset successfully',
            'Пароль успешно изменён',
        ],
        'reset_password_invalid_token' => [
            'Token yanlışdır və ya vaxtı bitib',
            'Invalid token or expired',
            'Токен недействителен или истёк',
        ],
    ];

    public function run(): void
    {
        $languageIds = Language::query()->orderBy('id')->pluck('id', 'key')->all();

        $group      = TranslationGroupEnum::ADMIN->value;
        $created    = 0;
        $backfilled = 0;

        foreach (self::KEYS as $key => $texts) {
            foreach (['az', 'en', 'ru'] as $index => $langKey) {
                if (!isset($languageIds[$langKey])) {
                    continue;
                }

                $translation = Translation::query()->firstOrCreate(
                    [
                        'group'       => $group,
                        'language_id' => $languageIds[$langKey],
                        'key'         => $key,
                    ],
                    [
                        'text' => $texts[$index],
                    ]
                );

                if ($translation->wasRecentlyCreated) {
                    $created++;

                    continue;
                }

                // Keys auto-created by LangService::setDefaultTranslation() carry the
                // English default in every language. Text still identical to the English
                // default was never translated by anyone, so it is safe to fill in;
                // anything an admin has actually edited differs and is left alone.
                if ($langKey !== 'en' && $translation->text === $texts[1] && $texts[$index] !== $texts[1]) {
                    $translation->update(['text' => $texts[$index]]);

                    $backfilled++;
                }
            }
        }

        // Clients only refetch translations when the version changes, and the
        // per-group cache has to be rebuilt for the new keys to be served.
        LangService::instance()->setTranslationsCache($group, array_keys($languageIds), false);

        LangService::instance()->changeTranslationVersion();

        $this->command?->info("Admin auth translations: {$created} row(s) created, {$backfilled} untranslated row(s) filled in.");
    }
}
