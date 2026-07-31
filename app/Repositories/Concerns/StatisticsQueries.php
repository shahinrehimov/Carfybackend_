<?php

namespace App\Repositories\Concerns;

use App\Enum\OrderItemStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Models\CompanyProductVariant;

/**
 * Query fragments shared by the admin and seller dashboard statistics.
 */
trait StatisticsQueries
{
    private function pgPeriod(string $period): string
    {
        return match($period) {
            'weekly'  => 'week',
            'monthly' => 'month',
            default   => 'day',
        };
    }

    /**
     * SQL condition selecting the order item rows whose money counts as revenue.
     *
     * Cancelled items never bring money in, and neither do cancelled or returned
     * orders. When the caller already narrowed the result down to one explicit
     * order status we only drop the cancelled items, otherwise the requested
     * status would be filtered away by this condition.
     */
    private function revenueCondition(bool $statusFiltered = false): string
    {
        $cancelledItem = OrderItemStatusEnum::CANCELED->value;
        $itemCondition = "oi.status IS DISTINCT FROM '{$cancelledItem}'";

        if ($statusFiltered) {
            return $itemCondition;
        }

        $revenueStatuses = implode("', '", OrderStatusEnum::revenueValues());

        return "o.status IN ('{$revenueStatuses}') AND {$itemCondition}";
    }

    /**
     * Relations the CPV title and image cascade need.
     *
     * The variant and its product are loaded with trashed rows on purpose: an
     * order item keeps pointing at what was sold even after the catalogue entry
     * is removed, and a statistics row without a title is useless.
     */
    private function cpvDisplayRelations(): array
    {
        return [
            'media',
            'productVariant'               => fn($q) => $q->withTrashed(),
            'productVariant.media',
            'productVariant.options',
            'productVariant.options.option',
            'productVariant.options.option.translatable',
            'productVariant.options.item',
            'productVariant.options.item.translatable',
            'productVariant.product'       => fn($q) => $q->withTrashed(),
            'productVariant.product.media',
            'productVariant.product.translatable',
        ];
    }

    private function formatCpv(?CompanyProductVariant $cpv): ?array
    {
        if (!$cpv) {
            return null;
        }

        $variant = $cpv->relationLoaded('productVariant') ? $cpv->productVariant : null;

        return [
            'id'         => $cpv->id,
            'uuid'       => $cpv->uuid,
            'title'      => $variant?->title,
            'main_image' => $cpv->listing_main_image,
        ];
    }
}
