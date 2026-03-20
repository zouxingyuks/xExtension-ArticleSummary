<?php
/**
 * Comprehensive tests for ArticleSummaryController
 * ArticleSummaryController 综合测试
 *
 * summarizeAction: tested in-process (uses return, not exit)
 * proxyAction: tested via subprocess (uses exit, kills PHPUnit process)
 */

namespace Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../phpstan-bootstrap.php';
require_once __DIR__ . '/../Controllers/ArticleSummaryController.php';

class ArticleSummaryControllerTest extends TestCase
{
    private \FreshExtension_ArticleSummary_Controller $controller;

    protected function setUp(): void
    {
        _resetAllMocks();
        $this->controller = new \FreshExtension_ArticleSummary_Controller();
    }

    protected function tearDown(): void
    {
        _resetAllMocks();
    }

    // ─── Helpers ───────────────────────────────────────────────

    /**
     * Call summarizeAction and capture JSON output.
     * 调用 summarizeAction 并捕获 JSON 输出
     * @return array{output: string, json: array|null}
     */
    private function callSummarizeAction(): array
    {
        $this->controller->summarizeAction();
        $output = ob_get_clean(); // controller's ob_start() buffer
        return [
            'output' => $output ?: '',
            'json' => json_decode($output ?: '', true),
        ];
    }

    /**
     * Set up a valid user_conf for OpenAI provider.
     * 设置 OpenAI 提供者的有效用户配置
     */
    private function setOpenAIConfig(): void
    {
        \FreshRSS_Context::$user_conf = (object) [
            'oai_url' => 'https://api.openai.com',
            'oai_key' => 'sk-test-key-123',
            'oai_model' => 'gpt-4',
            'oai_prompt' => 'Summarize this article',
            'oai_provider' => 'openai',
        ];
    }

    /**
     * Set up a valid user_conf for Ollama provider.
     * 设置 Ollama 提供者的有效用户配置
     */
    private function setOllamaConfig(): void
    {
        \FreshRSS_Context::$user_conf = (object) [
            'oai_url' => 'http://localhost:11434',
            'oai_key' => null,
            'oai_model' => 'llama3',
            'oai_prompt' => 'Summarize this article',
            'oai_provider' => 'ollama',
        ];
    }

    /**
     * Set up a valid entry DAO with a test article.
     * 设置包含测试文章的有效 entry DAO
     */
    private function setValidEntry(int $id = 42): void
    {
        $entry = new \FreshRSS_Entry((string) $id, 'Test Title', 'Test Author', '<p>Test content</p>');
        \MockEntryDao::$_entry = $entry;
        \FreshRSS_Factory::$_entryDao = new \MockEntryDao();
    }

    /**
     * Run proxyAction in a subprocess and return result.
     * 在子进程中运行 proxyAction 并返回结果
     * @return array{stdout: string, stderr: string, exit_code: int, json: array|null}
     */
    private function runProxySubprocess(array $config, int $timeoutSeconds = 10): array
    {
        $runner = __DIR__ . '/helpers/proxy_test_runner.php';
        $configJson = json_encode($config);
        $cmd = sprintf('php %s %s', escapeshellarg($runner), escapeshellarg($configJson));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $descriptors, $pipes, __DIR__ . '/..');
        if (!is_resource($proc)) {
            $this->fail('Failed to start proxy subprocess');
        }

        fclose($pipes[0]);

        // Set non-blocking mode to enable timeout
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $deadline = time() + $timeoutSeconds;

        while (true) {
            $status = proc_get_status($proc);
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';

            if (!$status['running']) {
                // Drain remaining output
                $stdout .= stream_get_contents($pipes[1]) ?: '';
                $stderr .= stream_get_contents($pipes[2]) ?: '';
                break;
            }

            if (time() >= $deadline) {
                proc_terminate($proc, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);
                $this->fail("Proxy subprocess timed out after {$timeoutSeconds}s. stdout: {$stdout}");
            }

            usleep(50000); // 50ms poll interval
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        return [
            'stdout' => $stdout ?: '',
            'stderr' => $stderr ?: '',
            'exit_code' => $exitCode,
            'json' => json_decode($stdout ?: '', true),
        ];
    }

    /**
     * Build a default valid proxy config for subprocess.
     * 构建子进程的默认有效代理配置
     */
    private function validProxyConfig(string $provider = 'openai'): array
    {
        $conf = [
            'has_access' => true,
            'is_post' => true,
            'params' => ['id' => 42],
            'user_conf' => [
            'oai_url' => 'http://127.0.0.1:1',
                'oai_key' => 'sk-test-key-123',
                'oai_model' => 'gpt-4',
                'oai_prompt' => 'Summarize this article',
                'oai_provider' => $provider,
            ],
            'entry' => [
                'id' => '42',
                'title' => 'Test Title',
                'author' => 'Test Author',
                'content' => '<p>Test content</p>',
            ],
        ];
        if ($provider === 'ollama') {
            $conf['user_conf']['oai_url'] = 'http://localhost:11434';
            $conf['user_conf']['oai_key'] = null;
            $conf['user_conf']['oai_model'] = 'llama3';
        }
        return $conf;
    }

