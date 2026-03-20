<?php
/**
 * Unit tests for ArticleSummaryExtension
 * ArticleSummaryExtension 单元测试
 */

namespace Tests;

use PHPUnit\Framework\TestCase;

// 加载 bootstrap 文件中的模拟类
require_once __DIR__ . '/../phpstan-bootstrap.php';

// 加载主要的类文件
require_once __DIR__ . '/../extension.php';

/**
 * Test class for ArticleSummaryExtension
 * ArticleSummaryExtension 测试类
 */
class ArticleSummaryExtensionTest extends TestCase
{
    protected function tearDown(): void
    {
        _resetAllMocks();
    }

    /**
     * Test that the extension class exists
     * 测试扩展类是否存在
     */
    public function testExtensionClassExists(): void
    {
        $this->assertTrue(class_exists('ArticleSummaryExtension'));
    }

    /**
     * Test that the extension extends Minz_Extension
     * 测试扩展是否继承自 Minz_Extension
     */
    public function testExtensionExtendsMinzExtension(): void
    {
        $reflection = new \ReflectionClass('ArticleSummaryExtension');
        $this->assertTrue($reflection->isSubclassOf('Minz_Extension'));
    }

    /**
     * Test that the extension is final
     * 测试扩展是否为 final 类
     */
    public function testExtensionIsFinal(): void
    {
        $reflection = new \ReflectionClass('ArticleSummaryExtension');
        $this->assertTrue($reflection->isFinal());
    }

    /**
     * Test that the init method exists
     * 测试 init 方法是否存在
     */
    public function testInitMethodExists(): void
    {
        $this->assertTrue(method_exists('ArticleSummaryExtension', 'init'));
    }

    /**
     * Test that the addSummaryButton method exists
     * 测试 addSummaryButton 方法是否存在
     */
    public function testAddSummaryButtonMethodExists(): void
    {
        $this->assertTrue(method_exists('ArticleSummaryExtension', 'addSummaryButton'));
    }

    /**
     * Test that the handleConfigureAction method exists
     * 测试 handleConfigureAction 方法是否存在
     */
    public function testHandleConfigureActionMethodExists(): void
    {
        $this->assertTrue(method_exists('ArticleSummaryExtension', 'handleConfigureAction'));
    }

    public function testAddSummaryButtonIncludesReadableButtonTextAndHelp(): void
    {
        \FreshRSS_Context::$user_conf = (object) ['oai_prompt' => null];

        $entry = new \FreshRSS_Entry(123, '<p>Hello world</p>');
        $extension = new \ArticleSummaryExtension();

        $result = $extension->addSummaryButton($entry);
        $content = $result->content();

        $this->assertStringContainsString('data-summarize-text="ArticleSummary.button.summarize"', $content);
        $this->assertStringContainsString('data-summarize-title-text="ArticleSummary.button.summarize_title"', $content);
        $this->assertStringContainsString('class="oai-summary-btn"', $content);
        $this->assertStringContainsString('>ArticleSummary.button.summarize</button>', $content);
        $this->assertStringContainsString('<p class="oai-summary-help">ArticleSummary.status.help</p>', $content);
    }

    public function testHandleConfigureActionTrimsValuesAndNormalizesProvider(): void
    {
        \Minz_Request::$_isPost = true;
        \Minz_Request::$_params = [
            'oai_url' => '  http://axonhub.r640.local/v1  ',
            'oai_key' => '  secret-key  ',
            'oai_model' => '  gpt-5.3-codex-spark  ',
            'oai_prompt' => '  Summarize clearly  ',
            'oai_provider' => ' invalid-provider ',
        ];

        $saved = false;
        \FreshRSS_Context::$user_conf = new class($saved) {
            public string $oai_url = '';
            public string $oai_key = '';
            public string $oai_model = '';
            public $oai_prompt = '';
            public string $oai_provider = '';
            private bool $saved = false;

            public function __construct(bool $saved)
            {
                $this->saved = $saved;
            }

            public function save(): void
            {
                $this->saved = true;
            }

            public function wasSaved(): bool
            {
                return $this->saved;
            }
        };

        $extension = new \ArticleSummaryExtension();
        $extension->handleConfigureAction();

        $this->assertTrue(\FreshRSS_Context::$user_conf->wasSaved());
        $this->assertSame('http://axonhub.r640.local/v1', \FreshRSS_Context::$user_conf->oai_url);
        $this->assertSame('secret-key', \FreshRSS_Context::$user_conf->oai_key);
        $this->assertSame('gpt-5.3-codex-spark', \FreshRSS_Context::$user_conf->oai_model);
        $this->assertSame('Summarize clearly', \FreshRSS_Context::$user_conf->oai_prompt);
        $this->assertSame('openai', \FreshRSS_Context::$user_conf->oai_provider);
    }

    public function testHandleConfigureActionConvertsEmptyPromptToNull(): void
    {
        \Minz_Request::$_isPost = true;
        \Minz_Request::$_params = [
            'oai_url' => 'http://axonhub.r640.local',
            'oai_key' => 'secret-key',
            'oai_model' => 'claude-sonnet-4-6',
            'oai_prompt' => '   ',
            'oai_provider' => 'ollama',
        ];

        \FreshRSS_Context::$user_conf = new class {
            public string $oai_url = '';
            public string $oai_key = '';
            public string $oai_model = '';
            public $oai_prompt = 'preset';
            public string $oai_provider = '';

            public function save(): void
            {
            }
        };

        $extension = new \ArticleSummaryExtension();
        $extension->handleConfigureAction();

        $this->assertNull(\FreshRSS_Context::$user_conf->oai_prompt);
        $this->assertSame('ollama', \FreshRSS_Context::$user_conf->oai_provider);
    }
}
