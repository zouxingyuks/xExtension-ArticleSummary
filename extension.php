<?php
/**
 * ArticleSummaryExtension - FreshRSS extension for AI article summarization
 * ArticleSummaryExtension - 用于AI文章总结的FreshRSS插件
 */
class ArticleSummaryExtension extends Minz_Extension
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
  public function init()
  {
    // Register hook to add summary button to each article
    // 注册钩子，为每篇文章添加总结按钮
    $this->registerHook('entry_before_display', array($this, 'addSummaryButton'));
    
    // Register controller for handling summarization requests
    // 注册控制器以处理总结请求
    $this->registerController('ArticleSummary');
    
    // Register translations
    // 注册翻译文件
    $this->registerTranslates(__DIR__ . '/i18n');
    
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
  public function addSummaryButton($entry)
  {
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
  public function handleConfigureAction()
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
