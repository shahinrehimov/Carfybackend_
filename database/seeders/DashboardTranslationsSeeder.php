<?php

namespace Database\Seeders;

use App\Enum\TranslationGroupEnum;
use App\Models\Language;
use App\Models\Translation;
use App\Services\LangService;
use Illuminate\Database\Seeder;

/**
 * Translation keys used by the admin and seller dashboards.
 *
 * Only missing keys are inserted, so re-running never overwrites a text an
 * admin has edited through the translations module.
 */
class DashboardTranslationsSeeder extends Seeder
{
    /**
     * Keys shared by both panels: [key => [az, en, ru]]
     */
    private const SHARED = [
        'dashboard'             => ['İdarə paneli', 'Dashboard', 'Панель управления'],
        'date_from'             => ['Başlanğıc tarixi', 'Date from', 'Дата с'],
        'date_to'               => ['Son tarix', 'Date to', 'Дата по'],
        'daily'                 => ['Gündəlik', 'Daily', 'Ежедневно'],
        'weekly'                => ['Həftəlik', 'Weekly', 'Еженедельно'],
        'monthly'               => ['Aylıq', 'Monthly', 'Ежемесячно'],
        'total_orders'          => ['Cəmi sifariş', 'Total orders', 'Всего заказов'],
        'total_revenue'         => ['Cəmi gəlir', 'Total revenue', 'Общий доход'],
        'quantity_sold'         => ['Satılmış say', 'Quantity sold', 'Продано штук'],
        'order_dynamics'        => ['Sifariş dinamikası', 'Order dynamics', 'Динамика заказов'],
        'top_selling_products'  => ['Ən çox satılan məhsullar', 'Top selling products', 'Самые продаваемые товары'],
        'top_viewed_products'   => ['Ən çox baxılan məhsullar', 'Most viewed products', 'Самые просматриваемые товары'],
        'order_count'           => ['Sifariş sayı', 'Order count', 'Количество заказов'],
        'revenue_azn'           => ['Gəlir (AZN)', 'Revenue (AZN)', 'Доход (AZN)'],
        'count'                 => ['Say', 'Count', 'Количество'],
        'no_data_found'         => ['Məlumat tapılmadı', 'No data found', 'Данные не найдены'],
        'pcs'                   => ['ədəd', 'pcs', 'шт'],
        'views'                 => ['baxış', 'views', 'просмотров'],
    ];

    /**
     * Admin-only keys.
     */
    private const ADMIN = [
        'platform_general_statistics' => ['Platformanın ümumi statistikası', 'General platform statistics', 'Общая статистика платформы'],
        'registrations'               => ['Qeydiyyatlar', 'Registrations', 'Регистрации'],
        'active_via_login'            => ['Giriş vasitəsilə aktiv', 'Active via login', 'Активные по входу'],
        'cancelled'                   => ['Ləğv edildi', 'Cancelled', 'Отменено'],
        'user_registrations'          => ['İstifadəçi qeydiyyatları', 'User registrations', 'Регистрации пользователей'],
        'top_selling_stores'          => ['Ən çox satan mağazalar', 'Top selling stores', 'Магазины с наибольшими продажами'],
        'cancellation_statistics'     => ['Ləğv etmə statistikası', 'Cancellation statistics', 'Статистика отмен'],
        'new_users'                   => ['Yeni istifadəçilər', 'New users', 'Новые пользователи'],

        // Commission
        'commission'                  => ['Komissiya', 'Commission', 'Комиссия'],
        'commission_income'           => ['Komissiya gəliri', 'Commission income', 'Комиссионный доход'],
        'total_commission'            => ['Cəmi komissiya', 'Total commission', 'Всего комиссии'],
        'effective_commission_rate'   => ['Faktiki komissiya faizi', 'Effective commission rate', 'Фактическая ставка комиссии'],
        'top_commission_stores'       => ['Ən çox komissiya gətirən mağazalar', 'Top stores by commission', 'Магазины с наибольшей комиссией'],
        'commission_missing_snapshot' => ['Komissiyası hesablanmamış sətir', 'Rows without a commission snapshot', 'Строк без расчёта комиссии'],
        'commission_percent'          => ['Komissiya faizi', 'Commission percent', 'Процент комиссии'],
        'commission_percent_hint'     => ['Boş buraxsanız kateqoriya, o da boşdursa platforma faizi tətbiq olunur', 'Leave empty to inherit the category, then the platform rate', 'Оставьте пустым — применится ставка категории, затем платформы'],
        'inherited'                   => ['Miras alınıb', 'Inherited', 'Наследуется'],
        'invalid_value'               => ['Yanlış dəyər', 'Invalid value', 'Неверное значение'],
    ];

    /**
     * Seller-only keys.
     */
    private const SELLERS = [
        'store_general_statistics' => ['Mağazanızın ümumi statistikası', 'General statistics of your store', 'Общая статистика вашего магазина'],
        'total_views'              => ['Cəmi baxışlar', 'Total views', 'Всего просмотров'],
        'basket_adds'              => ['Səbətə əlavələr', 'Basket adds', 'Добавления в корзину'],
        'converted_baskets'        => ['Çevrilmiş səbətlər', 'Converted baskets', 'Конвертированные корзины'],
        'view_to_basket_rate'      => ['Baxışdan səbətə keçid faizi', 'View to basket rate', 'Конверсия из просмотра в корзину'],
        'basket_to_order_rate'     => ['Səbətdən sifarişə keçid faizi', 'Basket to order rate', 'Конверсия из корзины в заказ'],
        'view_to_order_rate'       => ['Baxışdan sifarişə keçid faizi', 'View to order rate', 'Конверсия из просмотра в заказ'],
        'customer_behavior'        => ['Müştəri davranışı', 'Customer behaviour', 'Поведение покупателей'],
        'abandoned_products'       => ['Tərk edilmiş məhsullar', 'Abandoned products', 'Брошенные товары'],
        'abandonment_rate'         => ['Tərk etmə faizi', 'Abandonment rate', 'Процент брошенных'],
        'abandoned'                => ['Tərk edilmiş', 'abandoned', 'брошено'],
    ];

    public function run(): void
    {
        $languageIds = Language::query()->orderBy('id')->pluck('id', 'key')->all();

        $groups = [
            TranslationGroupEnum::ADMIN->value   => array_merge(self::SHARED, self::ADMIN),
            TranslationGroupEnum::SELLERS->value => array_merge(self::SHARED, self::SELLERS),
        ];

        $created = 0;

        foreach ($groups as $group => $keys) {
            foreach ($keys as $key => $texts) {
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
                    }
                }
            }
        }

        // Clients only refetch translations when the version changes, and the
        // per-group cache has to be rebuilt for the new keys to be served.
        foreach (array_keys($groups) as $group) {
            LangService::instance()->setTranslationsCache($group, array_keys($languageIds), false);
        }

        LangService::instance()->changeTranslationVersion();

        $this->command?->info("Dashboard translations: {$created} key(s) created.");
    }
}
