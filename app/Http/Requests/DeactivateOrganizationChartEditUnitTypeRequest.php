<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeactivateOrganizationChartEditUnitTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canEditOrganizationStructure() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
