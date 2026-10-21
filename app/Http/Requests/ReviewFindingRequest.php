<?php

namespace App\Http\Requests;

use App\Enums\FindingStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewFindingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(FindingStatus::class)],
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
