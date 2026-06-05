<?php

namespace App\Console\Commands;

use App\Models\Referral\ReferralAttribution;
use App\Models\User;
use App\Repositories\ReferralRepository;
use Illuminate\Console\Command;

class UpdateReferralActivityStatusCommand extends Command
{
    protected $signature   = 'referral:update-activity-status';
    protected $description = 'Refresh active/passive status for all referrers based on 30-day sliding window';

    public function handle(): void
    {
        $repo = new ReferralRepository();

        // Only check users who have ever been a referrer
        $referrerIds = ReferralAttribution::distinct()->pluck('referrer_id');

        User::whereIn('id', $referrerIds)
            ->each(function (User $user) use ($repo) {
                $repo->refreshActivityStatus($user);
            });
    }
}
