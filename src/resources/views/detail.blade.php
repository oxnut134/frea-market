@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="@versioned('css/detail.css')">
@endsection

@section('content')
<!--<head><meta name="csrf-token" content="{{ csrf_token() }}"></head>-->

<body>
    <div class="detail-form">
        <div class="detail-form_Item_image">
            <div class="sale-status_frame detail-form_image_frame">
                <img class="detail-form_image_attribute" src="{{ $item->image_url }}">
                @include('partials.sale_status_overlay')
            </div>

        </div>
        <div class="detail-form_detail_descriptions">
            <h1 class="detail-form_item_name">{{ $item->item_name }}</h1>
            <div class="detail-form_brand_name">{{ $item->brand_name }}</div>
            <div class="detail-form_item_price
            _wrapper">
                <span class="detail-form_item_price">￥</span><span class="detail-form_item_price">{{ $item->price }}</span><span class="detail-form_item_price_tax">（税込）</span>
            </div>
            <!-- いいね------->

            <div class="detail-form_engagement_image_box">
                @auth
                <a class="detail-form_engagement_image_wrapper js-like-button" href="#" role="button"
                    data-like-url="/like/{{ $item['id'] }}" data-liked="{{ $my_like ? 1 : 0 }}"
                    data-icon-on="{{ asset('images/liked.png') }}" data-icon-off="{{ asset('images/not-liked.png') }}">
                @else
                <a class="detail-form_engagement_image_wrapper" href="/login">
                @endauth
                    <img class="like-icon" src="{{ asset($my_like ? 'images/liked.png' : 'images/not-liked.png') }}" alt="いいね">
                    <div id="like-count">
                        <div id="count" class="detail-form_engagement_count">{{ $likes }}</div>
                    </div>
                </a>
                <script src="@versioned('js/like.js')" defer></script>
                <!-------　コメント　------------------>
                <div class="detail-form_engagement_image_wrapper">
                    <img class="detail-form_engagement_image" src="{{asset('images/comment.png')}}">
                    <div class="detail-form_engagement_count">{{ $comments }}</div>
                </div>
            </div>
            @if (session('message'))
            <div class="detail-form_message">{{ session('message') }}</div>
            @endif
            @if($item->sale_status !== \App\Models\Item::SALE_STATUS_ON_SALE)
            <div class="detail-form_sale_status">{{ $item->sale_status_label }}</div>
            @elseif(Auth::check() && Auth::id() == $item->user_id)
            <div class="detail-form_sale_status">出品中の商品です</div>
            @else
            <a class="detail-form_button_to_purchase_step" href="/purchase/{{ $item['id'] }}">購入手続きへ</a>
            @endif
            <h2>商品説明</h2>
            <p>{{ $item->description }}</p>
            <h2>商品の情報</h2>
            <div class="detail-form_Item_category_box">
                <span class="detail-form_Item_category_column_name">カテゴリー</span>
                <div class="detail-form_Item_category_wrapper">

                    @foreach($categories as $category)
                    <div class="detail-form_Item_category">{{ $category['category'] }}</div>
                    @endforeach

                </div>
            </div>
            <div class="detail-form_Item_condition_wrapper">
                <span class="detail-form_Item_condition_column_name">商品の状態</span>
                <span class="detail-form_Item_condition">{{ $item->condition }}</span>
            </div>
            @auth
            <h2>{{ 'コメント(' . $comments . ')'}}</h2>
            <div class="detail-form_user_picture_wrapper">
                <img class="detail-form_user_picture" src="{{ $profile_image_url }}" alt="プロフィール画像">
                <div class="detail-form_user_name">{{ $user_name }}</div>
            </div>
            @if(isset($first_comment['comment']) )
            <div class="detail-form_user_comment">{{ $first_comment['comment'] }}</div>
            @endif
            <form action="/item/comment" name="comment" method="post">
                @csrf
                <input type="hidden" name="item_id" value="{{ $item['id'] }}">
                <input type="hidden" name="user_id" value="{{ $item['user_id'] }}">
                <div class="detail-form_your_comment">商品へのコメント</div>
                <textarea class="detail-form_your_comment_description" name="comment" rows="6" cols="25"></textarea>
                @if ($errors->has('comment'))
                <div style="width:100%;display:flex;justify-content:flex-start;">
                    <div style="width:60%;display:flex;justify-content:flex-start;color:red;">
                        {{$errors->first('comment')}}
                    </div>
                </div>
                @endif
                <button class="detail-form_your_comment_post_button">コメントを送信する</button>
            </form>
            @endauth
        </div>
    </div>
</body>
@endsection
