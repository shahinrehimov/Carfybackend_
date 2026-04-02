<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\UserCar;
use App\Services\FileUpload;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class UserCarRepository
{
    public function load(User $user, array $validated): LengthAwarePaginator
    {
        return $user->userCars()
            ->with([
                'brandModel.brand.translatable',
                'brandModel.translatable',
                'carType.translatable',
                'engineVolume.translatable',
                'fuelType.translatable',
                'transmissionType.translatable',
                'carColor.translatable',
                'media',
            ])
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function show(User $user, UserCar $userCar): UserCar
    {
        $this->checkOwnership($user, $userCar);

        return $userCar->load([
            'brandModel.brand.translatable',
            'brandModel.translatable',
            'carType.translatable',
            'engineVolume.translatable',
            'fuelType.translatable',
            'transmissionType.translatable',
            'carColor.translatable',
            'media',
        ]);
    }

    public function store(User $user, FormRequest $request): UserCar
    {
        return DB::transaction(function () use ($user, $request) {
            /** @var UserCar $userCar */
            $userCar = $user->userCars()->create($request->validated());

            FileUpload::upload($request, 'photo', 'user_car_photos', $userCar);

            return $this->show($user, $userCar);
        });
    }

    public function update(User $user, UserCar $userCar, FormRequest $request): UserCar
    {
        $this->checkOwnership($user, $userCar);

        return DB::transaction(function () use ($user, $userCar, $request) {
            $userCar->update($request->validated());

            FileUpload::upload($request, 'photo', 'user_car_photos', $userCar);

            return $this->show($user, $userCar);
        });
    }

    public function destroy(User $user, UserCar $userCar): void
    {
        $this->checkOwnership($user, $userCar);

        DB::transaction(static function () use ($userCar) {
            $userCar->delete();
        });
    }

    public function deletePhoto(User $user, UserCar $userCar): void
    {
        $this->checkOwnership($user, $userCar);

        $userCar->clearMediaCollection('user_car_photos');
    }

    public function checkOwnership(User $user, UserCar $userCar): void
    {
        if ($userCar->user_id !== $user->id) {
            throw new AccessDeniedHttpException(
                LangService::instance()->setDefault('Car access denied!')->getLang('car_access_denied')
            );
        }
    }
}
