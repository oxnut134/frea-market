@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="@versioned('css/login.css')">
@endsection

@section('content')

<body>
    <form class="login_form"  action="/login" method="post">
        @csrf
        <div class="login-form_container">
            <div class="login-form_title">
                <h2 class="login-form_login_logo">ログイン</h2>
            </div>
            <div class="login-form_input_title">
                <h3 class="login-form_input_title_logo">メールアドレス</h3>
            </div>

            <div class="login-form_input_box ">
                <input class="login-form_input_field" type="email" name="email" value="{{ old('email') }}" />
            </div>
    @if ($errors->has('email'))
    <div  style="width:100%;display:flex;justify-content:center;">
         <div style="width:60%;display:flex;justify-content:flex-start;color:red;">
            {{$errors->first('email')}}
        </div>
    </div>
    @endif
            <div class="login-form_input_title">
                <h3 class="login-form_input_title_logo">パスワード</h3>
            </div>
            <div class="login-form_input_box ">
                <input class="login-form_input_field" type="password" name="password" />
            </div>
   @if ($errors->has('password'))
    <div  style="width:100%;display:flex;justify-content:center;">
         <div style="width:60%;display:flex;justify-content:flex-start;color:red;">
            {{$errors->first('password')}}
        </div>
    </div>
    @endif
                <div class="login-form_button_box">
                <button class="login-form_login_button">ログインする</button>
            </div>
            <div class="login-form_link_box">
                <a class="login-form_link_to_register" href="/register">会員登録はこちら</a>
            </div>
        </div>
    </form>
</body>
@endsection
