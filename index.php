<?php
// index.php

// 1. 環境変数の読み込み
function loadEnv($path = __DIR__ . '/.env') {
    $vars = [];
    if (!file_exists($path)) return $vars;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($key, $val) = explode('=', $line, 2);
        $vars[trim($key)] = preg_replace('/(^\"|\"$|^\'|\'$)/', '', trim($val));
    }
    return $vars;
}

$env = loadEnv();
$OPENAI_API_KEY = $env['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');

// 2. OpenAI API 呼び出し関数
function call_openai_chat($apiKey, $input) {
    $url = 'https://api.openai.com/v1/chat/completions';
    $payload = json_encode([
        'model' => 'gpt-4o-mini', // 実在する最新の軽量モデルに変更
        'messages' => [
            ['role' => 'system', 'content' => "あなたは日本語から重要語を1つ選び、「原語 - 英語」の形式で返すアシスタントです。余計な解説は一切禁止します。例: 太陽 - sun"],
            ['role' => 'user', 'content' => "入力: $input"]
        ],
        'temperature' => 0.3,
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ]);
    
    $resp = curl_exec($ch);
    $data = json_decode($resp, true);
    curl_close($ch);

    return $data['choices'][0]['message']['content'] ?? 'エラーが発生しました';
}

// 3. APIモード (JavaScriptからのFetchを受け取る)
if (isset($_GET['ajax']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $postData = json_decode(file_get_contents('php://input'), true);
    $input = $postData['q'] ?? '';

    if (empty($OPENAI_API_KEY)) {
        echo json_encode(['error' => 'APIキーが設定されていません。']);
    } elseif (empty($input)) {
        echo json_encode(['error' => '入力が空です。']);
    } else {
        $result = call_openai_chat($OPENAI_API_KEY, $input);
        echo json_encode(['result' => $result]);
    }
    exit;
}
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>AI重要語抽出ツール</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body { font-family: sans-serif; padding: 20px; line-height: 1.6; background: #f4f7f6; }
        .container { max-width: 600px; margin: auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        textarea { width: 100%; height: 100px; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; margin-bottom: 10px; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        button:disabled { background: #ccc; }
        .result-area { margin-top: 20px; padding: 15px; border-left: 5px solid #007bff; background: #e9f0ff; display: none; }
        .loading { display: none; color: #666; font-style: italic; }
    </style>
</head>
<body>

<div class="container">
    <h1>AI重要語抽出</h1>
    <p>文章を入力すると、AIが最も重要な単語を抜き出して英訳します。</p>

    <textarea id="inputQ" placeholder="例: 昨日は公園でとても綺麗な桜を見ました。"></textarea>
    
    <div>
        <button id="sendBtn" onclick="sendToAI()">AIで解析する</button>
        <div id="loading" class="loading">解析中...</div>
    </div>

    <div id="resultArea" class="result-area">
        <strong>抽出結果:</strong>
        <div id="outputText" style="font-size: 1.2rem; margin-top: 5px;"></div>
    </div>
</div>

<script>
async function sendToAI() {
    const q = document.getElementById('inputQ').value;
    const btn = document.getElementById('sendBtn');
    const loading = document.getElementById('loading');
    const resultArea = document.getElementById('resultArea');
    const outputText = document.getElementById('outputText');

    if(!q) return alert("文字を入力してください");

    // UI状態の更新
    btn.disabled = true;
    loading.style.display = 'block';
    resultArea.style.display = 'none';

    try {
        const response = await fetch('?ajax=1', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ q: q })
        });
        const data = await response.json();

        if(data.error) {
            alert(data.error);
        } else {
            outputText.innerText = data.result;
            resultArea.style.display = 'block';
        }
    } catch (e) {
        alert("通信エラーが発生しました");
    } finally {
        btn.disabled = false;
        loading.style.display = 'none';
    }
}
</script>

</body>
</html>