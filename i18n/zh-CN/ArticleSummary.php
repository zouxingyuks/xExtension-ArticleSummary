<?php
return array(
    'config' => array(
        'section_title' => '文章总结设置',
        'intro' => '先选择提供商，再填写用于生成文章总结的接口地址和模型。',
        'ai_provider' => 'AI 提供商',
        'provider_help' => 'OpenAI 用于兼容 OpenAI API 的服务；Ollama 用于本地或自托管的 Ollama 服务。',
        'base_url' => '基础 URL',
        'base_url_help' => '填写接口根地址，例如 https://api.openai.com 或 https://api.openai.com/v1。对于兼容 OpenAI 的服务，是否包含 /v1 都可以。',
        'api_key' => 'API密钥',
        'api_key_help' => 'OpenAI 兼容服务通常必填。若你的 Ollama 服务允许匿名访问，则可以留空。',
        'model_name' => '模型名称',
        'model_name_help' => '例如：gpt-5.3-codex-spark、claude-sonnet-4-6、llama3.2。',
        'prompt' => '总结指令',
        'prompt_help' => '这段指令会在文章内容前发送给模型。留空时会使用当前语言的默认提示词。',
        'default_prompt' => '请用中文总结以下文章。保持简洁但信息丰富，突出关键点和主要思想。',
        'save' => '保存设置',
        'saved' => '设置已保存。新的总结请求会使用更新后的配置。',
        'openai' => 'OpenAI',
        'ollama' => 'Ollama'
    ),
    'button' => array(
        'summarize' => '总结文章',
        'summarize_title' => '为这篇文章生成 AI 总结',
        'retry' => '重试'
    ),
    'status' => array(
        'loading' => '正在生成总结…',
        'error' => '出了点问题。',
        'request_failed' => '无法开始本次总结请求。',
        'timeout' => '本次总结耗时过长，请稍后重试。',
        'cancelled' => '总结请求在完成前被取消了。',
        'partial_error' => '总结在中途停止，下面保留了已经生成的部分结果。',
        'configuration' => '请先打开扩展设置并补全必填项，再发起总结。',
        'invalid_request' => '当前文章的总结操作暂时不可用，请刷新页面后重试。',
        'invalid_proxy' => '总结服务返回了无效响应，请稍后重试。',
        'empty_summary' => 'AI 服务已完成请求，但没有返回总结内容。',
        'help' => '生成总结可能需要几秒钟，并可能消耗 API 配额。'
    )
);
