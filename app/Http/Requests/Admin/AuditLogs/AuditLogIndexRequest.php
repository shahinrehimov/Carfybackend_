<?php

namespace App\Http\Requests\Admin\AuditLogs;

use App\Http\Requests\GeneralListRequest;

class AuditLogIndexRequest extends GeneralListRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'actor_type'    => ['filled', 'string', 'in:admin,seller,user,guest'],
            'actor_id'      => ['filled', 'integer', 'min:1'],
            'company_id'    => ['filled', 'integer', 'min:1'],
            'method'        => ['filled', 'string', 'in:GET,POST,PUT,PATCH,DELETE'],
            'include_reads' => ['filled', 'boolean'],
            'ip'            => ['filled', 'ip'],
            'date_from'     => ['filled', 'date'],
            'date_to'       => ['filled', 'date', 'after_or_equal:date_from'],
        ]);
    }
}
