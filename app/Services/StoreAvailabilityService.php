<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyWorkingHour;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Resolves whether a store is currently open, based on the weekly schedule the
 * seller maintains in `company_working_hours`.
 *
 * A company with no schedule rows is treated as always open so sellers that
 * never filled the form keep selling.
 */
class StoreAvailabilityService
{
    private const STATUS_OPEN = 'open';
    private const STATUS_CLOSED = 'closed';

    private static ?StoreAvailabilityService $instance = null;

    /** Memoized payloads, keyed by company id. */
    private array $cache = [];

    /** Working hour rows fetched on demand, keyed by company id. */
    private array $hours = [];

    /** Minute the memo was built for; answers go stale once it rolls over. */
    private ?string $memoMinute = null;

    private function __construct()
    {
    }

    public static function instance(): StoreAvailabilityService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Availability payload for a store, safe to embed in any seller block.
     *
     * @return array{
     *     is_open: bool,
     *     status: string,
     *     today: array{is_closed: bool, open_time: ?string, close_time: ?string},
     *     closes_at: ?string,
     *     opens_at: ?string,
     *     opens_at_time: ?string,
     *     opens_at_day_of_week: ?int,
     *     message: ?string
     * }
     */
    public function for(?Company $company): array
    {
        if (!$company?->id) {
            return $this->alwaysOpenPayload();
        }

        $this->expireStaleMemo();

        if (!array_key_exists($company->id, $this->cache)) {
            $this->cache[$company->id] = $this->resolve($company);
        }

        return $this->cache[$company->id];
    }

    public function isOpen(?Company $company): bool
    {
        return $this->for($company)['is_open'];
    }

    /**
     * Human readable reason a store cannot take orders right now, or null when
     * it is open.
     */
    public function closedMessage(?Company $company): ?string
    {
        return $this->for($company)['message'];
    }

    /**
     * Warm the memo for a batch of companies with a single query, so rendering a
     * product list never falls back to one lookup per row.
     *
     * @param iterable<Company|null> $companies
     */
    public function preload(iterable $companies): void
    {
        $this->expireStaleMemo();

        $missing = [];

        foreach ($companies as $company) {
            if (!$company?->id || isset($this->cache[$company->id]) || isset($this->hours[$company->id])) {
                continue;
            }

            if ($company->relationLoaded('workingHours')) {
                $this->hours[$company->id] = collect($company->workingHours);
                continue;
            }

            $missing[$company->id] = $company->id;
        }

        if ($missing === []) {
            return;
        }

        $grouped = CompanyWorkingHour::query()
            ->whereIn('company_id', array_values($missing))
            ->get()
            ->groupBy('company_id');

        foreach ($missing as $companyId) {
            $this->hours[$companyId] = $grouped->get($companyId, collect());
        }
    }

    /**
     * Drop the memo. Called automatically once the clock leaves the minute the
     * answers were computed for, which matters in long running processes
     * (queue workers, Octane) where the instance outlives a single request.
     */
    public function flush(): void
    {
        $this->cache = [];
        $this->hours = [];
        $this->memoMinute = null;
    }

    private function expireStaleMemo(): void
    {
        $minute = Carbon::now()->format('Y-m-d H:i');

        if ($this->memoMinute !== $minute) {
            $this->flush();
            $this->memoMinute = $minute;
        }
    }

