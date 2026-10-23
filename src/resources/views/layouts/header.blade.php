<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flea Market</title>
    {{-- ファビコン（ロゴと同じ値札）。SVG を使えないブラウザは .ico を使う --}}
    <link rel="icon" href="@versioned('favicon.ico')" sizes="any">
    <link rel="icon" href="@versioned('favicon.svg')" type="image/svg+xml">
    <link rel="stylesheet" href="@versioned('css/sanitize.css')">
    <link rel="stylesheet" href="@versioned('css/header.css')">
    @yield('css')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="@versioned('js/submit-guard.js')" defer></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

<body>
    {{-- 配置は header.css で決める（幅が狭いときの折り返しを @media で切り替えるため、style 属性には書かない） --}}
    <header class="frea-market_header{{ Auth::check() ? '' : ' frea-market_header--guest' }}">

        {{-- ロゴは商品一覧へ戻るリンク。未ログインでは / がログイン画面に送られるので、/frea へ --}}
        <a class="frea-market_header_logo_link" href="{{ Auth::check() ? '/' : '/frea' }}">
            <img class="frea-market_header_logo" src="@versioned('images/logo.svg')" alt="Flea Market">
        </a>

        @if (Auth::check())
        <form class="frea-market_header_search" action="/search" method="post">
            @csrf
            {{-- 案内の文字は placeholder（value にすると、その文字で検索される）。検索した文字は、検索後も欄に残す --}}
            <input class="frea-market_header_input_key" type="text" name="keyword" placeholder="なにをお探しですか？" value="{{ $keyword ?? '' }}">
        </form>
        <div class="frea-market_header_nav">
            <form class="frea-market_header_logout" action="{{ route('logout') }}" method="post">
                @csrf
                <button class="frea-market_header_logout_button">ログアウト</button>
            </form>
            <a class="frea-market_header_link" href="/mypage">マイページ</a>
            <a class="frea-market_header_button_to_exhibit" href="/sell">出品</a>
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
