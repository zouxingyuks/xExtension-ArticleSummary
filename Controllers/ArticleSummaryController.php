<?php

/**
 * Article Summary Controller
 * 文章总结控制器
 */
final class FreshExtension_ArticleSummary_Controller extends Minz_ActionController {
  /**
   * Handle the summarize action
   * 处理总结动作
   */
  public function summarizeAction(): void {
    // Start output buffering to prevent output before header() call
    // This is essential for JSON API responses because header() must be called before any output
    // $this->view->_layout(false) may trigger some output, causing "headers already sent" error
    // 开启输出缓冲区，防止在调用 header() 之前有输出
    // 这对于 JSON API 响应是必要的，因为 header() 必须在任何输出之前调用
    // $this->view->_layout(false) 可能会触发某些输出，导致 headers already sent 错误
    ob_start();
    
    $this->view->_layout(null);
    header('Content-Type: application/json');

    if (!FreshRSS_Auth::hasAccess()) {
      header('HTTP/1.1 403 Forbidden');
      echo json_encode(array('error' => 'forbidden'));
      return;
    }

    if (!is_object(FreshRSS_Context::$user_conf)) {
      header('HTTP/1.1 500 Internal Server Error');
      echo json_encode(array('error' => 'missing_user_config'));
      return;
    }

    // Get configuration values from user settings
    // 从用户设置中获取配置值
    $oai_url = FreshRSS_Context::$user_conf->oai_url;
    $oai_key = FreshRSS_Context::$user_conf->oai_key;
    $oai_model = FreshRSS_Context::$user_conf->oai_model;
    $oai_prompt = FreshRSS_Context::$user_conf->oai_prompt;
    $oai_provider = FreshRSS_Context::$user_conf->oai_provider;

    // Check if all required configurations are provided
    // 检查是否提供了所有必要的配置
    if (
      $this->isEmpty($oai_url)
      || ($this->isEmpty($oai_key) && $oai_provider !== 'ollama')
      || $this->isEmpty($oai_model)
      || $this->isEmpty($oai_prompt)
    ) {
      echo json_encode(array(
        'response' => array(
          'error' => 'configuration'
        ),
        'status' => 200
      ));
      return;
    }

    // Get article ID from request and fetch the article
    // 从请求中获取文章ID并获取文章
    $entry_id = Minz_Request::paramInt('id');
    $entry_dao = FreshRSS_Factory::createEntryDao();
    if (!is_object($entry_dao) || !method_exists($entry_dao, 'searchById')) {
      header('HTTP/1.1 500 Internal Server Error');
      echo json_encode(array('error' => 'entry_dao_unavailable'));
      return;
    }
    $entry = $entry_dao->searchById($entry_id);

    if ($entry === null) {
      echo json_encode(array('status' => 404));
      return;
    }

    // Process API URL - add version if missing (only for OpenAI)
    // 处理API URL - 如果缺少版本则添加（仅针对OpenAI）
    $oai_url = rtrim($oai_url, '/'); // Remove trailing slash
    if ($oai_provider !== "ollama" && !preg_match('/\/v\d+\/?$/', $oai_url)) {
        $oai_url .= '/v1'; // If there is no version information and it's not Ollama, add /v1
    }

    $provider = $oai_provider === 'ollama' ? 'ollama' : 'openai';

    // Send response
    // 发送响应
    $proxy_url = trim(html_entity_decode(Minz_Url::display(array(
      'c' => 'ArticleSummary',
      'a' => 'proxy',
      'params' => array(
        'ajax' => 1,
        'id' => $entry_id,
      ),
    ), 'php', 'root'), ENT_QUOTES));

    $parsed_url = parse_url($proxy_url);
    if (is_array($parsed_url)) {
      $has_http_scheme = isset($parsed_url['scheme'])
        && (strtolower((string) $parsed_url['scheme']) === 'http' || strtolower((string) $parsed_url['scheme']) === 'https');
      $has_host = isset($parsed_url['host']) || strpos($proxy_url, '//') === 0;

      if ($has_http_scheme || $has_host) {
        $proxy_url = isset($parsed_url['path']) && $parsed_url['path'] !== '' ? $parsed_url['path'] : '/';
        if (isset($parsed_url['query'])) {
          $proxy_url .= '?' . $parsed_url['query'];
        }
        if (isset($parsed_url['fragment'])) {
          $proxy_url .= '#' . $parsed_url['fragment'];
        }
      }
    }

    echo json_encode(array(
      'response' => array(
        'proxy_url' => $proxy_url,
        'provider' => $provider,
      ),
      'status' => 200,
    ));
    return;
  }

