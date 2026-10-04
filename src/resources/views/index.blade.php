@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="{{ asset('css/index.css') }}">
@endsection

@section('content')

<body>

    @auth
    <div class="index-form_mode_change_button_box">
        <a class="index-form_mode_recommend_button" href="/">おすすめ</a>
        @if(isset($keyword))
        <a class="index-form_mode_mypage_button" href="/?tab=mylist&&keyword={{ $keyword }}">マイリスト</a>
        @else
        <a class="index-form_mode_mypage_button" href="/?tab=mylist">マイリスト</a>
        @endif
    </div>
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
