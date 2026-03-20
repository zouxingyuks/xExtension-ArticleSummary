<?php
return array(
    'config' => array(
        'section_title' => 'Article summary settings',
        'intro' => 'Choose a provider, then enter the endpoint and model you want to use for article summaries.',
        'ai_provider' => 'AI provider',
        'provider_help' => 'Use OpenAI for OpenAI-compatible APIs. Use Ollama for local or self-hosted Ollama servers.',
        'base_url' => 'Base URL',
        'base_url_help' => 'Enter the API root, for example https://api.openai.com or https://api.openai.com/v1. OpenAI-compatible providers can include /v1 or omit it.',
        'api_key' => 'API Key',
        'api_key_help' => 'Required for OpenAI-compatible providers. Optional for Ollama if your server allows unauthenticated access.',
        'model_name' => 'Model Name',
        'model_name_help' => 'Examples: gpt-5.3-codex-spark, claude-sonnet-4-6, llama3.2.',
        'prompt' => 'Summary instructions',
        'prompt_help' => 'These instructions are sent before the article content. Leave this empty to use the default prompt for your language.',
        'default_prompt' => 'Please summarize the following article in English. Keep it concise but informative, highlighting the key points and main ideas.',
        'save' => 'Save settings',
        'saved' => 'Settings saved. New summary requests will use the updated values.',
        'openai' => 'OpenAI',
        'ollama' => 'Ollama'
    ),
    'button' => array(
        'summarize' => 'Summarize article',
        'summarize_title' => 'Generate an AI summary for this article',
        'retry' => 'Try again'
    ),
    'status' => array(
        'loading' => 'Generating summary…',
        'error' => 'Something went wrong.',
        'request_failed' => 'Couldn\'t start the summary request.',
        'timeout' => 'The summary took too long to finish. Please try again.',
        'cancelled' => 'The summary request was cancelled before it finished.',
        'partial_error' => 'The summary stopped early. The partial result is shown below.',
        'configuration' => 'Open the extension settings and complete the required fields before requesting a summary.',
        'invalid_request' => 'This article summary action is currently unavailable. Please refresh the page and try again.',
        'invalid_proxy' => 'The summary service returned an invalid response. Please try again.',
        'empty_summary' => 'The AI service finished without returning any summary text.',
        'help' => 'Summaries may take a few seconds and can use API credits.'
    )
);
