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
    </div>
</body>

@endsection
