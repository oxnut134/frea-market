@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="{{ asset('css/mypage.css') }}">
@endsection

@section('content')

    <form class="mypage-form_user_profile_box"  action="/mypage/profile" method="get">
            <div class="mypage-form_user_picture_wrapper">
                <img class="mypage-form_user_picture" src="{{ $profile->image_url }}" alt="画像がここに表示されます。">
                <div class="mypage-form_user_name">{{ $user['name'] }}</div>
            </div>
            <button class="mypage-form_edit_profile_button">
                プロフィールを編集
            </button>
    </form>
    <div class="mypage-form_mode_change_button_box">
        <a class="mypage-form_mode_exhibited_item_button" href="/mypage/?tab=sell">出品した商品</a>
        <a class="mypage-form_mode_purchased_item_button" href="/mypage/?tab=buy">購入した商品</a>
    </div>
    <div class="mypage-form_image_box" style="text-decoration:none;">
        @if(isset($items))
        @foreach($items as $item)
        <a class="mypage-form_image_wrapper" href="/item/{{ $item['id'] }}" >
            <img class="mypage-form_image_attribute" src="{{ $item->image_url }}">
            <div style="display:flex;justify-content:space-between;">
                <div style="color:#000;" >{{ $item['item_name'] }}</div>
                <div style="color:red;font-size:20px;">{{ $item['status'] }}</div>
            </div>
        </a>
        @endforeach
        @endif
    </div>

    </body>

    @endsection
