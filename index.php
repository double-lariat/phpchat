<?php
// index.php
// ブラウザからの入力を受け取り、重要語を1つ選んで「原語 - 英訳」をプレーンテキストで返す簡単な実装
// ライブラリは使わず、OpenAIのChat Completionsエンドポイントを直接curlで叩きます。

// .env を読み込んで環境変数を取得
function loadEnv($path = __DIR__ . '/.env') {
    $vars = [];
    if (!file_exists($path)) return $vars;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($key, $val) = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        // remove optional quotes
        $val = preg_replace('/(^\"|\"$|^\'|\'$)/', '', $val);
        $vars[$key] = $val;
    }
    return $vars;
}

$env = loadEnv();
$OPENAI_API_KEY = isset($env['OPENAI_API_KEY']) ? $env['OPENAI_API_KEY'] : getenv('OPENAI_API_KEY');

// simple helper to call OpenAI Chat Completions (no external libs)
function call_openai_chat($apiKey, $model, $messages, $max_tokens = 60) {
    $url = 'https://api.openai.com/v1/chat/completions';
    $payload = json_encode([
        'model' => $model,
        'messages' => $messages,
        'max_tokens' => $max_tokens,
        'temperature' => 0.2,
        'n' => 1,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) return ['error' => "curl_error: $err"];
    $data = json_decode($resp, true);
    if (!$data) return ['error' => 'invalid_json', 'raw' => $resp];
    if ($code < 200 || $code >= 300) return ['error' => 'http_error', 'code' => $code, 'body' => $data];
    return ['result' => $data];
}

// HTMLフォームと処理
$input = isset($_POST['q']) ? trim($_POST['q']) : null;
$outputText = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($OPENAI_API_KEY)) {
        $error = 'OpenAI APIキーが設定されていません。`.env` に `OPENAI_API_KEY` を追加してください。';
    } elseif (empty($input)) {
        $error = '入力が空です。日本語の問いかけを入力してください。';
    } else {
        // プロンプトを作成（モデルに「原語 - 英訳」の形式でプレーンテキストのみを返すよう厳密に指示）
        $system = "あなたは日本語の短い問いかけから最も重要な語を1つ選び、その原語（日本語）と英訳（単語1つ）をプレーンテキストで出力するアシスタントです。出力は必ず1行で、フォーマットは「原語 - translation（英単語）」にしてください。余談や説明は一切書かないでください。例: 猫 - cat";
        $user_msg = "入力: $input\n\n指示: 上のルールに従って、最も重要な語1つを選び、プレーンテキストで出力してください。フォーマット: 原語 - translation";

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user_msg],
        ];

        $res = call_openai_chat($OPENAI_API_KEY, 'gpt-5-mini', $messages, 60);
        if (isset($res['error'])) {
            $error = 'API呼び出しエラー: ' . json_encode($res);
        } else {
            $data = $res['result'];
            // Chat Completions の標準的なレスポンス構造を参照
            if (isset($data['choices'][0]['message']['content'])) {
                $raw = $data['choices'][0]['message']['content'];
                // 期待形式を厳密に満たすように最初の行を取り、不要な空白をトリム
                $lines = preg_split('/\r?\n/', trim($raw));
                $first = trim($lines[0]);
                // 最低限のバリデーション: ハイフンで区切られているか
                if (strpos($first, '-') !== false) {
                    $outputText = $first;
                } else {
                    // モデルが余計な情報を吐いた場合は、可能な限り日本語の単語と英語の単語を抽出
                    // シンプルな正規表現: 日本語の語（漢字/ひらがな/カタカナ）と英単語
                    if (preg_match('/([\p{Han}\p{Hiragana}\p{Katakana}ー]+)\s*[\-:\u2014]?\s*([A-Za-z\-]+)/u', $raw, $m)) {
                        $outputText = $m[1] . ' - ' . $m[2];
                    } else {
                        // 最後の手段として生の応答をプレーン表示
                        $outputText = $raw;
                    }
                }
            } else {
                $error = 'APIレスポンスに期待したフィールドがありません。';
            }
        }
    }
}

// 出力をプレーンテキストで返すリクエストがあれば、Content-Type を text/plain にして返す
if (isset($_GET['raw']) && $_GET['raw'] === '1') {
    header('Content-Type: text/plain; charset=utf-8');
    if ($error) {
        echo "ERROR: " . $error;
    } elseif ($outputText) {
        echo $outputText;
    } else {
        echo "入力を送信してください。";
    }
    exit;
}

?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>PHPChat - 重要語抽出 (gpt-5-mini)</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{font-family:system-ui, -apple-system, "Segoe UI", Roboto, "Hiragino Kaku Gothic ProN", "Noto Sans JP", 'Yu Gothic Medium', sans-serif; padding:20px}
.container{max-width:720px;margin:auto}
textarea{width:100%;height:120px;padding:8px;font-size:14px}
button{padding:8px 14px;font-size:16px}
.result{white-space:pre-wrap;background:#f8f8f8;padding:12px;border-radius:6px;margin-top:12px}
.error{color:#b00020}
.note{font-size:12px;color:#666}
</style>
</head>
<body>
<div class="container">
<h1>重要語抽出 - 原語と英訳を返す</h1>
<p class="note">入力（日本語）を与えると、最も重要な語を1つ選んで「原語 - 英訳」をプレーンテキストで返します。 (モデル: gpt-5-mini)</p>
<form method="post">
    <label for="q">問いかけ（日本語）：</label>
    <textarea id="q" name="q"><?php echo isset($_POST['q']) ? htmlspecialchars($_POST['q'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') : ''; ?></textarea>
    <div style="margin-top:8px">
        <button type="submit">送信</button>
        <button type="button" onclick="document.getElementById('q').value='猫が好きです。散歩に行きませんか？';">サンプル挿入</button>
    </div>
</form>

<?php if ($error): ?>
    <div class="result error">Error: <?php echo htmlspecialchars($error, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); ?></div>
<?php elseif ($outputText): ?>
    <div class="result"><strong>結果（プレーンテキスト）</strong>
        <div style="margin-top:8px;font-family:monospace;">
            <?php echo htmlspecialchars($outputText, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
    </div>
    <p class="note">プレーンテキストだけを返すAPIが必要なら、`?raw=1` を付けて再リクエストしてください。</p>
<?php endif; ?>

<h2>使い方</h2>
<ul>
    <li>`.env` に `OPENAI_API_KEY=sk-...` を設定してください。</li>
    <li>ローカルでテストするには、ターミナルで次を実行しますn</li>
</ul>
<pre><code>php -S localhost:8000 -t .
</code></pre>
<p class="note">その後ブラウザで <code>http://localhost:8000/index.php</code> にアクセスしてください。</p>
</div>
</body>
</html>
