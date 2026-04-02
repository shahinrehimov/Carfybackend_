<?php

namespace App\Repositories;

use App\Models\Brand;
use App\Models\BrandModel;
use App\Models\User;
use App\Models\UserCar;
use App\Services\FileUpload;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class UserCarRepository
{
    public function load(User $user, array $validated): LengthAwarePaginator
    {
        $langId = LangService::instance()->getCurrentLangId();

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
            ->leftJoin('brand_models', 'brand_models.id', '=', 'user_cars.brand_model_id')
            ->leftJoin('brands', 'brands.id', '=', 'brand_models.brand_id')
            ->leftJoin('model_translations as mt_brand', function (JoinClause $join) use ($langId) {
                $join->on('mt_brand.translatable_id', '=', 'brands.id')
                    ->where('mt_brand.translatable_type', '=', Brand::class)
                    ->where('mt_brand.column', '=', 'title')
                    ->where('mt_brand.language_id', '=', $langId)
                    ->whereNull('mt_brand.deleted_at');
            })
            ->leftJoin('model_translations as mt_model', function (JoinClause $join) use ($langId) {
                $join->on('mt_model.translatable_id', '=', 'brand_models.id')
                    ->where('mt_model.translatable_type', '=', BrandModel::class)
                    ->where('mt_model.column', '=', 'title')
                    ->where('mt_model.language_id', '=', $langId)
                    ->whereNull('mt_model.deleted_at');
            })
            ->orderBy('mt_brand.text')
            ->orderBy('mt_model.text')
            ->select('user_cars.*')
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
