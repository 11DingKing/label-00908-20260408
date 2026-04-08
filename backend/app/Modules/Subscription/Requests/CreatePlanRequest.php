<?php

namespace App\Modules\Subscription\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:subscription_plans,code',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,quarterly,yearly',
            'features' => 'nullable|array',
            'included_usage' => 'nullable|array',
            'usage_pricing' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '计划名称不能为空',
            'code.required' => '计划代码不能为空',
            'code.unique' => '计划代码已存在',
            'price.required' => '价格不能为空',
            'price.min' => '价格不能为负数',
            'billing_cycle.required' => '计费周期不能为空',
            'billing_cycle.in' => '计费周期必须是 monthly, quarterly 或 yearly',
        ];
    }
}
