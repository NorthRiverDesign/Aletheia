<?php

namespace App\Http\Requests;

use App\Models\ReferenceSource;
use App\Services\Analysis\ReferenceLink;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReferenceSourceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_id' => ['nullable', 'integer', 'exists:targets,id'],
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:http,https', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $duplicate = ReferenceSource::query()
                    ->where('url_key', ReferenceLink::key($this->string('url')->toString()))
                    ->where(fn ($query) => $query
                        ->whereNull('target_id')
                        ->when($this->filled('target_id'), fn ($query) => $query->orWhere('target_id', $this->integer('target_id'))))
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('url', __('That link is already in the library.'));
                }
            },
        ];
    }
}
