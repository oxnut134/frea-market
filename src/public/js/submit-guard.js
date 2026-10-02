// フォーム送信中はボタンを無効化して「処理中…」を表示する（二重送信防止）
document.addEventListener('submit', function (event) {
    const form = event.target;

    // 送信済みのフォームは、Enter キーなどでの再送信を止める
    if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
    }
    // 他のスクリプトが送信を止めた場合は何もしない
    if (event.defaultPrevented) return;

    form.dataset.submitting = 'true';
    const buttons = form.querySelectorAll('button:not([type="button"]), input[type="submit"]');
    // 押されたボタン（Enter キーでの送信時はフォーム内の最初のボタン）
    const pressed = event.submitter || buttons[0];

    // 送信データが組み立てられた後に無効化する（ボタンの値が送信から漏れないように）
    setTimeout(function () {
        buttons.forEach(function (button) {
            button.disabled = true;
        });
        if (!pressed) return;

        if (pressed.tagName === 'INPUT') {
            pressed.dataset.originalHtml = pressed.value;
            pressed.value = '処理中…';
        } else if (pressed.textContent.trim() !== '') {
            // アイコンだけのボタン（いいね等）は文字を差し替えない
            pressed.dataset.originalHtml = pressed.innerHTML;
            pressed.textContent = '処理中…';
        }
    }, 0);
});

// ブラウザの「戻る」でページが復元されたとき、ボタンを元に戻す
window.addEventListener('pageshow', function (event) {
    if (!event.persisted) return;
    document.querySelectorAll('form[data-submitting="true"]').forEach(function (form) {
        delete form.dataset.submitting;
        form.querySelectorAll('button, input[type="submit"]').forEach(function (button) {
            button.disabled = false;
            if (button.dataset.originalHtml === undefined) return;
            if (button.tagName === 'INPUT') {
                button.value = button.dataset.originalHtml;
            } else {
                button.innerHTML = button.dataset.originalHtml;
            }
            delete button.dataset.originalHtml;
        });
    });
});
