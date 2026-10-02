<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>フリマアプリ</title>
    <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
    <link rel="stylesheet" href="{{ asset('css/header.css') }}">
    @yield('css')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="{{ asset('js/submit-guard.js') }}" defer></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

<body>
    @if (Auth::check())
    <header class="frea-market_header" style="display:flex;justify-content:center;align-items: center;">

        @else
        <header class="frea-market_header" style="display:flex;justify-content:flex-start;align-items:center;">

            @endif

            <img class="frea-market_header_logo" src=" {{ asset('images/logo.svg')}}" alt="error">

            @if (Auth::check())
            <form action="/search" method="post" style="width:50%;display:flex;justify-content:flex-end;">
                @csrf
                <input class="frea-market_header_input_key" type="text" name="keyword" value="なにをお探しですか？">
            </form>
            <div style="width:30%;display:flex;justify-content:space-between;align-items:center;margin-right:3%;margin-left:3%;">
                <form action="{{ route('logout') }}" method="post" style="width:35%;">
                    @csrf
                    <button style="max-height:40px;background-color:#000;color:#fff;border-width:0;font-size:17px;">ログアウト</button>
                </form>
                <a class="frea-market_header_link" href="/mypage" style="width:35%;text-decoration:none;">マイページ</a>
                <a class="frea-market_header_button_to_exhibit" href="/sell" style="width:15%">出品</a>
            </div>
            @endif
            <meta name="csrf-token" content="{{ csrf_token() }}">
        </header>
        <main>
            @yield('content')
        </main>

</body>

</html>
