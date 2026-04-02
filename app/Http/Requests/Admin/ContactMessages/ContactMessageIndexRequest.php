<?php

namespace App\Http\Requests\Admin\ContactMessages;

use App\Http\Requests\GeneralListRequest;

class ContactMessageIndexRequest extends GeneralListRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'is_read' => ['filled', 'boolean'],
        ]);
    }
}
