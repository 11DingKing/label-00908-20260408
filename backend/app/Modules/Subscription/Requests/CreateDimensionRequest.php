<?php

namespace App\Modules\Subscription\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDimensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:100|unique:metering_dimensions,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'required|string|max:50',
            'unit_price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => '维度代码不能为空',
            'code.unique' => '维度代码已存在',
            'name.required' => '维度名称不能为空',
            'unit.required' => '单位不能为空',
            'unit_price.required' => '单价不能为空',
            'unit_price.min' => '单价不能为负数',
        ];
    }
}
