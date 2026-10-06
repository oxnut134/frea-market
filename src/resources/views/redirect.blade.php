@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="@versioned('css/redirect.css')">
@endsection

@section('content')

<body>
    <form class="redirect_form" action="/purchase/address/return" method="post">
        @csrf
        <div class="redirect-form_container">
            <div class="redirect-form_title">
                <h2 class="redirect-form_redirect_logo">住所の変更</h2>
            </div>
            <div class="redirect-form_input_title">
                <h3 class="redirect-form_input_title_logo">郵便番号</h3>
            </div>
            <div class="redirect-form_input_box ">
                <input class="redirect-form_input_field" type="text" name="post_code" aria-activedescendant="" value="{{ old('post_code', $post_code) }}">
            </div>
            @if ($errors->has('post_code'))
            <div style="width:100%;display:flex;justify-content:center;">
                <div style="width:60%;display:flex;justify-content:flex-start;color:red;">
                    {{$errors->first('post_code')}}
                </div>
            </div>
            @endif
            <div class="redirect-form_input_title">
                <h3 class="redirect-form_input_title_logo">住所</h3>
            </div>
            <div class="redirect-form_input_box ">
                <input class="redirect-form_input_field" type="text" name="address" value="{{ old('address', $address) }}">
            </div>
            @if ($errors->has('address'))
            <div style="width:100%;display:flex;justify-content:center;">
                <div style="width:60%;display:flex;justify-content:flex-start;color:red;">
                    {{$errors->first('address')}}
                </div>
            </div>
            @endif
            <div class="redirect-form_input_title">
                <h3 class="redirect-form_input_title_logo">建物名</h3>
            </div>
            <div class="redirect-form_input_box ">
                <input class="redirect-form_input_field" type="text" name="building" value="{{ old('building', $building) }}">
            </div>
            @if ($errors->has('building'))
            <div style="width:100%;display:flex;justify-content:center;">
                <div style="width:60%;display:flex;justify-content:flex-start;color:red;">
                    {{$errors->first('building')}}
                </div>
            </div>
            @endif
            <div class="redirect-form_button_box">
                <input type="hidden" name="item_id" value="{{ $item['id'] }}">
                <button class="redirect-form_redirect_button">更新する</button>
            </div>
        </div>
    </form>
</body>
@endsection
