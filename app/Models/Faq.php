<?php

namespace App\Models;

use App\Interfaces\TranslatableInterface;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use App\Traits\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Faq extends Model implements TranslatableInterface
{
    use SoftDeletes, ActionBy, ActionUser, Translatable, CascadeSoftDeletes;

    protected $fillable = [
        'sort',
        'is_active',
    ];

    protected array $translatableFields = [
        'question',
        'answer',
    ];

    protected $casts = [
        'sort'      => 'integer',
        'is_active' => 'boolean',
    ];

    protected array $cascadeDeletes = [
        'translatable',
    ];

    public function scopeActive(Builder $builder): void
    {
        $builder->where('is_active', true);
    }

    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }
}
