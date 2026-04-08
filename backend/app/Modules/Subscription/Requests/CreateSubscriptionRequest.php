<?php

namespace App\Modules\Subscription\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => 'required|exists:subscription_plans,id',
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.required' => '订阅计划不能为空',
            'plan_id.exists' => '订阅计划不存在',
        ];
    }
}
