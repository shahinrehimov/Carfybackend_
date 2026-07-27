<?php

namespace App\Repositories;

use App\Enum\ProductVariantApprovalStatusEnum;
use App\Models\CompanyProductVariant;
use App\Models\Group;
use App\Models\ProductVariant;
use App\Services\FileUpload;
use App\Services\LangService;
use App\Services\ProductService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class GroupRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return Group::query()
            ->with([
                'translatable',
                'creatable',
                'media',
            ])
            ->withCount([
                'productVariants',
            ])
            ->when($validated['search'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where(function (Builder $builder) use ($validated) {
                    $builder->whereHas('translatable', function (Builder $query) use ($validated) {
                        $query->where(function (Builder $q) use ($validated) {
                            $q->where('column', 'title');
                            $q->where('text', 'ILIKE', '%' . $validated['search'] . '%');
                        });
                        $query->orWhere(function (Builder $q) use ($validated) {
                            $q->where('column', 'description');
                            $q->where('text', 'ILIKE', '%' . $validated['search'] . '%');
                        });
                    });
                });
            })
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function list(): Collection
    {
        return Group::query()
            ->active()
            ->with([
                'translatable',
            ])
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get();
    }

    public function show(Group $group): Group
    {
        $group->load([
            'media',
        ]);

        return $group;
    }

    public function loadRelations(Group $group): Group
    {
        return $group
            ->load([
                'translatable',
                'creatable',
                'media',
            ])
            ->loadCount([
                'productVariants',
            ]);
    }

    public function store(FormRequest $request): Group
    {
        $_this = $this;

        return DB::transaction(static function () use ($_this, $request) {
            $validated = $request->validated();

            $defaultTitle = Group::getDefaultText($validated['translations'] ?? []);

            if ($defaultTitle) {
                $validated['name'] = $_this->generateName($defaultTitle);
            }

            $validated['uuid'] = Str::uuid();

            $group = Group::createWithLangs($validated);

            FileUpload::upload($request, 'image', 'groups', $group);

            return $group;
        });
    }

    public function update(Group $group, FormRequest $request): Group
    {
        return DB::transaction(static function () use ($group, $request) {
            $validated = $request->validated();

            $validated['max_count'] = $validated['max_count'] ?? null;

            $group->updateWithLangs($validated);

            FileUpload::upload($request, 'image', 'groups', $group);

            return $group;
        });
    }

    public function destroy(Group $group): void
    {
        DB::transaction(static function () use ($group) {
            $group->delete();
        });
    }

    public function deleteImage(Group $group): void
    {
        DB::transaction(static function () use ($group) {
            $group->clearMediaCollection('groups');
        });
    }

    public function changeActiveStatus(Group $group): void
    {
        DB::transaction(function () use ($group) {
            $group->is_active = !$group->is_active;
            $group->save();
        });
    }

    public function changeSorting(Group $group, int $sort): void
    {
        $group->update([
            'sort' => $sort,
        ]);
    }

    public function changeSortingMultiple(array $validated): void
    {
        $groups = $validated['items'];

        $groupsCount = Group::query()->whereIn('id', data_get($groups, '*.id'))->count();

        if ($groupsCount !== count($groups)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid data!')
                    ->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($groups) {
            foreach ($groups as $groupData) {
                Group::query()->where('id', $groupData['id'])->update(['sort' => $groupData['sort']]);
            }
        });
    }

    private function generateName(string $title): string
    {
        $name = Str::snake($title, '');

        while (Group::query()->where('name', $name)->exists()) {
            $name = Str::snake($title . Str::random(5), '');
        }

        return $name;
    }

    public function addProduct(Group $group, ProductVariant $productVariant): void
    {
        DB::transaction(function () use ($group, $productVariant) {
            if ($group->productVariants()->where('product_variant_id', $productVariant->id)->exists()) {
                throw new BadRequestHttpException(
                    LangService::instance()
                        ->setDefault('Product variant already exists in this group!')
                        ->getLang('group_product_product_variant_already_exists_in_group')
                );
            }

            $group->productVariants()->attach($productVariant->id, [
                'product_id' => $productVariant->product_id,
            ]);
        });
    }

    public function removeProduct(Group $group, ProductVariant $productVariant): void
    {
        DB::transaction(function () use ($group, $productVariant) {
            if (!$group->productVariants()->where('product_variant_id', $productVariant->id)->exists()) {
                throw new BadRequestHttpException(
                    LangService::instance()
                        ->setDefault('Product variant does not exist in this group!')
                        ->getLang('group_product_variant_product_does_not_exist_in_group')
                );
            }

            $group->productVariants()->detach($productVariant->id);
        });
    }

    public function addBulkProducts(Group $group, array $productVariantIds): void
    {
        DB::transaction(function () use ($group, $productVariantIds) {
            $existingProductVariantIds = $group->productVariants()->pluck('product_variant_id')->all();

            $newProductVariantIds = array_diff($productVariantIds, $existingProductVariantIds);

            if (empty($newProductVariantIds)) {
                throw new BadRequestHttpException(
                    LangService::instance()
                        ->setDefault('No new product variants to add!')
                        ->getLang('group_product_no_new_product_variants_to_add')
                );
            }

            $newProductVariants = ProductVariant::query()
                ->whereIn('id', $newProductVariantIds)
                ->get(['id', 'product_id']);

            if ($newProductVariants->isEmpty()) {
                throw new BadRequestHttpException(
                    LangService::instance()
                        ->setDefault('Product variants not found!')
                        ->getLang('group_product_variants_not_found')
                );
            }

            $pivotData = $newProductVariants
                ->mapWithKeys(fn ($variant) => [$variant->id => ['product_id' => $variant->product_id]])
                ->all();

            $group->productVariants()->attach($pivotData);
        });
    }

    public function removeBulkProducts(Group $group, array $productVariantIds): void
    {
        DB::transaction(function () use ($group, $productVariantIds) {
            $existingProductVariantIds = $group->productVariants()->pluck('product_variant_id')->all();

            $removableProductVariantIds = array_intersect($productVariantIds, $existingProductVariantIds);

            if (empty($removableProductVariantIds)) {
                throw new BadRequestHttpException(
                    LangService::instance()
                        ->setDefault('No product variant to remove!')
                        ->getLang('group_product_no_product_variants_to_remove')
                );
            }

            $group->productVariants()->detach($removableProductVariantIds);
        });
    }

    public function getByName(string $name, array $relations = []): ?Group
    {
        return Group::query()
            ->where('name', $name)
            ->active()
            ->when(count($relations) > 0, function ($query) use ($relations) {
                $query->with($relations);
            })
            ->first();
    }

    public function getByNames(array $names, array $relations = []): Collection
    {
        return Group::query()
            ->whereIn('name', $names)
            ->active()
            ->when(count($relations) > 0, function ($query) use ($relations) {
                $query->with($relations);
            })
            ->get();
    }

    public function loadGroupWithProducts(string $groupName, array $validated = []): Group
    {
        $group = (new GroupRepository())->getByName($groupName, [
            'translatable',
            'media',
        ]);

        if (!$group) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Group not found: @name')
                    ->getLang('group_not_found_by_name', ['@name' => $groupName])
            );
        }

        $group->products_data = $this->loadCpvsForGroup($group->id, $validated['limit'] ?? 10);

        return $group;
    }

    public function loadGroupsWithProducts(array $validated = []): Collection
    {
        $groups = $this->getByNames(data_get($validated['groups'], '*.name', []), [
            'translatable',
            'media',
        ]);

        if (count($groups) !== count($validated['groups'])) {
            $missingGroups = array_diff(data_get($validated['groups'], '*.name', []), $groups->pluck('name')->toArray());

            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Groups not found: @names')
                    ->getLang('group_not_found_by_names', ['@names' => implode(', ', $missingGroups)])
            );
        }

        foreach ($validated['groups'] as $groupData) {
            if (!isset($groupData['name']) || !isset($groupData['limit'])) {
                throw new BadRequestHttpException(
                    LangService::instance()
                        ->setDefault('Group name and limit are required')
                        ->getLang('group_name_and_limit_required')
                );
            }

            /** @var Group $group */
            $group = $groups->firstWhere('name', $groupData['name']);

            if (!$group) {
                throw new BadRequestHttpException(
                    LangService::instance()
                        ->setDefault('Group not found: @name')
                        ->getLang('group_not_found_by_name', ['@name' => $groupData['name']])
                );
            }

            $group->products_data = $this->loadCpvsForGroup($group->id, $groupData['limit']);
        }

        return $groups;
    }

    private function loadCpvsForGroup(int $groupId, int $limit): Collection
    {
        $cpvs = CompanyProductVariant::query()
            ->where('company_product_variant.is_active', true)
            ->where('company_product_variant.approval_status', ProductVariantApprovalStatusEnum::APPROVED->value)
            ->whereHas('productVariant', fn(Builder $q) => $q
                ->active()
                ->whereHas('product', fn(Builder $pq) => $pq->active())
                ->whereHas('groupsRel', fn(Builder $gq) => $gq->where('group_products.group_id', $groupId))
            )
            ->with([
                'media',
                'city',
                'city.translatable',
                'company',
                'productVariant',
                'productVariant.media',
                'productVariant.product',
                'productVariant.product.translatable',
                'productVariant.product.media',
                'productVariant.product.mainCategory',
                'productVariant.product.mainCategory.translatable',
                'productVariant.product.mainCategory.parent',
                'productVariant.product.mainCategory.parent.translatable',
                'productVariant.options',
                'productVariant.options.option',
                'productVariant.options.option.translatable',
                'productVariant.options.item',
                'productVariant.options.item.translatable',
            ])
            ->limit($limit)
            ->inRandomOrder()
            ->get();

        $productVariantRepository = new ProductVariantRepository();
        $productVariantRepository->attachWishlistAndBasketToCpvsCollection($cpvs);
        $productVariantRepository->attachRatingsToCpvsCollection($cpvs);

        return $cpvs;
    }
}
