<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesWorkScheduleTemplateMutation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DestroyWorkScheduleTemplateRequest extends FormRequest
{
    use AuthorizesWorkScheduleTemplateMutation;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
