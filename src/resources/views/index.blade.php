@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="@versioned('css/index.css')">
@endsection

@section('content')

<body>

    @auth
    {{-- タブ（header.css の tab-nav）。?tab=mylist ならマイリスト、それ以外（検索結果も）はおすすめが選ばれている --}}
    @php($mylist = request()->query('tab') === 'mylist')
    <nav class="tab-nav">
        <a class="tab-nav_item{{ $mylist ? '' : ' tab-nav_item--active' }}" href="/"{!! $mylist ? '' : ' aria-current="page"' !!}>おすすめ</a>
        @if(isset($keyword))
        <a class="tab-nav_item{{ $mylist ? ' tab-nav_item--active' : '' }}" href="/?tab=mylist&&keyword={{ $keyword }}"{!! $mylist ? ' aria-current="page"' : '' !!}>マイリスト</a>
        @else
        <a class="tab-nav_item{{ $mylist ? ' tab-nav_item--active' : '' }}" href="/?tab=mylist"{!! $mylist ? ' aria-current="page"' : '' !!}>マイリスト</a>
        @endif
    </nav>
    @endauth
    <div class="index-form_image_box">
        @if(isset($items))
        @foreach($items as $item)
        <a class="index-form_image_wrapper" href="/item/{{ $item['id'] }}">
            <div class="sale-status_frame">
                <img class="index-form_image_attribute" src="{{ $item->image_url }}">
                @include('partials.sale_status_overlay')
            </div>
            <div style="display:flex;justify-content:space-between;">
                <span>{{ $item['item_name'] }}</span>
            </div>
        </a>
        @endforeach
        @endif
        {{-- 0 件のときの案内。検索の文字があれば検索の結果として、なければマイリストが空として案内する。
             検索もマイリストもログインが必要なので、未ログインの一覧には出さない --}}
        @auth
        @if($items->isEmpty())
        @if(isset($keyword) && $keyword !== '')
        <p class="item-list_empty">該当する商品はありません。</p>
        @elseif($mylist)
        <p class="item-list_empty">いいねした商品はありません。</p>
        @endif
        @endif
        @endauth
    </div>
</body>

@endsection
