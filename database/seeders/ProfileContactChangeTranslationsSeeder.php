<?php

namespace Database\Seeders;

use App\Enum\TranslationGroupEnum;
use App\Models\Language;
use App\Models\Translation;
use App\Services\LangService;
use Illuminate\Database\Seeder;

/**
 * Translation keys for the OTP-confirmed email/phone change on the profile.
 *
 * Seeded into both the site and app groups because routes/app_core.php is
 * registered under the api/site and api/app prefixes, and GeneralAccessMiddleware
 * scopes lookups to the group matching the prefix.
 *
 * Only missing keys are inserted, so re-running never overwrites a text an
 * admin has edited through the translations module.
 */
class ProfileContactChangeTranslationsSeeder extends Seeder
{
    /**
     * [key => [az, en, ru]]
     */
    private const KEYS = [
        // Messages returned by the new endpoints.
        'change_email_otp_sent' => [
            'Yeni email ünvanınıza OTP kodu göndərildi. Dəyişikliyi tamamlamaq üçün kodu təsdiqləyin.',
            'An OTP has been sent to your new email address. Please verify to complete the change.',
            'Код подтверждения отправлен на ваш новый адрес электронной почты. Подтвердите его, чтобы завершить изменение.',
        ],
        'change_phone_otp_sent' => [
            'Yeni telefon nömrənizə OTP kodu göndərildi. Dəyişikliyi tamamlamaq üçün kodu təsdiqləyin.',
            'An OTP has been sent to your new phone number. Please verify to complete the change.',
            'Код подтверждения отправлен на ваш новый номер телефона. Подтвердите его, чтобы завершить изменение.',
        ],
        'email_changed_successfully' => [
            'Email ünvanınız uğurla dəyişdirildi',
            'Your email address has been changed successfully',
            'Ваш адрес электронной почты успешно изменён',
        ],
        'phone_changed_successfully' => [
            'Telefon nömrəniz uğurla dəyişdirildi',
            'Your phone number has been changed successfully',
            'Ваш номер телефона успешно изменён',
        ],
        'email_same_as_current' => [
            'Bu artıq sizin mövcud email ünvanınızdır',
            'This is already your current email address',
            'Это уже ваш текущий адрес электронной почты',
        ],
        'phone_same_as_current' => [
            'Bu artıq sizin mövcud telefon nömrənizdir',
            'This is already your current phone number',
            'Это уже ваш текущий номер телефона',
        ],
        'change_email_not_allowed_for_social_login' => [
            'Google və ya Apple ilə yaradılmış hesabın email ünvanı dəyişdirilə bilməz.',
            'The email address of an account created with Google or Apple cannot be changed.',
            'Адрес электронной почты аккаунта, созданного через Google или Apple, изменить нельзя.',
        ],

        // Returned by profile/update, which sits alongside the new endpoints.
        'profile_updated_successfully' => [
            'Profil məlumatlarınız uğurla yeniləndi',
            'Profile updated successfully',
            'Данные профиля успешно обновлены',
        ],

        // Errors the new flows can surface, so they are not auto-created in English.
        'email_already_used' => [
            'Bu email ünvanı artıq istifadə olunur',
            'Email already used',
            'Этот адрес электронной почты уже используется',
        ],
        'phone_already_used' => [
            'Bu telefon nömrəsi artıq istifadə olunur',
            'Phone already used',
            'Этот номер телефона уже используется',
        ],
        'invalid_otp' => [
            'OTP kodu yanlışdır',
            'Invalid OTP',
            'Неверный код подтверждения',
        ],
        'otp_not_found' => [
            'OTP kodu tapılmadı və ya vaxtı bitib',
            'OTP not found',
            'Код подтверждения не найден или истёк',
        ],
        'otp_already_used' => [
            'Bu OTP kodu artıq istifadə olunub',
            'OTP already used',
            'Этот код подтверждения уже использован',
        ],
        'maximum_otp_attempts_exceeded' => [
            'OTP cəhdlərinin maksimum sayı aşıldı. Yeni kod tələb edin.',
            'Maximum OTP attempts exceeded',
            'Превышено максимальное число попыток ввода кода. Запросите новый код.',
        ],
        // The @otp placeholder is substituted by LangService and must survive translation.
        'phone_otp_message' => [
            'OTP kodunuz: @otp',
            'Your OTP code: @otp',
            'Ваш код подтверждения: @otp',
        ],
        'sms_send_failed' => [
            'SMS göndərilə bilmədi. Zəhmət olmasa bir az sonra yenidən cəhd edin.',
            'SMS could not be sent. Please try again later.',
            'SMS не удалось отправить. Пожалуйста, попробуйте позже.',
        ],
    ];

    public function run(): void
    {
        $languageIds = Language::query()->orderBy('id')->pluck('id', 'key')->all();

        $groups  = [
            TranslationGroupEnum::SITE->value,
            TranslationGroupEnum::APP->value,
        ];
        $created    = 0;
        $backfilled = 0;

        foreach ($groups as $group) {
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
        }

        LangService::instance()->changeTranslationVersion();

        $this->command?->info("Profile contact change translations: {$created} row(s) created, {$backfilled} untranslated row(s) filled in.");
    }
}
