<?php

namespace App\Http\Requests;

use App\Enums\ProposalStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnalysisRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_id' => ['required', 'integer', Rule::exists('targets', 'id')->where('status', ProposalStatus::Active->value)],
            'vector_id' => ['nullable', 'integer', Rule::exists('vectors', 'id')->where('status', ProposalStatus::Active->value)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_id.exists' => 'Choose an active target.',
            'vector_id.exists' => 'Choose an active vector.',
        ];
    }
}
