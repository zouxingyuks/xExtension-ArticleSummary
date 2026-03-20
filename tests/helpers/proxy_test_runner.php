<?php
/**
 * Subprocess runner for proxyAction tests.
 * proxyAction 使用 exit() 终止进程，无法在 PHPUnit 进程内测试，
 * 因此通过子进程调用并捕获输出。
 *
 * Usage: php proxy_test_runner.php '<json_config>'
 */

// Capture all output including headers
ob_start();

require_once __DIR__ . '/../../phpstan-bootstrap.php';
require_once __DIR__ . '/../../Controllers/ArticleSummaryController.php';

$config = json_decode($argv[1] ?? '{}', true);
if (!is_array($config)) {
    echo json_encode(array('error' => 'invalid_runner_config'));
    exit(1);
}

// Set up mocks from config
FreshRSS_Auth::$_hasAccess = $config['has_access'] ?? true;
Minz_Request::$_isPost = $config['is_post'] ?? true;
Minz_Request::$_params = $config['params'] ?? [];

if (isset($config['user_conf'])) {
    FreshRSS_Context::$user_conf = (object) $config['user_conf'];
} else {
    FreshRSS_Context::$user_conf = null;
}

if (isset($config['entry'])) {
    $e = $config['entry'];
    MockEntryDao::$_entry = new FreshRSS_Entry(
        (string) ($e['id'] ?? ''),
        $e['title'] ?? '',
        $e['author'] ?? '',
        $e['content'] ?? ''
    );
    FreshRSS_Factory::$_entryDao = new MockEntryDao();
} elseif (!empty($config['has_entry_dao'])) {
    MockEntryDao::$_entry = null;
    FreshRSS_Factory::$_entryDao = new MockEntryDao();
} elseif (isset($config['no_entry_dao']) && $config['no_entry_dao']) {
    FreshRSS_Factory::$_entryDao = null;
} else {
    FreshRSS_Factory::$_entryDao = new MockEntryDao();
}

$controller = new FreshExtension_ArticleSummary_Controller();
$controller->proxyAction();
