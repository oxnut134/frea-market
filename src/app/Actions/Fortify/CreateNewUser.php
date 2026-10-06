<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                // email ルールは引用符内の改行などを通すため、制御文字を別に弾く
                'not_regex:/[\x00-\x1F\x7F]/',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);

        // デモ環境ではメールを送らないので、登録と同時に認証済みにする。
        // 認証メールは未認証のユーザーにだけ送られるので、これで作られなくなる
        if (config('demo.enabled')) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }
}
