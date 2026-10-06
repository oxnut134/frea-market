<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RedirectRequest extends FormRequest
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

        return [
            // \z：$ は末尾の改行を通すため
            'post_code' => ['required', 'regex:/^\d{3}-\d{4}\z/'],
             // 郵便番号と合わせて、purchases.delivery_address（255 文字）に収まる長さにする
             'address' => ['required', 'max:100'],
             'building' => ['required', 'max:100'],
        ];
    }
    public function messages()
    {

        return [
            'post_code.required' => '郵便番号を入力してください。',
            'post_code.regex' => '郵便番号はハイフンありの８文字で入力してください。',
            'address.required' => '住所を入力してください。',
            'address.max' => '住所は100文字以内で入力してください。',
            'building.required' => '建物名を入力してください。',
            'building.max' => '建物名は100文字以内で入力してください。',
        ];
    }
}
