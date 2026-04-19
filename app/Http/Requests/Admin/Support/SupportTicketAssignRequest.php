<?php

namespace App\Http\Requests\Admin\Support;

use App\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupportTicketAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'admin_id' => ['required', 'integer', Rule::exists(Admin::class, 'id')],
        ];
    }
}
