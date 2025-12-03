<?php

namespace App\Repositories;

use App\Models\Address;
use App\Models\User;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AddressRepository
{
    public function load(User $user, array $validated): LengthAwarePaginator
    {
        return $user->addresses()
            ->with([
                'addressType',
                'addressType.translatable',
                'city',
                'city.translatable',
                'city.country',
                'city.country.translatable',
            ])
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function list(User $user): Collection
    {
        return $user->addresses()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }

    private function checkUser(User $user, Address $address): void
    {
        if ($address->user_id !== $user->id) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid data!')
                    ->getLang('admin_invalid_data')
            );
        }
    }

    public function show(User $user, Address $address): Address
    {
        $this->checkUser($user, $address);

        $this->loadRelations($address);

        return $address;
    }

    public function loadRelations(Address $address): Address
    {
        return $address
            ->load([
                'addressType',
                'addressType.translatable',
                'city',
                'city.translatable',
                'city.country',
                'city.country.translatable',
            ]);
    }

    public function store(User $user, FormRequest $request): Address
    {
        return DB::transaction(static function () use ($user, $request) {
            $validated = $request->validated();

            if (!$user->addresses()->where('is_default', true)->exists()) {
                $validated['is_default'] = true;
            }

            return $user->addresses()->create($validated);
        });
    }

    public function update(User $user, Address $address, FormRequest $request): Address
    {
        $this->checkUser($user, $address);

        return DB::transaction(static function () use ($address, $request) {
            $address->update($request->validated());

            return $address;
        });
    }

    public function destroy(User $user, Address $address): void
    {
        $this->checkUser($user, $address);

        DB::transaction(static function () use ($user, $address) {
            $isDefault = $address->isDefault();

            $address->delete();

            if ($isDefault) {
                $nextAddress = $user->addresses()->orderBy('id')->first();

                if ($nextAddress) {
                    $nextAddress->is_default = true;
                    $nextAddress->save();
                }
            }
        });
    }

    public function setDefault(User $user, Address $address): void
    {
        $this->checkUser($user, $address);

        if ($address->isDefault()) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('This address is already set as default!')
                    ->getLang('user_address_already_default')
            );
        }

        DB::transaction(function () use ($user, $address) {
            $user->addresses()->where('is_default', true)->update(['is_default' => false]);

            $address->is_default = true;
            $address->save();
        });
    }
}