  public function proxyAction(): void {
    ob_start();

    $request_id = uniqid('as_', true);

    register_shutdown_function(static function () use ($request_id): void {
      $last_error = error_get_last();
      if (!is_array($last_error)) {
        return;
      }

      $fatal_types = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
      if (!in_array($last_error['type'], $fatal_types, true)) {
        return;
      }

      error_log('[ArticleSummary][proxyAction][fatal][' . $request_id . '] '
        . $last_error['message'] . ' in ' . $last_error['file'] . ':' . $last_error['line']);

      if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');
        echo json_encode(array(
          'error' => 'internal_server_error',
          'request_id' => $request_id,
        ));
      }
    });

    try {
      $this->view->_layout(null);

      header('X-ArticleSummary-Request-Id: ' . $request_id);

      if (!FreshRSS_Auth::hasAccess()) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json');
        echo json_encode(array('error' => 'forbidden', 'request_id' => $request_id));
        exit;
      }

      if (!Minz_Request::isPost()) {
        header('HTTP/1.1 405 Method Not Allowed');
        header('Content-Type: application/json');
        echo json_encode(array('error' => 'method_not_allowed', 'request_id' => $request_id));
        exit;
      }

      if (!is_object(FreshRSS_Context::$user_conf)) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');
        echo json_encode(array('error' => 'missing_user_config', 'request_id' => $request_id));
        exit;
      }

      $entry_id = Minz_Request::paramInt('id');
      if ($entry_id <= 0) {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: application/json');
        echo json_encode(array('error' => 'missing_article_id', 'request_id' => $request_id));
        exit;
      }

      $oai_url = FreshRSS_Context::$user_conf->oai_url;
      $oai_key = FreshRSS_Context::$user_conf->oai_key;
      $oai_model = FreshRSS_Context::$user_conf->oai_model;
      $oai_prompt = FreshRSS_Context::$user_conf->oai_prompt;
      $oai_provider = FreshRSS_Context::$user_conf->oai_provider;

      if (
        $this->isEmpty($oai_url)
        || ($this->isEmpty($oai_key) && $oai_provider !== 'ollama')
        || $this->isEmpty($oai_model)
        || $this->isEmpty($oai_prompt)
      ) {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: application/json');
        echo json_encode(array('error' => 'configuration', 'request_id' => $request_id));
        exit;
      }

      if ($oai_provider !== 'openai' && $oai_provider !== 'ollama') {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: application/json');
        echo json_encode(array('error' => 'invalid_provider', 'request_id' => $request_id));
        exit;
      }

      $entry_dao = FreshRSS_Factory::createEntryDao();
      if (!is_object($entry_dao) || !method_exists($entry_dao, 'searchById')) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');
        echo json_encode(array('error' => 'entry_dao_unavailable', 'request_id' => $request_id));
        exit;
      }

      $entry = $entry_dao->searchById((int) $entry_id);
      if ($this->isEmpty($entry)) {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json');
        echo json_encode(array('error' => 'article_not_found', 'request_id' => $request_id));
        exit;
      }

      $prompt_content = 'Title: ' . $entry->title() . "\nAuthor: " . $entry->author() . "\n\nContent: " . $this->htmlToMarkdown($entry->content());

      $base_url = rtrim($oai_url, '/');
      if ($oai_provider !== 'ollama' && !preg_match('/\/v\d+\/?$/', $base_url)) {
        $base_url .= '/v1';
      }

      if ($oai_provider === 'ollama') {
        $api_url = $base_url . '/api/generate';
        $payload = array(
          'model' => $oai_model,
          'system' => $oai_prompt,
          'prompt' => $prompt_content,
          'stream' => true,
        );
        $headers = array('Content-Type: application/json');
        if (!$this->isEmpty($oai_key)) {
          $headers[] = 'Authorization: Bearer ' . $oai_key;
        }
        $success_content_type = 'application/x-ndjson; charset=UTF-8';
      } else {
        $api_url = $base_url . '/chat/completions';
        $payload = array(
          'model' => $oai_model,
          'messages' => array(
            array(
              'role' => 'system',
              'content' => $oai_prompt,
            ),
            array(
              'role' => 'user',
              'content' => $prompt_content,
            ),
          ),
          'max_tokens' => 2048,
          'temperature' => 0.7,
          'n' => 1,
          'stream' => true,
        );
        $headers = array(
          'Content-Type: application/json',
          'Authorization: Bearer ' . $oai_key,
        );
        $success_content_type = 'text/event-stream; charset=UTF-8';
      }

      if (!function_exists('curl_init')) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');
        echo json_encode(array(
          'error' => 'runtime_missing_curl',
          'message' => 'PHP cURL extension is required for proxy streaming.',
          'request_id' => $request_id,
        ));
        exit;
      }

      $payload_json = json_encode($payload);
      if ($payload_json === false) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');
        echo json_encode(array(
          'error' => 'payload_encode_failed',
          'request_id' => $request_id,
        ));
        exit;
      }

      while (ob_get_level() > 0) {
        ob_end_clean();
      }

      $header_http_code = 0;
      $upstream_error_body = '';
      $stream_started = false;

      $ch = curl_init($api_url);
      if ($ch === false) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');
        echo json_encode(array(
          'error' => 'curl_init_failed',
          'request_id' => $request_id,
        ));
        exit;
      }

      curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload_json,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HEADERFUNCTION => static function ($handle, string $header_line) use (&$header_http_code): int {
          if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', trim($header_line), $matches) === 1) {
            $header_http_code = (int) $matches[1];
          }

          return strlen($header_line);
        },
        CURLOPT_WRITEFUNCTION => static function ($handle, string $data) use (&$stream_started, &$header_http_code, &$upstream_error_body, $success_content_type): int {
          if ($header_http_code >= 400) {
            $upstream_error_body .= $data;
            return strlen($data);
          }

          if (!$stream_started) {
            header('Content-Type: ' . $success_content_type);
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');
            $stream_started = true;
          }

          echo $data;
          flush();
          return strlen($data);
        },
      ));

      $result = curl_exec($ch);
      $curl_error = curl_error($ch);
      $curl_errno = curl_errno($ch);
      $http_code = $header_http_code;
      if ($http_code === 0) {
        $http_code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
      }
      curl_close($ch);

      if ($result === false || $curl_errno !== 0) {
        error_log('[ArticleSummary][proxyAction][upstream_error][' . $request_id . '] errno=' . $curl_errno . ' message=' . $curl_error . ' url=' . $api_url);

        if ($stream_started) {
          echo "\n";
          flush();
          exit;
        }

        $is_timeout = $curl_errno === 28 || (defined('CURLE_OPERATION_TIMEDOUT') && $curl_errno === CURLE_OPERATION_TIMEDOUT);
        header($is_timeout ? 'HTTP/1.1 504 Gateway Timeout' : 'HTTP/1.1 502 Bad Gateway');
        header('Content-Type: application/json');
        echo json_encode(array(
          'error' => $is_timeout ? 'upstream_timeout' : 'upstream_request_failed',
          'message' => $curl_error,
          'request_id' => $request_id,
        ));
        exit;
      }

      if ($http_code >= 400) {
        error_log('[ArticleSummary][proxyAction][upstream_http][' . $request_id . '] status=' . $http_code . ' url=' . $api_url);

        if ($stream_started) {
          echo "\n";
          flush();
          exit;
        }

        header('HTTP/1.1 502 Bad Gateway');
        header('Content-Type: application/json');
        echo json_encode(array(
          'error' => 'upstream_http_error',
          'status' => $http_code,
          'body' => substr(trim($upstream_error_body), 0, 1024),
          'request_id' => $request_id,
        ));
        exit;
      }

      exit;
    } catch (Throwable $throwable) {
      error_log('[ArticleSummary][proxyAction][exception][' . $request_id . '] ' . get_class($throwable) . ': ' . $throwable->getMessage() . ' at ' . $throwable->getFile() . ':' . $throwable->getLine());

      if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');
        echo json_encode(array(
          'error' => 'internal_server_error',
          'request_id' => $request_id,
        ));
      } else {
        echo "\n";
        flush();
      }

      exit;
    }
  }

  /**
   * Check if a value is empty
   * 检查值是否为空
   * 
   * @param mixed $item The value to check
   * @return bool True if the value is null, false otherwise
   */
  private function isEmpty(mixed $item): bool {
    return $item === null;
  }

  /**
   * Convert HTML content to Markdown format
   * 将HTML内容转换为Markdown格式
   * 
   * @param string $content HTML content to convert
   * @return string Markdown formatted content
   */
  private function htmlToMarkdown(string $content): string {
    if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
      return strip_tags($content);
    }

    // Create DOMDocument object
    $dom = new DOMDocument();
    libxml_use_internal_errors(true); // Ignore HTML parsing errors
    $dom->loadHTML('<?xml encoding="UTF-8">' . $content);
    libxml_clear_errors();

    // Create XPath object
    $xpath = new DOMXPath($dom);

    // Get all nodes
    $nodes = $xpath->query('//body/*');

    // Process all nodes
    $markdown = '';
    foreach ($nodes as $node) {
      $markdown .= $this->processNode($node, $xpath);
    }

    // Remove extra line breaks
    $markdown = preg_replace('/(\n){3,}/', "\n\n", $markdown);
    
    return $markdown;
  }

  /**
   * Process a single DOM node and convert it to Markdown
   * 处理单个DOM节点并将其转换为Markdown
   * 
   * @param DOMNode $node The DOM node to process
   * @param DOMXPath $xpath XPath object for querying nodes
   * @param int $indentLevel Indentation level for nested elements
   * @return string Markdown formatted content
   */
  private function processNode(DOMNode $node, DOMXPath $xpath, int $indentLevel = 0): string {
    $markdown = '';

    // Process text nodes
    if ($node->nodeType === XML_TEXT_NODE) {
      $markdown .= trim($node->nodeValue);
    }

    // Process element nodes
    if ($node->nodeType === XML_ELEMENT_NODE) {
      switch ($node->nodeName) {
        case 'p':
        case 'div':
          foreach ($node->childNodes as $child) {
            $markdown .= $this->processNode($child, $xpath, $indentLevel);
          }
          $markdown .= "\n\n";
          break;
        case 'h1':
          $markdown .= "# ";
          $markdown .= $this->processNode($node->firstChild, $xpath);
          $markdown .= "\n\n";
          break;
        case 'h2':
          $markdown .= "## ";
          $markdown .= $this->processNode($node->firstChild, $xpath);
          $markdown .= "\n\n";
          break;
        case 'h3':
          $markdown .= "### ";
          $markdown .= $this->processNode($node->firstChild, $xpath);
          $markdown .= "\n\n";
          break;
        case 'h4':
          $markdown .= "#### ";
          $markdown .= $this->processNode($node->firstChild, $xpath);
          $markdown .= "\n\n";
          break;
        case 'h5':
          $markdown .= "##### ";
          $markdown .= $this->processNode($node->firstChild, $xpath);
          $markdown .= "\n\n";
          break;
        case 'h6':
          $markdown .= "###### ";
          $markdown .= $this->processNode($node->firstChild, $xpath);
          $markdown .= "\n\n";
          break;
        case 'a':
          // Convert links to code-style text instead of markdown links
          // 将链接转换为代码风格的文本而不是markdown链接
          $markdown .= "`";
          $markdown .= $this->processNode($node->firstChild, $xpath);
          $markdown .= "`";
          break;
        case 'img':
          $alt = $node->getAttribute('alt');
          $markdown .= "img: `" . $alt . "`";
          break;
        case 'strong':
        case 'b':
          $markdown .= "**";
          $markdown .= $this->processNode($node->firstChild, $xpath);
          $markdown .= "**";
          break;
        case 'em':
        case 'i':
          $markdown .= "*";
          $markdown .= $this->processNode($node->firstChild, $xpath);
          $markdown .= "*";
          break;
        case 'ul':
        case 'ol':
          $markdown .= "\n";
          foreach ($node->childNodes as $child) {
            if ($child->nodeName === 'li') {
              $markdown .= str_repeat("  ", $indentLevel) . "- ";
              $markdown .= $this->processNode($child, $xpath, $indentLevel + 1);
              $markdown .= "\n";
            }
          }
          $markdown .= "\n";
          break;
        case 'li':
          $markdown .= str_repeat("  ", $indentLevel) . "- ";
          foreach ($node->childNodes as $child) {
            $markdown .= $this->processNode($child, $xpath, $indentLevel + 1);
          }
          $markdown .= "\n";
          break;
        case 'br':
          $markdown .= "\n";
          break;
        case 'audio':
        case 'video':
          $alt = $node->getAttribute('alt');
          $markdown .= "[" . ($alt ? $alt : 'Media') . "]";
          break;
        default:
          // Tags not considered, only the text inside is kept
          // 不处理的标签，只保留内部文本
          foreach ($node->childNodes as $child) {
            $markdown .= $this->processNode($child, $xpath, $indentLevel);
          }
          break;
      }
    }

    return $markdown;
  }
}
