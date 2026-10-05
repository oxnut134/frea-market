{{-- デモ用アカウントの案内（DEMO_NOTICE=true のときだけ表示）。$place：login / register / verify --}}
@if (config('demo.notice'))
<link rel="stylesheet" href="@versioned('css/demo-notice.css')">
@if ($place === 'login')
<div class="demo-login">
    <button type="button" class="demo-login_button js-demo-login" data-email="{{ config('demo.email') }}" data-password="{{ config('demo.password') }}">デモアカウントでログイン</button>
</div>
<script src="@versioned('js/demo-login.js')" defer></script>
@else
<div class="demo-notice">
    <p class="demo-notice_text">このデモ環境ではメールを送信しないため、新規登録しても認証を完了できません。デモ用アカウントをご利用ください。</p>
    @if ($place === 'register')
    <a class="demo-notice_link" href="/login">ログイン画面のデモアカウントでログインする</a>
    @endif
</div>
@endif
@endif
