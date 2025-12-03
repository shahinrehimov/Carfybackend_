<?php

namespace App\Traits;

use Illuminate\Support\Facades\Schema;

trait ActionBy
{
    public static function bootActionBy(): void
    {
        static::creating(static function ($model) {
            $model->creatable_id = auth()->user()?->id ?? null;
            $model->creatable_type = auth()->user()?->getMorphClass();
        });

        static::updating(static function ($model) {
            $model->updatable_id = auth()->user()?->id ?? null;
            $model->updatable_type = auth()->user()?->getMorphClass();
        });

        static::deleted(static function ($model) {
            $model->deletable_id = auth()->user()?->id ?? null;
            $model->deletable_type = auth()->user()?->getMorphClass();
            if (Schema::hasColumn($model->getTable(), 'is_deleted')) {
                $model->is_deleted = 'deleted_' . $model->id;
            }
            $model->save();
        });
    }
}
