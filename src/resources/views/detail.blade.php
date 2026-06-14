@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="{{ asset('css/detail.css') }}">
@endsection

@section('content')
<!--<head><meta name="csrf-token" content="{{ csrf_token() }}"></head>-->

<body>{{ $my_like }}
    <div class="detail-form">
        <div class="detail-form_Item_image">
            <img class="detail-form_image_attribute" src="{{asset( 'storage/'.$item->item_image)}}">

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
                <a class="detail-form_engagement_image_wrapper" href="" data-product-id="{{ $item['id'] }}">
                    <img class="like-icon" src="{{ asset('storage/not-liked.png') }}" alt="Like Icon"> <!-- 初期状態の画像 -->
                    <div id="like-count">
                        <div id="count" class="detail-form_engagement_count">{{ $likes }}</div>
                    </div>
                </a>
                <!--------- script ------------------>
                <style>
                    .like-icon {
                        font-size: 24px;
                        cursor: pointer;
                        width: 70%;
                    }

                    .liked {
                        /* いいねしたときのスタイル（必要に応じて追加） */
                    }

                    .not-liked {
                        /* いいねしていないときのスタイル（必要に応じて追加） */
                    }
                </style>

                <!-------- ajax による 非同期通信 速度向上版--------->
                <div id="like-data" data-my-like="{{ $my_like }}"></div>
                <script>
                    $(document).ready(function() {
                        let csrfToken;

                        // ページ読み込み時に一度だけトークンを取得
                        $.get('/sanctum/csrf-cookie').done(function() {
                            csrfToken = $('meta[name="csrf-token"]').attr('content');
                            $.ajaxSetup({
                                headers: {
                                    'X-CSRF-TOKEN': csrfToken
                                }
                            });
                        });

                        // HTMLから直接データを取得
                        var itemId = $('.detail-form_engagement_image_wrapper').data('product-id'); // data-product-idから取得
                        //現在の$my_likeカウント0 or 1を取得
                        var myLike = parseInt($('#like-data').data('my-like'), 10);
                        //console.log("myLike:", myLike); // デバッグ用

                        var initialCount = parseInt($('#count').text()); // 初期カウントをJavaScriptで取得

                        // 初期状態の設定
                        if (initialCount > 0) {
                            $('.like-icon').addClass('liked').attr('src', "{{ asset('storage/liked.png') }}"); // 初期状態を設定
                        } else {
                            $('.like-icon').addClass('not-liked').attr('src', "{{ asset('storage/not-liked.png') }}"); // 初期状態を設定
                        }

                        $('.detail-form_engagement_image_wrapper').on('click', function(e) {
                            e.preventDefault(); // デフォルトのリンク動作を防ぐ
                            let likeIcon = $(this).find('.like-icon');
                            let countElement = $('#count');
                            let currentCount = parseInt(countElement.text()); // 現在のカウントを取得

                            // アクションを決定

                            console.log("現在の myLike の値:", myLike);
                            let action = myLike === 1 ? 'remove' : 'add';

                            $.ajax({
                                url: `/like/${itemId}/${action}`, // コントローラ起動
                                method: 'GET',
                                success: function(data) {
                                    if (action === 'remove') {
                                        likeIcon.removeClass('liked').addClass('not-liked');
                                        currentCount -= 1; // カウントを減算
                                        myLike = 0; // ユーザーのいいね状態を更新
                                    } else {
                                        likeIcon.removeClass('not-liked').addClass('liked');
                                        currentCount += 1; // カウントを加算
                                        myLike = 1; // ユーザーのいいね状態を更新
                                    }

                                    // 画像を更新
                                    if (currentCount <= 0) {
                                        likeIcon.attr('src', "{{ asset('storage/not-liked.png') }}"); // いいね数が0のとき
                                    } else {
                                        likeIcon.attr('src', "{{ asset('storage/liked.png') }}"); // いいね数が1以上のとき
                                    }

                                    countElement.text(currentCount); // 現在のカウントを表示
                                },
                                error: function() {
                                    console.log(`いいね${action}に失敗しました`);
                                }
                            });
                        });
                    });
                </script>
                <!-------　コメント　------------------>
                <div class="detail-form_engagement_image_wrapper">
                    <img class="detail-form_engagement_image" src="{{asset('storage/comment.png')}}">
                    <div class="detail-form_engagement_count">{{ $comments }}</div>
                </div>
            </div>
            <a class="detail-form_button_to_purchase_step" href="/purchase/{{ $item['id'] }}">購入手続きへ</a>
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
                <img class="detail-form_user_picture" src="{{asset( 'storage/'.$profile_image)}}" alt="{{asset( 'storage/'.$item->profile_image)}}">
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
