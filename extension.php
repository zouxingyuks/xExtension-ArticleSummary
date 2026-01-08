<?php
/**
 * ArticleSummaryExtension - FreshRSS extension for AI article summarization
 * ArticleSummaryExtension - 用于AI文章总结的FreshRSS插件
 */
final class ArticleSummaryExtension extends Minz_Extension
{
  /**
   * Content Security Policy settings
   * 内容安全策略设置
   */
  protected array $csp_policies = [
    'default-src' => '*',
  ];

  /**
   * Initialize the extension
   * 初始化插件
   */
  #[\Override]
  public function init(): void
  {
    // Register hook to add summary button to each article
    // 注册钩子，为每篇文章添加总结按钮
    // 使用字符串方式以兼容旧版本 FreshRSS
    $this->registerHook('entry_before_display', [$this, 'addSummaryButton']);
    
    // Register controller for handling summarization requests
    // 注册控制器以处理总结请求
    $this->registerController('ArticleSummary');
    
    // Register translations
    // 注册翻译文件
    $this->registerTranslates(__DIR__ . '/i18n');
    
    // Set default prompt if not already set
    // 如果没有设置默认提示词，则设置默认值
    if (empty(FreshRSS_Context::$user_conf->oai_prompt)) {
      FreshRSS_Context::$user_conf->oai_prompt = _t('ArticleSummary.config.default_prompt');
      FreshRSS_Context::$user_conf->save();
    }
    
    // Append static resources
    // 附加静态资源
    Minz_View::appendStyle($this->getFileUrl('style.css', 'css'));
    Minz_View::appendScript($this->getFileUrl('axios.js', 'js'));
    Minz_View::appendScript($this->getFileUrl('marked.js', 'js'));
    Minz_View::appendScript($this->getFileUrl('script.js', 'js'));
  }

  /**
   * Add summary button to article content
   * 向文章内容添加总结按钮
   * 
   * @param FreshRSS_Entry $entry The article entry
   * @return FreshRSS_Entry Modified article entry with summary button
   */
  public function addSummaryButton(FreshRSS_Entry $entry): FreshRSS_Entry
  {
    // Check if current request is for RSS feed
    // 检查当前请求是否为RSS feed
    if (Minz_Request::param('a') === 'rss') {
      return $entry; // Return original entry without modifying it for RSS
    }
    
    // Generate URL for summarization request
    // 生成总结请求的URL
    $url_summary = Minz_Url::display(array(
      'c' => 'ArticleSummary',
      'a' => 'summarize',
      'params' => array(
        'id' => $entry->id()
      )
    ));

    // Get translated texts
    // 获取翻译文本
    $summarizeText = _t('ArticleSummary.button.summarize');
    $loadingText = _t('ArticleSummary.status.loading');
    $errorText = _t('ArticleSummary.status.error');
    $requestFailedText = _t('ArticleSummary.status.request_failed');

    // Add summary button and container to article content with translated texts as data attributes
    // 向文章内容添加总结按钮和容器，并将翻译文本作为data属性
    $entry->_content(
      '<div class="oai-summary-wrap">'
      . '<button data-request="' . $url_summary . '" '
      . 'data-summarize-text="' . $summarizeText . '" '
      . 'data-loading-text="' . $loadingText . '" '
      . 'data-error-text="' . $errorText . '" '
      . 'data-request-failed-text="' . $requestFailedText . '" '
      . 'class="oai-summary-btn"></button>'
      . '<div class="oai-summary-content"></div>'
      . '</div>'
      . $entry->content()
    );
    
    return $entry;
  }

  /**
   * Handle configuration action
   * 处理配置动作
   */
  public function handleConfigureAction(): void
  {
    if (Minz_Request::isPost()) {
      // Save user configuration from form inputs
      // 从表单输入保存用户配置
      FreshRSS_Context::$user_conf->oai_url = Minz_Request::param('oai_url', '');
      FreshRSS_Context::$user_conf->oai_key = Minz_Request::param('oai_key', '');
      FreshRSS_Context::$user_conf->oai_model = Minz_Request::param('oai_model', '');
      FreshRSS_Context::$user_conf->oai_prompt = Minz_Request::param('oai_prompt', '');
      FreshRSS_Context::$user_conf->oai_provider = Minz_Request::param('oai_provider', '');
      FreshRSS_Context::$user_conf->save();
    }
  }
}
