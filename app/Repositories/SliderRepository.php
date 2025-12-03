<?php

namespace App\Repositories;

use App\Models\Slider;
use App\Models\SliderLayer;
use App\Services\AdminService;
use App\Services\FileUpload;
use App\Services\LangService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SliderRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return Slider::query()
            ->with([
                'translatable',
                'creatable',
                'layers' => function ($query) {
                    $query->with(['translatable', 'media']);
                },
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
            ->when($validated['type'] ?? null, function (Builder $builder) use ($validated) {
                $builder->byType($validated['type']);
            })
            ->orderBy('sort')
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function list(): Collection
    {
        return Slider::query()
            ->active()
            ->with([
                'translatable',
                'layers' => function ($query) {
                    $query->active()->with(['translatable', 'media']);
                },
            ])
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get();
    }

    public function show(Slider $slider): Slider
    {
        $slider->load([
            'layers' => function ($query) {
                $query->with(['translatable', 'media']);
            },
        ]);

        return $slider;
    }

    public function loadRelations(Slider $slider): Slider
    {
        return $slider->load([
            'translatable',
            'creatable',
            'layers' => function ($query) {
                $query->with(['translatable', 'media']);
            },
        ]);
    }

    public function store(FormRequest $request): Slider
    {
        $_this = $this;

        return DB::transaction(static function () use ($_this, $request) {
            $validated = $request->validated();
            $layers = $validated['layers'] ?? [];
            unset($validated['layers']);

            $slider = Slider::createWithLangs($validated);

            // Create layers
            $_this->storeLayers($slider, $layers, $request);

            return $slider;
        });
    }

    public function update(Slider $slider, FormRequest $request): Slider
    {
        $_this = $this;

        return DB::transaction(static function () use ($_this, $slider, $request) {
            $validated = $request->validated();
            $layers = $validated['layers'] ?? [];
            unset($validated['layers']);

            $slider->updateWithLangs($validated);

            // Update layers - delete existing and recreate
            $slider->layers()->update([
                'deletable_id' => AdminService::instance()->getUser()->id,
                'deletable_type' => AdminService::instance()->getUser()->getMorphClass(),
                'is_deleted' => DB::raw("CONCAT('deleted_', id)"),
                'deleted_at' => Carbon::now(),
            ]);

            $_this->storeLayers($slider, $layers, $request);

            return $slider;
        });
    }

    public function destroy(Slider $slider): void
    {
        DB::transaction(static function () use ($slider) {
            $slider->delete();
        });
    }

    public function changeActiveStatus(Slider $slider): void
    {
        DB::transaction(function () use ($slider) {
            $slider->is_active = !$slider->is_active;
            $slider->save();
        });
    }

    public function changeSorting(Slider $slider, int $sort): void
    {
        $slider->update(['sort' => $sort]);
    }

    public function changeSortingMultiple(array $validated): void
    {
        $sliders = $validated['items'];

        $slidersCount = Slider::query()->whereIn('id', data_get($sliders, '*.id'))->count();

        if ($slidersCount !== count($sliders)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid data!')
                    ->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($sliders) {
            foreach ($sliders as $sliderData) {
                Slider::query()->where('id', $sliderData['id'])->update(['sort' => $sliderData['sort']]);
            }
        });
    }

    public function storeLayers(Slider $slider, array $layers, FormRequest $request): void
    {
        $i = 0;
        foreach ($layers as $layerData) {
            $layerData['slider_id'] = $slider->id;

            $layer = SliderLayer::createWithLangs($layerData);

            FileUpload::upload($request, "layers.{$i}.image", 'slider_layer_images', $layer);
            FileUpload::upload($request, "layers.{$i}.video", 'slider_layer_videos', $layer);

            $i++;
        }
    }

    public function getByType(string $type): Collection
    {
        return Slider::query()
            ->active()
            ->byType($type)
            ->with([
                'translatable',
                'layers' => function ($query) {
                    $query->active()->with(['translatable', 'media']);
                },
            ])
            ->orderBy('sort')
            ->limit(30)
            ->get();
    }

    public function getBySlug(string $slug): ?Slider
    {
        return Slider::query()
            ->active()
            ->where('slug', $slug)
            ->with([
                'translatable',
                'layers' => function ($query) {
                    $query->active()->with(['translatable', 'media']);
                },
            ])
            ->firstOrFail();
    }
}
