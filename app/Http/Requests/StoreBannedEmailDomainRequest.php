<?php

namespace App\Http\Requests;

use App\Models\BannedEmailDomain;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBannedEmailDomainRequest extends FormRequest
{
    /**
     * Accept "@spam.example" or a full address and keep just the domain.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('domain'))) {
            $this->merge(['domain' => BannedEmailDomain::normalize($this->input('domain'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/',
                Rule::unique(BannedEmailDomain::class, 'domain'),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
            'ban_members' => ['boolean'],
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
            'domain.regex' => __('Enter a domain such as spam.example.'),
            'domain.unique' => __('That domain is already banned.'),
        ];
    }
}
