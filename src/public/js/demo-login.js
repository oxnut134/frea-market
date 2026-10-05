// 「デモアカウントでログイン」：ログインフォームにメールアドレスとパスワードを入れて、そのまま送信する
document.addEventListener('click', function (event) {
    const button = event.target.closest('.js-demo-login');
    if (!button) return;

    const form = button.closest('form');
    if (!form) return;

    form.querySelector('input[name="email"]').value = button.dataset.email;
    form.querySelector('input[name="password"]').value = button.dataset.password;

    // requestSubmit は submit イベントを起こすので、submit-guard.js の二重送信防止が効く
    if (form.requestSubmit) {
        form.requestSubmit();
    } else {
        form.submit();
    }
});
