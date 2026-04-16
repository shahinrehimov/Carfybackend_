<?php

namespace App\Repositories;

use App\Models\Advertisement;
use App\Models\AdvertisementClick;
use App\Services\FileUpload;
use App\Services\GuestService;
use App\Services\UserService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdvertisementRepository
{
    // ─── Admin ───────────────────────────────────────────────────────────────

    public function load(array $validated): LengthAwarePaginator
    {
        return Advertisement::query()
            ->with(['creatable'])
            ->when($validated['search'] ?? null, function (Builder $q) use ($validated) {
                $q->where(function (Builder $q) use ($validated) {
                    $q->where('title', 'ILIKE', '%' . $validated['search'] . '%')
                        ->orWhere('key', 'ILIKE', '%' . $validated['search'] . '%');
                });
            })
            ->when($validated['platform'] ?? null, fn(Builder $q) => $q->where('platform', $validated['platform']))
            ->when(isset($validated['is_active']), fn(Builder $q) => $q->where('is_active', $validated['is_active']))
            ->when(isset($validated['is_currently_active']), function (Builder $q) use ($validated) {
                if ($validated['is_currently_active']) {
                    $q->active();
                } else {
                    $today = now()->toDateString();
                    $q->where(function (Builder $q) use ($today) {
                        $q->where('is_active', false)
                            ->orWhere('start_date', '>', $today)
                            ->orWhere('end_date', '<', $today);
                    });
                }
            })
            ->when($validated['date_from'] ?? null, fn(Builder $q) => $q->where('start_date', '>=', $validated['date_from']))
            ->when($validated['date_to'] ?? null, fn(Builder $q) => $q->where('end_date', '<=', $validated['date_to']))
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function show(Advertisement $advertisement): Advertisement
    {
        return $advertisement->load(['creatable', 'media']);
    }

    public function store(FormRequest $request): Advertisement
    {
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();
            $validated['key'] = $this->generateKey($validated['title']);

            $advertisement = Advertisement::create($validated);

            FileUpload::upload($request, 'image', 'advertisement_image', $advertisement);

            return $advertisement;
        });
    }

    public function update(Advertisement $advertisement, FormRequest $request): Advertisement
    {
        return DB::transaction(function () use ($advertisement, $request) {
            $validated = $request->validated();

            if (isset($validated['title']) && $validated['title'] !== $advertisement->title) {
                $validated['key'] = $this->generateKey($validated['title'], $advertisement->id);
            }

            $advertisement->update($validated);

            FileUpload::upload($request, 'image', 'advertisement_image', $advertisement);

            return $advertisement;
        });
    }

    public function destroy(Advertisement $advertisement): void
    {
        DB::transaction(static fn() => $advertisement->delete());
    }

    public function changeActiveStatus(Advertisement $advertisement): void
    {
        DB::transaction(static function () use ($advertisement) {
            $advertisement->is_active = !$advertisement->is_active;
            $advertisement->save();
        });
    }

    public function loadRelations(Advertisement $advertisement): Advertisement
    {
        return $advertisement->load(['creatable', 'media']);
    }

    public function loadClicks(Advertisement $advertisement, array $validated): LengthAwarePaginator
    {
        return AdvertisementClick::query()
            ->where('advertisement_id', $advertisement->id)
            ->when($validated['date_from'] ?? null, fn(Builder $q) => $q->where('created_at', '>=', $validated['date_from']))
            ->when($validated['date_to'] ?? null, fn(Builder $q) => $q->where('created_at', '<=', $validated['date_to'] . ' 23:59:59'))
            ->when($validated['user_type'] ?? null, function (Builder $q) use ($validated) {
                match ($validated['user_type']) {
                    'authenticated' => $q->whereNotNull('user_id'),
                    'guest'         => $q->whereNull('user_id')->whereNotNull('guest_identifier'),
                    'anonymous'     => $q->whereNull('user_id')->whereNull('guest_identifier'),
                    default         => null,
                };
            })
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 20);
    }

    // ─── Public (App / Site) ─────────────────────────────────────────────────

    public function getActive(string $platform): Collection
    {
        return Advertisement::query()
            ->active()
            ->forPlatform($platform)
            ->with(['media'])
            ->orderByDesc('id')
            ->get();
    }

    public function getByKey(string $key, string $platform): Advertisement
    {
        return Advertisement::query()
            ->active()
            ->forPlatform($platform)
            ->where('key', $key)
            ->with(['media'])
            ->firstOrFail();
    }

    public function findActiveByKey(string $key): ?Advertisement
    {
        return Advertisement::query()
            ->active()
            ->where('key', $key)
            ->first();
    }

    public function trackClick(Advertisement $advertisement, Request $request): void
    {
        $userId = UserService::instance()->getUser()?->id;
        $guestIdentifier = !$userId
            ? GuestService::instance()->getGuest()?->identifier
            : null;

        DB::transaction(function () use ($advertisement, $request, $userId, $guestIdentifier) {
            AdvertisementClick::create([
                'advertisement_id' => $advertisement->id,
                'user_id'          => $userId,
                'guest_identifier' => $guestIdentifier,
                'ip_address'       => $request->ip(),
                'user_agent'       => $request->userAgent(),
                'created_at'       => now(),
            ]);

            $advertisement->increment('click_count');
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function generateKey(string $title, ?int $excludeId = null): string
    {
        $base = Str::slug($title);
        $key = $base;
        $i = 1;

        while (
            Advertisement::query()
                ->where('key', $key)
                ->where('is_deleted', 'false')
                ->when($excludeId, fn(Builder $q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $key = $base . '-' . $i;
            $i++;
        }

        return $key;
    }
}
