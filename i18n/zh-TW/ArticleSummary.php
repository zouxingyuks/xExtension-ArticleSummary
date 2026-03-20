<?php
return array(
    'config' => array(
        'section_title' => '文章總結設定',
        'intro' => '先選擇提供商，再填寫用來產生文章總結的介面位址與模型。',
        'ai_provider' => 'AI 提供商',
        'provider_help' => 'OpenAI 適用於相容 OpenAI API 的服務；Ollama 適用於本機或自架的 Ollama 服務。',
        'base_url' => '基礎 URL',
        'base_url_help' => '請填寫 API 根位址，例如 https://api.openai.com 或 https://api.openai.com/v1。對於相容 OpenAI 的服務，是否包含 /v1 都可以。',
        'api_key' => 'API密鑰',
        'api_key_help' => 'OpenAI 相容服務通常必填；若你的 Ollama 服務允許匿名存取，則可以留空。',
        'model_name' => '模型名稱',
        'model_name_help' => '例如：gpt-5.3-codex-spark、claude-sonnet-4-6、llama3.2。',
        'prompt' => '總結指令',
        'prompt_help' => '這段指令會在文章內容前送給模型。留空時會使用目前語言的預設提示詞。',
        'default_prompt' => '請用中文繁體總結以下文章。保持簡潔但資訊豐富，突出關鍵點和主要思想。',
        'save' => '儲存設定',
        'saved' => '設定已儲存。新的總結請求會使用更新後的設定。',
        'openai' => 'OpenAI',
        'ollama' => 'Ollama'
    ),
    'button' => array(
        'summarize' => '總結文章',
        'summarize_title' => '為這篇文章產生 AI 總結',
        'retry' => '重試'
    ),
    'status' => array(
        'loading' => '正在產生總結…',
        'error' => '發生了一點問題。',
        'request_failed' => '無法開始本次總結請求。',
        'timeout' => '本次總結耗時過久，請稍後再試。',
        'cancelled' => '總結請求在完成前被取消了。',
        'partial_error' => '總結在中途停止，下方保留了已產生的部分結果。',
        'configuration' => '請先打開擴充套件設定並補齊必填欄位，再發起總結。',
        'invalid_request' => '目前這篇文章的總結操作暫時不可用，請重新整理頁面後再試。',
        'invalid_proxy' => '總結服務回傳了無效的回應，請稍後再試。',
        'empty_summary' => 'AI 服務已完成請求，但沒有回傳任何總結內容。',
        'help' => '產生總結可能需要幾秒鐘，並可能消耗 API 額度。'
    )
);
