<?php

return [
    'required' => ':attributeを入力してください。',
    'email' => ':attributeは有効なメールアドレス形式で入力してください。',
    //'min' =>  ':attribute は :min 文字以上で入力してください。',
    'min' => [
        'string' => ':attributeは:min文字以上で入力してください。',
    ],
    'confirmed' => 'パスワードと一致しません。',
    'unique' => 'この:attributeは既に登録されています。',
//'confirmed' => ':attribute が確認欄と一致しません。',

    'custom' => [
        'email' => [
            'not_regex' => ':attributeは有効なメールアドレス形式で入力してください。',
        ],
    ],

    // カスタム属性名
    'attributes' => [
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'password_confirmation' => '確認用パスワード',
        'name' => '名前',
    ],
];
