// 詳細画面のいいね。アイコンと件数は、サーバーの応答（liked / likes）で更新する
document.addEventListener('DOMContentLoaded', function () {
    // 未ログインのときは、ログインして同じ商品に戻るリンクなので、何もしない
    const button = document.querySelector('.js-like-button');
    if (!button) return;

    const icon = button.querySelector('.like-icon');
    const count = document.getElementById('count');
    const token = document.querySelector('meta[name="csrf-token"]').content;

    button.addEventListener('click', function (event) {
        event.preventDefault();

        // 送信中は次のクリックを無視する（連打対策）
        if (button.dataset.sending === 'true') return;
        button.dataset.sending = 'true';
        button.classList.add('is-sending');
        button.setAttribute('aria-busy', 'true');

        fetch(button.dataset.likeUrl, {
            method: button.dataset.liked === '1' ? 'DELETE' : 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        })
            .then(function (response) {
                // ログインが切れていたら、ログイン画面へ案内する（ログイン後は、同じ商品に戻る）。
                // ログアウトやセッション切れでは CSRF トークンも作り直され、
                // auth より先に CSRF の検証で止まるので、401 ではなく 419 が返る
                if (response.status === 401 || response.status === 419) {
                    window.location.href = button.dataset.loginUrl;
                    return null;
                }
                if (!response.ok) {
                    throw new Error('like request failed: ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                if (!data) return;
                button.dataset.liked = data.liked ? '1' : '0';
                icon.src = data.liked ? button.dataset.iconOn : button.dataset.iconOff;
                count.textContent = data.likes;
            })
            .catch(function () {
                // 失敗時は表示を変えない（アイコンと件数は送信前のまま）
            })
            .finally(function () {
                delete button.dataset.sending;
                button.classList.remove('is-sending');
                button.removeAttribute('aria-busy');
            });
    });
});
