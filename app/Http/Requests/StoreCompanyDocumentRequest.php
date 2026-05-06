<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesCompanyDocumentAdministration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyDocumentRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx',
            ],
            'tags' => ['nullable', 'array', 'max:8'],
            'tags.*' => ['string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
