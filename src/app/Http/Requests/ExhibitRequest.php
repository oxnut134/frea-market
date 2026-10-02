<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExhibitRequest extends FormRequest
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

            'item_image' => ['required','mimes:jpeg,png,jpg','max:5120'],
             'categories' => ['required'],
             'condition' => ['required'],
             'item_name' => ['required'],
             //'brand_name' => ['required'],
             'description' => ['required','max:255'],
             'price' => ['required','integer','min:0'],
        ];
    }
    public function messages()
    {

        return [
            //'profile_image.required' => 'お名前を入力してください。',
            'item_image.required' => '画像ファイルを選択してください。',
            'item_image.mimes' => '画像ファイルはjpegかpngの型式にしてください。',
            'item_image.max' => '画像ファイルは5MB以下にしてください。',
            'item_image.uploaded' => '画像ファイルは5MB以下にしてください。',
            'categories.required' => 'カテゴリーを選択してください。',
            'condition.required' => '状態を選択してください。',
            'item_name.required' => '商品名を入力してください。',
            'description.required' => '商品の説明を入力してください。',
            'description.max' => '商品の説明は255文字以内で入力してください。',
            'price.required' => '商品価格を入力してください。',
            'price.integer' => '商品価格は0円以上で入力してください。',
            'price.min' => '商品価格は0円以上で入力してください。'
        ];
    }
}
