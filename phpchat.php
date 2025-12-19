<?php
/**
 * PHPChat - 面白い会話型エンターテイナー
 * 遊び心溢れたAI的チャットボット
 */

class PHPChat {
    private $responses = [
        'こんにちは' => 'こんにちは！元気ですか？',
        'おはよう' => 'おはようございます！今日も頑張りましょう！',
        'バイバイ' => 'さようなら！また明日ね！👋',
        '疲れた' => 'そんな時は寝るのが一番です。zzz...',
        'ジョーク' => $this->getJoke(),
        '面白い' => '僕も自分のジョークで笑っちゃいます😆',
    ];

    private $jokes = [
        'PHPプログラマーってなぜお風呂が好き？ → Webを見るから！',
        'JavaとPHPの違いは？ → Javaは難しい、PHPは簡単... でも両方とも虫がいっぱい！',
        'プログラマーがクリスマスを待つ理由？ → ついに休暇がstartするから！',
        'なぜプログラマーは暗いのか？ → lightを消し忘れたから！',
        'デバッグとは？ → 自分が書いたクソコードと戦うこと',
        'PHPって何の略？ → PHP: Hypertext Preprocessor... でも本当は「ぐちゃぐちゃ」の意味！',
    ];

    private $easterEggs = [
        'ねこ' => 'にゃんにゃん🐱 猫派ですね',
        'いぬ' => 'わんわん🐶 犬派ですね',
        'タコ焼き' => 'アツアツのたこ焼き...最高！🐙',
        'ラーメン' => 'すすす〜うまい！🍜',
        'PHP' => 'PHPは最高の言語です！...多分',
    ];

    private $moods = ['😊', '😄', '😆', '🤔', '😎', '🎉'];
    private $currentMoodIndex = 0;

    public function __construct() {
        $this->currentMoodIndex = rand(0, count($this->moods) - 1);
    }

    /**
     * ユーザー入力に応答する
     */
    public function chat($userInput) {
        $input = trim($userInput);
        if (empty($input)) {
            return $this->getRandomResponse();
        }

        // イースターエッグを確認
        foreach ($this->easterEggs as $keyword => $response) {
            if (strpos($input, $keyword) !== false) {
                return $this->addMood($response);
            }
        }

        // 標準回答を確認
        foreach ($this->responses as $keyword => $response) {
            if (strpos($input, $keyword) !== false) {
                return $this->addMood($response);
            }
        }

        // デフォルト回答
        return $this->generateSmartResponse($input);
    }

    /**
     * ランダムな返答を生成
     */
    private function getRandomResponse() {
        $responses = [
            'そうですね... 🤔',
            'へぇ、面白い！',
            '私には理解できません(´・ω・`)',
            'もっと教えてください！',
            'そういうこともあるでしょう',
            'ふむふむ 📝',
        ];
        return $this->addMood($responses[array_rand($responses)]);
    }

    /**
     * スマートな返答を生成
     */
    private function generateSmartResponse($input) {
        $length = mb_strlen($input);
        $wordCount = count(explode(' ', $input));

        if ($length > 50) {
            return $this->addMood('長い話ですね... 詳しく聞かせてください！');
        }

        if ($wordCount >= 5) {
            return $this->addMood('複雑な話ですね。もう少し簡潔にお願いします。');
        }

        if (strpos($input, '？') !== false || strpos($input, '?') !== false) {
            return $this->addMood('いい質問ですね！🤔 その答えは... 秘密です！');
        }

        if (strpos($input, '！') !== false || strpos($input, '!') !== false) {
            return $this->addMood('すごい！энергetic ですね！');
        }

        return $this->addMood('へぇ、そんなことがあるんですか。');
    }

    /**
     * ジョークを取得
     */
    private function getJoke() {
        return $this->jokes[array_rand($this->jokes)];
    }

    /**
     * ムード絵文字を追加
     */
    private function addMood($text) {
        $mood = $this->moods[$this->currentMoodIndex];
        $this->currentMoodIndex = ($this->currentMoodIndex + 1) % count($this->moods);
        return $text . ' ' . $mood;
    }

    /**
     * ゲームをプレイ
     */
    public function playGame($gameType) {
        switch (strtolower($gameType)) {
            case '数当て':
            case 'number':
                return $this->numberGuessingGame();
            case 'じゃんけん':
            case 'rps':
                return $this->rockPaperScissors();
            case 'トリビア':
            case 'trivia':
                return $this->triviaGame();
            default:
                return 'そのゲームは知りません。使用可能なゲーム: 数当て, じゃんけん, トリビア';
        }
    }

    /**
     * 数当てゲーム
     */
    private function numberGuessingGame() {
        $secret = rand(1, 100);
        return "秘密の数字を思い浮かべました！(1〜100) 当ててみてください！ 🎯";
    }

    /**
     * じゃんけんゲーム
     */
    private function rockPaperScissors() {
        $choices = ['✊ グー', '✋ パー', '✌️ チョキ'];
        $myChoice = $choices[array_rand($choices)];
        return "じゃんけんしましょう！僕の手は... {$myChoice} です！";
    }

    /**
     * トリビアゲーム
     */
    private function triviaGame() {
        $trivia = [
            'PHPは何年に作られた？ => 1995年',
            'PHPの作成者の名前は？ => ラスマス・ラードフ',
            'PHPはもともと何の略だった？ => Personal Home Page',
        ];
        $question = $trivia[array_rand($trivia)];
        return "【トリビア】$question";
    }

    /**
     * ユーザーの統計情報を表示
     */
    public function getStats() {
        return [
            'PHPバージョン' => phpversion(),
            '実行時間' => date('Y年m月d日 H:i:s'),
            'メモリ使用量' => round(memory_get_usage() / 1024, 2) . ' KB',
            'システム' => php_uname(),
        ];
    }
}

// ===============================================
// インタラクティブセッション
// ===============================================

$chat = new PHPChat();

// テスト用の会話例
$testInputs = [
    'こんにちは',
    'ジョーク教えて',
    'ねこ好きです',
    'PHP最高！',
    '疲れた...',
];

echo "╔════════════════════════════════════════╗\n";
echo "║     PHPChat - 面白い会話システム       ║\n";
echo "╚════════════════════════════════════════╝\n\n";

foreach ($testInputs as $input) {
    echo "👤 ユーザー: $input\n";
    echo "🤖 PHPChat: " . $chat->chat($input) . "\n\n";
}

echo "【ゲームで遊ぼう！】\n";
echo "🤖 PHPChat: " . $chat->playGame('じゃんけん') . "\n\n";

echo "【システム統計】\n";
$stats = $chat->getStats();
foreach ($stats as $key => $value) {
    echo "  $key: $value\n";
}
echo "\n";

echo "╔════════════════════════════════════════╗\n";
echo "║   楽しい会話をありがとう！またね！     ║\n";
echo "╚════════════════════════════════════════╝\n";
