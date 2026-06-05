<?php

use App\Enum\SettingTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            ['key' => 'referral_pending_days',           'value' => '14',   'comment' => 'Days before pending earning moves to available'],
            ['key' => 'referral_withdrawal_fee_percent',  'value' => '2',    'comment' => 'Withdrawal fee percent (dynamic, admin editable)'],
            ['key' => 'referral_min_platform_spend',      'value' => '2000', 'comment' => 'Min available balance to use on platform (cents)'],
            ['key' => 'referral_min_withdrawal',          'value' => '5000', 'comment' => 'Min available balance to withdraw (cents)'],
            ['key' => 'referral_activation_threshold',    'value' => '2000', 'comment' => 'Min order amount to activate attribution (cents)'],
            ['key' => 'referral_code_min_length',         'value' => '4',    'comment' => 'Referral code minimum character length'],
            ['key' => 'referral_code_max_length',         'value' => '20',   'comment' => 'Referral code maximum character length'],
            ['key' => 'referral_activity_window_days',    'value' => '30',   'comment' => 'Sliding window days for active/passive check'],
            ['key' => 'referral_activity_min_orders',     'value' => '1',    'comment' => 'Min orders in window to stay active'],
            ['key' => 'referral_activity_min_referrals',  'value' => '2',    'comment' => 'Min new referrals in window to stay active'],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->insert([
                'type'       => SettingTypeEnum::STRING->value,
                'key'        => $setting['key'],
                'value'      => $setting['value'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'referral_pending_days',
            'referral_withdrawal_fee_percent',
            'referral_min_platform_spend',
            'referral_min_withdrawal',
            'referral_activation_threshold',
            'referral_code_min_length',
            'referral_code_max_length',
            'referral_activity_window_days',
            'referral_activity_min_orders',
            'referral_activity_min_referrals',
        ])->delete();
    }
};
