<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>フリマアプリ</title>
    <link rel="stylesheet" href="@versioned('css/sanitize.css')">
    <link rel="stylesheet" href="@versioned('css/header.css')">
    @yield('css')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="@versioned('js/submit-guard.js')" defer></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

<body>
    @if (Auth::check())
    <header class="frea-market_header" style="display:flex;justify-content:center;align-items: center;">

        @else
        <header class="frea-market_header" style="display:flex;justify-content:flex-start;align-items:center;">

            @endif

            {{-- ロゴは商品一覧へ戻るリンク。未ログインでは / がログイン画面に送られるので、/frea へ --}}
            <a class="frea-market_header_logo_link" href="{{ Auth::check() ? '/' : '/frea' }}">
                <img class="frea-market_header_logo" src="@versioned('images/logo.svg')" alt="Flea Market">
            </a>

            @if (Auth::check())
            <form action="/search" method="post" style="width:50%;display:flex;justify-content:flex-end;">
                @csrf
                {{-- 案内の文字は placeholder（value にすると、その文字で検索される）。検索した文字は、検索後も欄に残す --}}
                <input class="frea-market_header_input_key" type="text" name="keyword" placeholder="なにをお探しですか？" value="{{ $keyword ?? '' }}">
            </form>
            <div style="width:30%;display:flex;justify-content:space-between;align-items:center;margin-right:3%;margin-left:3%;">
                <form action="{{ route('logout') }}" method="post" style="width:35%;">
                    @csrf
                    <button style="max-height:36px;background-color:#000;color:#fff;border-width:0;font-size:15.3px;">ログアウト</button>
                </form>
                <a class="frea-market_header_link" href="/mypage" style="width:35%;text-decoration:none;">マイページ</a>
                <a class="frea-market_header_button_to_exhibit" href="/sell" style="width:15%">出品</a>
            </div>
            @elseif (! request()->routeIs('login'))
            {{-- 未ログイン：ログイン画面へのリンク。戻り先を持つ画面は、login_url で差し替える --}}
            <a class="frea-market_header_login_link" href="@yield('login_url', '/login')">ログイン</a>
            @endif
            <meta name="csrf-token" content="{{ csrf_token() }}">
        </header>
        <main>
            @yield('content')
        </main>

</body>

</html>
