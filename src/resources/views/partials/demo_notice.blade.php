{{-- デモ環境の案内（DEMO_MODE=true のときだけ表示）。$place：login / register --}}
@if (config('demo.enabled'))
<link rel="stylesheet" href="@versioned('css/demo-notice.css')">
@if ($place === 'login')
<div class="demo-login">
    <button type="button" class="demo-login_button js-demo-login" data-email="{{ config('demo.email') }}" data-password="{{ config('demo.password') }}">デモアカウントでログイン</button>
</div>
<script src="@versioned('js/demo-login.js')" defer></script>
@else
<div class="demo-notice">
    <p class="demo-notice_text">このデモ環境では、架空のメールアドレス（例：yourname@example.com）と、普段使っていないパスワードで登録してください。</p>
    <p class="demo-notice_text">認証メールは送信しません。登録したデータは毎日 4:00 に削除されます。</p>
</div>
@endif
@endif
