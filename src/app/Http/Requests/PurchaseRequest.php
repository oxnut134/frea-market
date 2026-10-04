<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        // 金額・送付先・メールアドレスはリクエストから受け取らず、サーバー側で決める
        return [
            'payment_method' => ['required', 'in:card,konbini'],
        ];
    }
    public function messages()
    {

        return [
            'payment_method.required' => 'お支払い方法を入力してください。',
            'payment_method.in' => 'お支払い方法を入力してください。',
        ];
    }
}