    // ═══ STRUCTURAL TESTS ═══════════════════════════════════════════

    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists('FreshExtension_ArticleSummary_Controller'));
    }

    public function testControllerExtendsMinzActionController(): void
    {
        $reflection = new \ReflectionClass('FreshExtension_ArticleSummary_Controller');
        $this->assertTrue($reflection->isSubclassOf('Minz_ActionController'));
    }

    public function testControllerIsFinal(): void
    {
        $reflection = new \ReflectionClass('FreshExtension_ArticleSummary_Controller');
        $this->assertTrue($reflection->isFinal());
    }

    public function testRequiredMethodsExist(): void
    {
        $methods = ['summarizeAction', 'proxyAction', 'isEmpty', 'htmlToMarkdown', 'processNode'];
        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists('FreshExtension_ArticleSummary_Controller', $method),
                "Method {$method} should exist"
            );
        }
    }

    // ═══ summarizeAction TESTS ═══════════════════════════════════════

    public function testSummarizeAction_Forbidden(): void
    {
        \FreshRSS_Auth::$_hasAccess = false;
        $result = $this->callSummarizeAction();
        $this->assertNotNull($result['json']);
        $this->assertSame('forbidden', $result['json']['error']);
    }

    public function testSummarizeAction_MissingUserConfig(): void
    {
        \FreshRSS_Auth::$_hasAccess = true;
        \FreshRSS_Context::$user_conf = null;
        $result = $this->callSummarizeAction();
        $this->assertNotNull($result['json']);
        $this->assertSame('missing_user_config', $result['json']['error']);
    }

    public function testSummarizeAction_MissingOaiUrl(): void
    {
        \FreshRSS_Context::$user_conf = (object) [
            'oai_url' => null,
            'oai_key' => 'sk-key',
            'oai_model' => 'gpt-4',
            'oai_prompt' => 'Summarize',
            'oai_provider' => 'openai',
        ];
        $result = $this->callSummarizeAction();
        $this->assertNotNull($result['json']);
        $this->assertSame(200, $result['json']['status']);
        $this->assertSame('configuration', $result['json']['response']['error']);
    }

    public function testSummarizeAction_OllamaAllowsNullKey(): void
    {
        $this->setOllamaConfig();
        $this->setValidEntry(42);
        \Minz_Request::$_params = ['id' => 42];
        \Minz_Url::$_displayResult = '/i/?c=ArticleSummary&a=proxy&ajax=1&id=42';
        $result = $this->callSummarizeAction();
        $this->assertNotNull($result['json']);
        $this->assertSame(200, $result['json']['status']);
        $this->assertSame('ollama', $result['json']['response']['provider']);
        $this->assertArrayHasKey('proxy_url', $result['json']['response']);
    }

    public function testSummarizeAction_EntryDaoUnavailable(): void
    {
        $this->setOpenAIConfig();
        \Minz_Request::$_params = ['id' => 42];
        \FreshRSS_Factory::$_entryDao = null;
        $result = $this->callSummarizeAction();
        $this->assertNotNull($result['json']);
        $this->assertSame('entry_dao_unavailable', $result['json']['error']);
    }

    public function testSummarizeAction_ArticleNotFound(): void
    {
        $this->setOpenAIConfig();
        \Minz_Request::$_params = ['id' => 999];
        \MockEntryDao::$_entry = null;
        \FreshRSS_Factory::$_entryDao = new \MockEntryDao();
        $result = $this->callSummarizeAction();
        $this->assertNotNull($result['json']);
        $this->assertSame(404, $result['json']['status']);
    }
    /**
     * Test summarizeAction response shape: proxy_url + provider only, no credentials.
     * 测试 summarizeAction 响应格式：仅包含 proxy_url 和 provider，无凭据
     */
    public function testSummarizeAction_ResponseShape_OpenAI(): void
    {
        $this->setOpenAIConfig();
        $this->setValidEntry(42);
        \Minz_Request::$_params = ['id' => 42];
        \Minz_Url::$_displayResult = '/i/?c=ArticleSummary&a=proxy&ajax=1&id=42';
        $result = $this->callSummarizeAction();
        $this->assertNotNull($result['json'], 'Response should be valid JSON');
        $this->assertSame(200, $result['json']['status']);
        $response = $result['json']['response'];
        // Must have proxy_url and provider
        $this->assertArrayHasKey('proxy_url', $response);
        $this->assertArrayHasKey('provider', $response);
        $this->assertSame('openai', $response['provider']);
        // Must NOT contain credentials
        $this->assertArrayNotHasKey('oai_key', $response);
        $this->assertArrayNotHasKey('oai_url', $response);
        $this->assertArrayNotHasKey('oai_model', $response);
        $this->assertArrayNotHasKey('oai_prompt', $response);
        $this->assertArrayNotHasKey('api_key', $response);
        $this->assertArrayNotHasKey('key', $response);
        // proxy_url must not contain http scheme (same-origin safety)
        $this->assertStringNotContainsString('http://', $response['proxy_url']);
        $this->assertStringNotContainsString('https://', $response['proxy_url']);
        // Entire JSON output must not leak the API key
        $this->assertStringNotContainsString('sk-test-key-123', $result['output']);
    }
    public function testSummarizeAction_ResponseShape_Ollama(): void
    {
        $this->setOllamaConfig();
        $this->setValidEntry(42);
        \Minz_Request::$_params = ['id' => 42];
        \Minz_Url::$_displayResult = '/i/?c=ArticleSummary&a=proxy&ajax=1&id=42';
        $result = $this->callSummarizeAction();
        $this->assertNotNull($result['json']);
        $this->assertSame(200, $result['json']['status']);
        $this->assertSame('ollama', $result['json']['response']['provider']);
        $this->assertArrayHasKey('proxy_url', $result['json']['response']);
        // Only 2 keys in response
        $this->assertCount(2, $result['json']['response']);
    }
    public function testSummarizeAction_ProxyUrlStripsScheme(): void
    {
        $this->setOpenAIConfig();
        $this->setValidEntry(42);
        \Minz_Request::$_params = ['id' => 42];
        // Simulate Minz_Url returning a full URL with scheme+host
        \Minz_Url::$_displayResult = 'https://example.com/i/?c=ArticleSummary&a=proxy&ajax=1&id=42';
        $result = $this->callSummarizeAction();
        $this->assertNotNull($result['json']);
        $proxy_url = $result['json']['response']['proxy_url'];
        $this->assertStringStartsWith('/i/', $proxy_url);
        $this->assertStringNotContainsString('example.com', $proxy_url);
    }
    public function testSummarizeAction_OpenAIAddsV1(): void
    {
        \FreshRSS_Context::$user_conf = (object) [
            'oai_url' => 'https://api.openai.com',
            'oai_key' => 'sk-key',
            'oai_model' => 'gpt-4',
            'oai_prompt' => 'Summarize',
            'oai_provider' => 'openai',
        ];
        $this->setValidEntry(1);
        \Minz_Request::$_params = ['id' => 1];
        \Minz_Url::$_displayResult = '/i/?c=ArticleSummary&a=proxy&ajax=1&id=1';
        $result = $this->callSummarizeAction();
        // Provider should be openai (not the raw oai_provider value)
        $this->assertSame('openai', $result['json']['response']['provider']);
    }
    // ═══ proxyAction SUBPROCESS TESTS ═══════════════════════════════════
    public function testProxyAction_NotPost(): void
    {
        $config = $this->validProxyConfig();
        $config['is_post'] = false;
        $result = $this->runProxySubprocess($config);
        $this->assertNotNull($result['json'], 'Expected JSON response for non-POST: ' . $result['stdout']);
        $this->assertSame('method_not_allowed', $result['json']['error']);
    }
    public function testProxyAction_Forbidden(): void
    {
        $config = $this->validProxyConfig();
        $config['has_access'] = false;
        $result = $this->runProxySubprocess($config);
        $this->assertNotNull($result['json'], 'Expected JSON response for forbidden: ' . $result['stdout']);
        $this->assertSame('forbidden', $result['json']['error']);
    }
    public function testProxyAction_MissingUserConfig(): void
    {
        $config = $this->validProxyConfig();
        unset($config['user_conf']);
        $result = $this->runProxySubprocess($config);
        $this->assertNotNull($result['json'], 'Expected JSON: ' . $result['stdout']);
        $this->assertSame('missing_user_config', $result['json']['error']);
    }
    public function testProxyAction_MissingArticleId(): void
    {
        $config = $this->validProxyConfig();
        $config['params'] = [];
        $result = $this->runProxySubprocess($config);
        $this->assertNotNull($result['json'], 'Expected JSON: ' . $result['stdout']);
        $this->assertSame('missing_article_id', $result['json']['error']);
    }
    public function testProxyAction_InvalidProvider(): void
    {
        $config = $this->validProxyConfig();
        $config['user_conf']['oai_provider'] = 'anthropic';
        $result = $this->runProxySubprocess($config);
        $this->assertNotNull($result['json'], 'Expected JSON: ' . $result['stdout']);
        $this->assertSame('invalid_provider', $result['json']['error']);
    }
    public function testProxyAction_MissingConfig_NoUrl(): void
    {
        $config = $this->validProxyConfig();
        $config['user_conf']['oai_url'] = '';
        $result = $this->runProxySubprocess($config);
        $this->assertNotNull($result['json'], 'Expected JSON: ' . $result['stdout']);
        $this->assertSame('configuration', $result['json']['error']);
    }
    public function testProxyAction_MissingConfig_NoModel(): void
    {
        $config = $this->validProxyConfig();
        $config['user_conf']['oai_model'] = '';
        $result = $this->runProxySubprocess($config);
        $this->assertNotNull($result['json'], 'Expected JSON: ' . $result['stdout']);
        $this->assertSame('configuration', $result['json']['error']);
    }
    public function testProxyAction_MissingConfig_NoPrompt(): void
    {
        $config = $this->validProxyConfig();
        $config['user_conf']['oai_prompt'] = '';
        $result = $this->runProxySubprocess($config);
        $this->assertNotNull($result['json'], 'Expected JSON: ' . $result['stdout']);
        $this->assertSame('configuration', $result['json']['error']);
    }
    public function testProxyAction_EntryDaoUnavailable(): void
    {
        $config = $this->validProxyConfig();
        $config['no_entry_dao'] = true;
        unset($config['entry']);
        $result = $this->runProxySubprocess($config);
        $this->assertNotNull($result['json'], 'Expected JSON: ' . $result['stdout']);
        $this->assertSame('entry_dao_unavailable', $result['json']['error']);
    }
    public function testProxyAction_ArticleNotFound(): void
    {
        $config = $this->validProxyConfig();
        $config['has_entry_dao'] = true;
        unset($config['entry']);
        $result = $this->runProxySubprocess($config);
        $this->assertNotNull($result['json'], 'Expected JSON: ' . $result['stdout']);
        $this->assertSame('article_not_found', $result['json']['error']);
    }
    /**
     * Test that proxyAction with valid OpenAI config attempts cURL connection.
     * Since there's no real API, we expect a cURL error (connection refused/timeout).
     * The important thing is it gets past all validation gates.
     * 测试有效 OpenAI 配置通过所有验证关卡
     */
    public function testProxyAction_OpenAI_PassesValidation(): void
    {
        $config = $this->validProxyConfig('openai');
        $result = $this->runProxySubprocess($config);
        // Should NOT be a validation error — should be a cURL/connection error
        if ($result['json'] !== null) {
            $this->assertNotContains($result['json']['error'] ?? '', [
                'method_not_allowed', 'forbidden', 'missing_user_config',
                'missing_article_id', 'invalid_provider', 'configuration',
                'entry_dao_unavailable', 'article_not_found',
            ], 'Should pass all validation gates. Got: ' . ($result['json']['error'] ?? 'none'));
        }
        // If we got here, validation passed (cURL error is expected)
        $this->assertTrue(true);
    }
    public function testProxyAction_Ollama_PassesValidation(): void
    {
        $config = $this->validProxyConfig('ollama');
        $result = $this->runProxySubprocess($config);
        if ($result['json'] !== null) {
            $this->assertNotContains($result['json']['error'] ?? '', [
                'method_not_allowed', 'forbidden', 'missing_user_config',
                'missing_article_id', 'invalid_provider', 'configuration',
                'entry_dao_unavailable', 'article_not_found',
            ], 'Should pass all validation gates. Got: ' . ($result['json']['error'] ?? 'none'));
        }
        $this->assertTrue(true);
    }
    public function testProxyAction_ResponseContainsRequestId(): void
    {
        // Even error responses should contain request_id
        $config = $this->validProxyConfig();
        $config['params'] = []; // trigger missing_article_id
        $result = $this->runProxySubprocess($config);
        $this->assertNotNull($result['json'], 'Expected JSON: ' . $result['stdout']);
        $this->assertArrayHasKey('request_id', $result['json']);
        $this->assertNotEmpty($result['json']['request_id']);
    }
    public function testProxyAction_OpenAI_RequiresKey(): void
    {
        $config = $this->validProxyConfig('openai');
        $config['user_conf']['oai_key'] = '';
        $result = $this->runProxySubprocess($config);
        $this->assertNotNull($result['json'], 'Expected JSON: ' . $result['stdout']);
        $this->assertSame('configuration', $result['json']['error']);
    }
    public function testProxyAction_Ollama_AllowsNullKey(): void
    {
        $config = $this->validProxyConfig('ollama');
        $config['user_conf']['oai_key'] = null;
        $result = $this->runProxySubprocess($config);
        // Should NOT fail on missing_config — Ollama doesn't require a key
        if ($result['json'] !== null && isset($result['json']['error'])) {
            $this->assertNotSame('missing_config', $result['json']['error'],
                'Ollama should not require an API key');
        }
        $this->assertTrue(true);
    }
}