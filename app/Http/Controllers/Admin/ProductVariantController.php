<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductVariants\ProductVariantSearchRequest;
use App\Http\Resources\GeneralResource;
use App\Http\Resources\Sellers\ProductVariants\ProductVariantResource;
use App\Http\Resources\Sellers\ProductVariants\ProductVariantsListResource;
use App\Http\Resources\Sellers\ProductVariants\ProductVariantsResource;
use App\Models\ProductVariant;
use App\Models\ProductVariantRevision;
use App\Repositories\ProductVariantRepository;
use App\Services\FitmentPresenter;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductVariantController extends Controller
{
    private ProductVariantRepository $repo;

    public function __construct(ProductVariantRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(ProductVariantSearchRequest $request): AnonymousResourceCollection
    {
        return ProductVariantsResource::collection($this->repo->load($request->validated(), true));
    }

    public function list(ProductVariantSearchRequest $request): AnonymousResourceCollection
    {
        return ProductVariantsListResource::collection($this->repo->list($request->validated()));
    }

    public function show(ProductVariant $productVariant): ProductVariantResource
    {
        $productVariant = $this->repo->show($productVariant);

        return ProductVariantResource::make($productVariant);
    }

    public function destroy(ProductVariant $productVariant): JsonResponse
    {
        $this->repo->destroy($productVariant);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('product_deleted_successfully'),
        ]));
    }

    public function deleteHoverImage(ProductVariant $productVariant): JsonResponse
    {
        $this->repo->deleteHoverImage($productVariant);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Image deleted successfully!')
                ->getLang('product_image_deleted_successfully'),
        ]));
    }

    public function destroyImage(ProductVariant $productVariant, int $mediaId): JsonResponse
    {
        $this->repo->deleteImage($productVariant, $mediaId);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Selected media file deleted successfully!')
                ->getLang('product_media_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(ProductVariant $productVariant): JsonResponse
    {
        $this->repo->changeActiveStatus($productVariant);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function revisions(ProductVariant $productVariant): JsonResponse
    {
        $revisions = $productVariant->revisions()
            ->with(['productVariant', 'productVariant.options', 'productVariant.options.option', 'productVariant.fitments.brand.translatable', 'productVariant.fitments.brandModel.translatable', 'media', 'company'])
            ->latest()
            ->get()
            ->map(fn(ProductVariantRevision $r) => $this->formatRevision($r));

        return response()->json(GeneralResource::make(['data' => $revisions]));
    }

    public function approveRevision(ProductVariantRevision $revision): JsonResponse
    {
        $this->repo->approveRevision($revision);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Revision approved successfully!')
                ->getLang('revision_approved_successfully'),
        ]));
    }

    public function rejectRevision(ProductVariantRevision $revision, Request $request): JsonResponse
    {
        $this->repo->rejectRevision($revision, $request->input('message'));

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Revision rejected!')
                ->getLang('revision_rejected_successfully'),
        ]));
    }

    private function formatRevision(ProductVariantRevision $revision): array
    {
        $pv = $revision->productVariant;

        return [
            'id'                 => $revision->id,
            'status'             => $revision->status,
            'rejection_message'  => $revision->rejection_message,
            'company'            => $revision->company ? ['id' => $revision->company->id, 'title' => $revision->company->getLang('title')] : null,
            // Fitment ids inside the raw payload are resolved to titles so the
            // diff is readable; the rest of the payload is passed through as-is.
            'data'               => array_merge($revision->data, [
                'fitments' => FitmentPresenter::instance()->forRawRows($revision->data['fitments'] ?? []),
            ]),
            'current'            => [
                'oem_code'    => $pv->oem_code,
                'fitments'    => FitmentPresenter::instance()->forVariant($pv),
                'is_universal' => $pv->is_universal,
                'requires_fitment_selection' => $pv->requires_fitment_selection,
                'options'        => $pv->options->map(fn($o) => [
                    'option_id' => $o->product_option_id,
                    'value'     => $o->product_option_item_id ?? $o->value,
                ]),
            ],
            'images'             => [
                'main_image'  => $revision->getWebImageUrl('revision_main_image'),
                'hover_image' => $revision->getWebImageUrl('revision_hover_image'),
                'images'      => $revision->getMedia('revision_images')->map(fn($m) => ['id' => $m->id, 'url' => $revision::webUrlForMedia($m)])->values(),
            ],
            'created_date'       => $revision->created_at?->toDateTimeString(),
        ];
    }
}
