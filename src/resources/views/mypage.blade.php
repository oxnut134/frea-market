@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="@versioned('css/mypage.css')">
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
    {{-- タブ（header.css の tab-nav）。選ばれているほうに tab-nav_item--active を付ける --}}
    <nav class="tab-nav">
        <a class="tab-nav_item{{ $tab === 'sell' ? ' tab-nav_item--active' : '' }}" href="/mypage/?tab=sell"{!! $tab === 'sell' ? ' aria-current="page"' : '' !!}>出品した商品</a>
        <a class="tab-nav_item{{ $tab === 'buy' ? ' tab-nav_item--active' : '' }}" href="/mypage/?tab=buy"{!! $tab === 'buy' ? ' aria-current="page"' : '' !!}>購入した商品</a>
    </nav>
    <div class="mypage-form_image_box" style="text-decoration:none;">
        @if(isset($items))
        @foreach($items as $item)
        <a class="mypage-form_image_wrapper" href="/item/{{ $item['id'] }}" >
            <div class="sale-status_frame">
                <img class="mypage-form_image_attribute" src="{{ $item->image_url }}">
                @include('partials.sale_status_overlay')
            </div>
            <div style="display:flex;justify-content:space-between;">
                <div style="color:#000;" >{{ $item['item_name'] }}</div>
            </div>
        </a>
        @endforeach
        @endif
        @if($items->isEmpty())
        <p class="item-list_empty">{{ $tab === 'buy' ? '購入した商品はありません。' : '出品した商品はありません。' }}</p>
        @endif
    </div>

    </body>

    @endsection
