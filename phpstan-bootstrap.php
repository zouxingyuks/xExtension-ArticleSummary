<?php
/**
 * PHPStan bootstrap file
 * PHPStan 启动文件
 * Also used as test mock bootstrap / 也用作测试模拟启动文件
 */

// Define constants for FreshRSS if not already defined
if (!defined('FRESHRSS_PATH')) {
    define('FRESHRSS_PATH', __DIR__ . '/../../..');
}

// Define XML constants if not already defined
if (!defined('XML_ELEMENT_NODE')) {
    define('XML_ELEMENT_NODE', 1);
}

if (!defined('XML_TEXT_NODE')) {
    define('XML_TEXT_NODE', 3);
}

// Mock FreshRSS classes for static analysis and testing
// 模拟 FreshRSS 类用于静态分析和测试

if (!class_exists('FreshRSS_Auth')) {
    class FreshRSS_Auth {
        /** @var bool Configurable for testing */
        public static bool $_hasAccess = true;

        public static function hasAccess(): bool {
            return self::$_hasAccess;
        }
    }
}

if (!class_exists('FreshRSS_Context')) {
    class FreshRSS_Context {
        /** @var object|null Configurable for testing */
        public static $user_conf;
    }
}

if (!class_exists('FreshRSS_Entry')) {
    class FreshRSS_Entry {
        private string $_id;
        private string $_title;
        private string $_author;
        private string $_content;

        public function __construct(string $id = '', string $title = '', string $author = '', string $content = '') {
            $this->_id = $id;
            $this->_title = $title;
            $this->_author = $author;
            $this->_content = $content;
        }

        public function id(): string {
            return $this->_id;
        }

        public function title(): string {
            return $this->_title;
        }

        public function author(): string {
            return $this->_author;
        }

        public function content(): string {
            return $this->_content;
        }

        public function _content(string $content): void {
            $this->_content = $content;
        }
    }
}

if (!class_exists('MockEntryDao')) {
    class MockEntryDao {
        /** @var FreshRSS_Entry|null Configurable for testing */
        public static $_entry = null;

        public function searchById($id): ?FreshRSS_Entry {
            return self::$_entry;
        }
    }
}

if (!class_exists('FreshRSS_Factory')) {
    class FreshRSS_Factory {
        /** @var object|null Configurable for testing */
        public static $_entryDao = null;

        public static function createEntryDao() {
            return self::$_entryDao;
        }
    }
}

if (!class_exists('Minz_Extension')) {
    class Minz_Extension {
        protected array $csp_policies = [];

        public function init(): void {
        }

        public function registerHook(string $hook, callable $callback): void {
        }

        public function registerController(string $name): void {
        }

        public function registerTranslates(string $path): void {
        }

        public function getFileUrl(string $file, string $type): string {
            return '';
        }

        public function handleConfigureAction(): void {
        }
    }
}

if (!class_exists('Minz_ActionController')) {
    class Minz_ActionController {
        public $view;

        public function __construct() {
            $this->view = new class {
                public function _layout($layout): void {
                }
            };
        }
    }
}

if (!class_exists('Minz_Request')) {
    class Minz_Request {
        /** @var array<string, mixed> Configurable for testing */
        public static array $_params = [];
        /** @var bool Configurable for testing */
        public static bool $_isPost = false;

        public static function param(string $name, $default = null) {
            return self::$_params[$name] ?? $default;
        }

        public static function paramInt(string $name, int $default = 0): int {
            return isset(self::$_params[$name]) ? (int) self::$_params[$name] : $default;
        }

        public static function isPost(): bool {
            return self::$_isPost;
        }
    }
}

if (!class_exists('Minz_Url')) {
    class Minz_Url {
        /** @var string Configurable for testing */
        public static string $_displayResult = '';

        public static function display(array $params, string $format = 'html', string $type = ''): string {
            return self::$_displayResult;
        }
    }
}

if (!class_exists('Minz_View')) {
    class Minz_View {
        public static function appendStyle(string $url): void {
        }

        public static function appendScript(string $url): void {
        }
    }
}

if (!class_exists('Minz_HookType')) {
    class Minz_HookType {
        public const EntryBeforeDisplay = 'entry_before_display';
    }
}

if (!class_exists('DOMNode')) {
    class DOMNode {
        public int $nodeType;
        public string $nodeName;
        public string $nodeValue;
        public iterable $childNodes;
        public ?DOMNode $firstChild;

        public function getAttribute(string $name): string {
            return '';
        }

        public function hasChildNodes(): bool {
            return false;
        }

        public function getFirstChild(): ?DOMNode {
            return null;
        }
    }
}

if (!class_exists('DOMDocument')) {
    class DOMDocument {
        public function loadHTML(string $html): bool {
            return true;
        }
    }
}

if (!class_exists('DOMXPath')) {
    class DOMXPath {
        public function __construct() {
        }

        public function query(string $expression): DOMNodeList {
            return new DOMNodeList();
        }
    }
}

if (!class_exists('DOMNodeList')) {
    class DOMNodeList implements IteratorAggregate {
        public function getIterator(): Traversable {
            return new ArrayIterator([]);
        }
    }
}

if (!function_exists('_t')) {
    function _t(string $key, ...$args): string {
        return $key;
    }
}

/**
 * Reset all mock states to defaults.
 * Call in test tearDown() to ensure clean state between tests.
 * 重置所有模拟状态为默认值，在测试 tearDown() 中调用
 */
function _resetAllMocks(): void {
    FreshRSS_Auth::$_hasAccess = true;
    FreshRSS_Context::$user_conf = null;
    FreshRSS_Factory::$_entryDao = null;
    MockEntryDao::$_entry = null;
    Minz_Request::$_params = [];
    Minz_Request::$_isPost = false;
    Minz_Url::$_displayResult = '';
}
