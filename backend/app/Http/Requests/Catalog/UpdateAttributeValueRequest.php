<?php

namespace App\Http\Requests\Catalog;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAttributeValueRequest extends FormRequest
{
    /**
     * Fields supported by the AttributeValue management API.
     *
     * @var list<string>
     */
    private const ALLOWED_FIELDS = [
        'value',
        'color_code',
        'sort_order',
        'status',
    ];

    public function authorize(): bool
    {
        // Authentication and permission checks are enforced by route middleware.
        return true;
    }

    /**
     * Validation rules for partially updating an AttributeValue.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $attribute = $this->route('attribute');

        $attributeId = $attribute instanceof Attribute
            ? $attribute->getKey()
            : null;

        $value = $this->route('value');

        $valueId = $value instanceof AttributeValue
            ? $value->getKey()
            : null;

        $colorCodeRules = [
            'sometimes',
            'nullable',
            'string',
            'max:20',
        ];

        if ($attribute instanceof Attribute && $attribute->type === 'color') {
            $colorCodeRules[] = 'regex:/^#(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/';
        }

        return [
            'value' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('attribute_values', 'value')
                    ->where('attribute_id', $attributeId)
                    ->ignore($valueId),
            ],

            'color_code' => $colorCodeRules,

            'sort_order' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
                'max:2147483647',
            ],

            'status' => [
                'sometimes',
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Reject unsupported fields, empty PATCH payloads and invalid color use.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $input = $this->all();

                foreach (
                    array_diff(
                        array_keys($input),
                        self::ALLOWED_FIELDS,
                    ) as $field
                ) {
                    $validator->errors()->add(
                        (string) $field,
                        'This field is not allowed.',
                    );
                }

                $providedFields = array_intersect(
                    array_keys($input),
                    self::ALLOWED_FIELDS,
                );

                if ($providedFields === []) {
                    $validator->errors()->add(
                        'attribute_value',
                        'At least one attribute value field must be provided.',
                    );
                }

                $attribute = $this->route('attribute');

                if (
                    $attribute instanceof Attribute
                    && $attribute->type !== 'color'
                    && $this->input('color_code') !== null
                ) {
                    $validator->errors()->add(
                        'color_code',
                        'The color_code field is only allowed for color attributes.',
                    );
                }
            },
        ];
    }
}
