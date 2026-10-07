<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>メール認証</title>
    <script src="@versioned('js/submit-guard.js')" defer></script>
    <style>
        /* ほかの画面（header.css）と同じ、文字の大きさの基準 */
        html {
            font-size: 90%;
        }

        button:disabled,
        input[type="submit"]:disabled {
            opacity: 0.6;
            cursor: wait;
        }
    </style>
</head>

<body style="width:100%;display:flex;justify-content:center;">
    <div>
        <h3>登録していただいたメールアドレスに認証メールを送付しました。</h3>
        <div style="display:flex;justify-content:center;">
            <div>
                <h3>メールを確認していただき認証を完了してください。</h3>
                <div style="display:flex;flex-direction:column;justify-content:center;">
                    <div style="border-radius:4.5px;padding: 9px 18px; font-size: 14.4px;background-color:gray;color:#fff;text-decoration:none;display:flex;justify-content:center;">メールにあるボタンをクリックしてください。</div>
                    @if (session('status') == 'verification-link-sent')
                    <p style="display:flex;justify-content:center;font-size:12.6px;">認証メールを再送しました。</p>
                    @endif
                    <form style="display:flex;justify-content:center;" action="{{ route('verification.send') }}" method="post">
                        @csrf
                        <button style="margin-top:5vh;border:none;background-color:#fff;font-size:12.6px;">認証メールを再送する</button>
                    </form>
                    <form style="display:flex;justify-content:center;" action="{{ route('logout') }}" method="post">
                        @csrf
                        <button style="margin-top:2vh;border:none;background-color:#fff;font-size:12.6px;">ログアウト</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>


</html>
