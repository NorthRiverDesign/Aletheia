<?php

namespace App\Http\Requests;

use App\Enums\VectorCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VectorRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(VectorCategory::class)],
            'summary' => ['required', 'string', 'max:500'],
            'description' => ['required', 'string', 'max:10000'],
            'indicators' => ['required', 'array', 'min:1', 'max:25'],
            'indicators.*' => ['string', 'max:500'],
            'data_sources' => ['present', 'array', 'max:25'],
            'data_sources.*' => ['string', 'max:500'],
        ];
    }

    /**
     * The form submits lists as one item per line.
     */
    protected function prepareForValidation(): void
    {
        foreach (['indicators', 'data_sources'] as $field) {
            $value = $this->input($field);

            if (is_string($value) || $value === null) {
                $this->merge([$field => self::lines($value)]);
            }
        }
    }

    /**
     * @return list<string>
     */
    public static function lines(?string $value): array
    {
        $lines = preg_split('/\R/', (string) $value) ?: [];

        return array_values(array_filter(
            array_map(fn (string $line): string => trim((string) preg_replace('/^\s*[-*•]\s+/u', '', $line)), $lines),
            fn (string $line): bool => $line !== '',
        ));
    }
}