    private function resolve(Company $company): array
    {
        $hours = $this->hoursFor($company);

        if ($hours->isEmpty()) {
            return $this->alwaysOpenPayload();
        }

        $now = Carbon::now();
        $today = $hours->get($now->dayOfWeekIso);

        $todayOpen = $this->timeOn($now, $today?->open_time);
        $todayClose = $this->timeOn($now, $today?->close_time);

        // Half open interval: the store stops taking orders the moment it hits
        // its closing time.
        $isOpenNow = $today
            && !$today->is_closed
            && $todayOpen
            && $todayClose
            && $now->greaterThanOrEqualTo($todayOpen)
            && $now->lessThan($todayClose);

        $todayPayload = [
            'is_closed'  => (bool) ($today?->is_closed ?? true),
            'open_time'  => $today?->open_time?->format('H:i'),
            'close_time' => $today?->close_time?->format('H:i'),
        ];

        if ($isOpenNow) {
            return [
                'is_open'              => true,
                'status'               => self::STATUS_OPEN,
                'today'                => $todayPayload,
                'closes_at'            => $todayClose->toIso8601String(),
                'opens_at'             => null,
                'opens_at_time'        => null,
                'opens_at_day_of_week' => null,
                'message'              => null,
            ];
        }

        $next = $this->nextOpening($hours, $now);

        return [
            'is_open'              => false,
            'status'               => self::STATUS_CLOSED,
            'today'                => $todayPayload,
            'closes_at'            => null,
            'opens_at'             => $next?->toIso8601String(),
            'opens_at_time'        => $next?->format('H:i'),
            'opens_at_day_of_week' => $next?->dayOfWeekIso,
            'message'              => $this->buildMessage($company, $now, $next),
        ];
    }

    /**
     * @return Collection<int, CompanyWorkingHour> keyed by ISO day of week
     */
    private function hoursFor(Company $company): Collection
    {
        if (!isset($this->hours[$company->id])) {
            $this->hours[$company->id] = $company->relationLoaded('workingHours')
                ? collect($company->workingHours)
                : CompanyWorkingHour::query()->where('company_id', $company->id)->get();
        }

        return collect($this->hours[$company->id])->keyBy('day_of_week');
    }

    /**
     * The next moment the store opens, looked ahead one full week. Null when
     * every day is marked closed.
     */
    private function nextOpening(Collection $hours, CarbonInterface $now): ?Carbon
    {
        for ($offset = 0; $offset <= 7; $offset++) {
            $day = $now->copy()->addDays($offset);
            $row = $hours->get($day->dayOfWeekIso);

            if (!$row || $row->is_closed || !$row->open_time) {
                continue;
            }

            $opensAt = $this->timeOn($day, $row->open_time);

            if ($opensAt && $opensAt->greaterThan($now)) {
                return $opensAt;
            }
        }

        return null;
    }

    /**
     * Project a stored `H:i` time onto a given date.
     */
    private function timeOn(CarbonInterface $date, ?CarbonInterface $time): ?Carbon
    {
        if (!$time) {
            return null;
        }

        return $date->copy()->setTime((int) $time->format('H'), (int) $time->format('i'), 0);
    }

    private function buildMessage(Company $company, CarbonInterface $now, ?Carbon $next): string
    {
        $store = $company->getSellerLabel();

        if (!$next) {
            return LangService::instance()
                ->setDefault('@store is closed right now. Please try again later.')
                ->getLang('store_closed_indefinitely', ['@store' => $store]);
        }

        $replace = ['@store' => $store, '@time' => $next->format('H:i')];

        $daysAhead = (int) $now->copy()->startOfDay()->diffInDays($next->copy()->startOfDay());

        if ($daysAhead === 0) {
            return LangService::instance()
                ->setDefault('@store is closed right now. You can place your order today from @time.')
                ->getLang('store_closed_opens_today', $replace);
        }

        if ($daysAhead === 1) {
            return LangService::instance()
                ->setDefault('@store is closed right now. You can place your order tomorrow from @time.')
                ->getLang('store_closed_opens_tomorrow', $replace);
        }

        $replace['@day'] = $this->weekdayName($next->dayOfWeekIso);

        return LangService::instance()
            ->setDefault('@store is closed right now. You can place your order on @day from @time.')
            ->getLang('store_closed_opens_on_day', $replace);
    }

    private function weekdayName(int $dayOfWeekIso): string
    {
        $defaults = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];

        return LangService::instance()
            ->setDefault($defaults[$dayOfWeekIso] ?? '')
            ->getLang('weekday_' . $dayOfWeekIso);
    }

    private function alwaysOpenPayload(): array
    {
        return [
            'is_open'              => true,
            'status'               => self::STATUS_OPEN,
            'today'                => ['is_closed' => false, 'open_time' => null, 'close_time' => null],
            'closes_at'            => null,
            'opens_at'             => null,
            'opens_at_time'        => null,
            'opens_at_day_of_week' => null,
            'message'              => null,
        ];
    }
}
