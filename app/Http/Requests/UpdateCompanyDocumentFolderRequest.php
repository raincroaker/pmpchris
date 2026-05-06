<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesCompanyDocumentAdministration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyDocumentFolderRequest extends FormRequest
{
    use AuthorizesCompanyDocumentAdministration;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->userCanAdministerCompanyDocuments();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'parent_id' => ['nullable', 'integer'],
        ];
    }
}
