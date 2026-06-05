<?php

namespace App\Console\Commands;

use App\Enum\Referral\ReferralEarningStatusEnum;
use App\Models\Referral\ReferralEarning;
use App\Repositories\ReferralRepository;
use Illuminate\Console\Command;

class ProcessPendingReferralEarningsCommand extends Command
{
    protected $signature   = 'referral:process-pending-earnings';
    protected $description = 'Move pending referral earnings to available after the pending period expires';

    public function handle(): void
    {
        $repo = new ReferralRepository();

        ReferralEarning::where('status', ReferralEarningStatusEnum::PENDING)
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->each(function (ReferralEarning $earning) use ($repo) {
                $repo->creditPendingEarning($earning);
            });
    }
}
