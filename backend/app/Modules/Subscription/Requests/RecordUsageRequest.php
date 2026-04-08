<?php

namespace App\Modules\Subscription\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dimension_code' => 'required|exists:metering_dimensions,code',
            'quantity' => 'required|numeric|min:0',
            'metadata' => 'sometimes|array',
        ];
    }

    public function messages(): array
    {
        return [
            'dimension_code.required' => '计量维度不能为空',
            'dimension_code.exists' => '计量维度不存在',
            'quantity.required' => '使用量不能为空',
            'quantity.numeric' => '使用量必须是数字',
            'quantity.min' => '使用量不能为负数',
        ];
    }
}
