<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>プライバシーポリシー - {{ config('app.name') }}</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, "Hiragino Sans", "Noto Sans JP", sans-serif; line-height: 1.8; color: #1f2937; background: #fff; }
        main { max-width: 720px; margin: 0 auto; padding: 40px 16px 64px; }
        h1 { font-size: 1.5rem; }
        h2 { font-size: 1.1rem; margin-top: 2rem; }
        p, li { font-size: .95rem; }
        .muted { color: #6b7280; font-size: .85rem; }
        @media (prefers-color-scheme: dark) { body { background: #0a0a0a; color: #e5e7eb; } .muted { color: #9ca3af; } }
    </style>
</head>
<body>
<main>
    <h1>プライバシーポリシー</h1>
    <p>{{ config('app.name') }}（以下「本アプリ」）は、運営者本人が自分の就職活動を管理するための個人用アプリです。運営者以外のアカウントではログインできません。</p>

    <h2>取得する情報</h2>
    <ul>
        <li>Googleアカウントの名前・メールアドレス・プロフィール画像（ログインのため）</li>
        <li>Googleカレンダーへの予定の書き込み権限</li>
        <li>Gmailの読み取り権限（受信したメールを取り込み、就活の予定や連絡を見つけるため）</li>
    </ul>

    <h2>Gmailのメールの扱い</h2>
    <p>本アプリは、運営者の就活用Gmailアカウントに届いたメール（迷惑メール・ゴミ箱・送信済み・下書きを除く）を読み取り、本アプリのサーバーに保存します。メールの送信・削除・変更は行いません。</p>
    <p>メールの種類の判定、予定の日時の抜き出し、運営者が「次から表示しない」としたメールの判定のため、メールの内容を OpenAI の API に送信します。OpenAI の API に送信したデータは、OpenAI のモデルの学習には使われません。</p>

    <h2>LINEへの通知</h2>
    <p>運営者がLINEの送り先を設定した場合、予定のまとめと、メールの件名・要約・予定の日時を、LINE株式会社（LINEヤフー株式会社）の Messaging API を通じて運営者のLINEに送信します。</p>

    <h2>利用目的</h2>
    <p>取得した情報は、本アプリの機能（予定の管理、Googleカレンダーへの登録、メールの仕分けと予定の抽出）にのみ使用します。広告、販売、AIの学習などには使用しません。</p>

    <h2>保存と管理</h2>
    <p>データは本アプリのサーバーに保存し、Googleのアクセストークンとメール本文は暗号化して保存します。上記の OpenAI と LINE への送信を除き、第三者に提供することはありません（法令に基づく場合を除く）。</p>

    <h2>Google API サービスのユーザーデータポリシー</h2>
    <p>本アプリによる Google API から受け取った情報の使用および他のアプリへの転送は、限定的使用の要件を含む <a href="https://developers.google.com/terms/api-services-user-data-policy">Google API Services User Data Policy</a> に準拠します。</p>

    <h2>連携の解除</h2>
    <p>本アプリの「設定 → 連携」、または <a href="https://myaccount.google.com/permissions">Googleアカウントのサードパーティ接続</a> からいつでも解除できます。</p>

    <p class="muted">制定日：2026年10月1日</p>
</main>
</body>
</html>
