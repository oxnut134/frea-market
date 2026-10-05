@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="@versioned('css/register.css')">
@endsection

@section('content')

<body>
    <form class="register_form" action="/register" method="post">
        @csrf
        <div class="register-form_container">
            <div class="register-form_title">
                <h2 class="register-form_register_logo">会員登録</h2>
            </div>
            @include('partials.demo_notice', ['place' => 'register'])
            <div class="register-form_input_title">
                <h3 class="register-form_input_title_logo">ユーザー名</h3>
            </div>
            <div class="register-form_input_box ">
                <input class="register-form_input_field" type="text" name="name" value="{{ old('name') }}">
            </div>
     @if ($errors->has('name'))
    <div  style="width:100%;display:flex;justify-content:center;">
         <div style="width:60%;display:flex;justify-content:flex-start;color:red;">
            {{$errors->first('name')}}
        </div>
    </div>
    @endif
           <div class="register-form_input_title">
                <h3 class="register-form_input_title_logo">メールアドレス</h3>
            </div>
            <div class="register-form_input_box ">
                <input class="register-form_input_field" type="email" name="email" value="{{ old('email') }}">
            </div>
     @if ($errors->has('email'))
    <div  style="width:100%;display:flex;justify-content:center;">
         <div style="width:60%;display:flex;justify-content:flex-start;color:red;">
            {{$errors->first('email')}}
        </div>
    </div>
    @endif
            <div class="register-form_input_title">
                <h3 class="register-form_input_title_logo">パスワード</h3>
            </div>
            <div class="register-form_input_box ">
                <input class="register-form_input_field" type="password" name="password">
            </div>
     @if ($errors->has('password'))
    <div  style="width:100%;display:flex;justify-content:center;">
         <div style="width:60%;display:flex;justify-content:flex-start;color:red;">
            {{$errors->first('password')}}
        </div>
    </div>
    @endif
            <div class="register-form_input_title">
                <h3 class="register-form_input_title_logo">確認用パスワード</h3>
            </div>
            <div class="register-form_input_box ">
                <input class="register-form_input_field" type="password" name="password_confirmation">
            </div>
     @if ($errors->has('password_confirmation'))
    <div  style="width:100%;display:flex;justify-content:center;">
         <div style="width:60%;display:flex;justify-content:flex-start;color:red;">
            {{$errors->first('password_confirmation')}}
        </div>
    </div>
    @endif
            <div class="register-form_button_box">
                <button class="register-form_redirect_button" type="submit">登録する</button>
            </div>
            <div class="login-form_link_box">
                <a class="login-form_link_to_register" href="/login">ログインはこちら</a>
            </div>
        </div>
    </form>
</body>
@endsection
