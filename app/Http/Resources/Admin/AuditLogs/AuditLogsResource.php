<?php

namespace App\Http\Resources\Admin\AuditLogs;

use App\Models\Admin;
use App\Models\CompanyAdmin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property string $method
 * @property string $url
 * @property string $url_full
 * @property string $action
 * @property string $ip
 * @property mixed $creatable_type
 * @property mixed $creatable_id
 * @property mixed $creatable
 * @property mixed $company
 * @property mixed $created_at
 */
class AuditLogsResource extends JsonResource
{
    private const ACTOR_TYPES = [
        Admin::class        => 'admin',
        CompanyAdmin::class => 'seller',
        User::class         => 'user',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'method'     => $this->method,
            'path'       => parse_url($this->url, PHP_URL_PATH),
            'query'      => $this->url_full ?: null,
            'action'     => str_replace('App\\Http\\Controllers\\', '', (string) $this->action),
            'ip'         => $this->ip,
            'actor'      => $this->creatable_type ? [
                'type' => self::ACTOR_TYPES[$this->creatable_type] ?? class_basename($this->creatable_type),
                'id'   => $this->creatable_id,
                'name' => $this->actorName(),
            ] : null,
            'company'    => $this->company ? [
                'id'    => $this->company->id,
                'title' => $this->company->title,
            ] : null,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }

    private function actorName(): ?string
    {
        $actor = $this->creatable;

        if (!$actor) {
            return null;
        }

        $fullName = trim(($actor->name ?? '') . ' ' . ($actor->surname ?? ''));

        return $fullName ?: ($actor->username ?? $actor->email ?? null);
    }
}
