<?php

namespace App\Http\Requests;

use App\Enums\PropertyType;
use App\Models\SavedSearch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreSavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mirrors ListingIndexRequest, except that region must be a real branch
     * region and min_bedrooms starts at 1: zero would count as a criterion
     * while matching every listing.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'property_type' => ['nullable', new Enum(PropertyType::class)],
            'region' => ['nullable', 'string', 'exists:branches,region'],
            'min_bedrooms' => ['nullable', 'integer', 'min:1', 'max:20'],
            'max_price' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * A search with no criteria would match every new listing, so at least
     * one is required.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (collect($this->only(SavedSearch::CRITERIA))->filter(fn ($value) => filled($value))->isEmpty()) {
                    $validator->errors()->add('criteria', 'Choose at least one filter to save a search.');
                }
            },
        ];
    }
}
