<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Services\ReportService;
use Illuminate\Foundation\Http\FormRequest;

class SalesReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::OWNER) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ReportService::rules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ReportService::messages();
    }
}
