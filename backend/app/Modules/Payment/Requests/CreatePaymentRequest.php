<?php

namespace App\Modules\Payment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bill_id' => 'required|exists:bills,id',
            'payment_method' => 'required|in:alipay,wechat,stripe,bank_transfer,credit_card',
            'payment_data' => 'sometimes|array',
        ];
    }

    public function messages(): array
    {
        return [
            'bill_id.required' => '账单ID不能为空',
            'bill_id.exists' => '账单不存在',
            'payment_method.required' => '支付方式不能为空',
            'payment_method.in' => '支付方式不正确',
        ];
    }
}
