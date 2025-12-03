<?php

namespace App\Models;

use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property array $config
 */
class PaymentMethodConfig extends Model
{
    use SoftDeletes, ActionBy, ActionUser;

    protected $fillable = [
        'method_name',
        'display_name',
        'enabled',
        'priority',
        'config',
        'metadata'
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'priority' => 'integer',
        'config' => 'array',
        'metadata' => 'array'
    ];

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('priority');
    }

    public function getConfigValue(string $key, $default = null)
    {
        return data_get($this->config, $key, $default);
    }

    public function setConfigValue(string $key, $value): void
    {
        $config = $this->config ?? [];
        data_set($config, $key, $value);
        $this->config = $config;
    }
}
