<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesCompanyDocumentAdministration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexCompanyDocumentsRequest extends FormRequest
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
            'folder_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:255'],
            'sort_key' => ['nullable', 'string', 'in:name,modified'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
            'type' => ['nullable', 'string', 'in:all,folder,pdf,docx,xlsx,pptx'],
        ];
    }
}
