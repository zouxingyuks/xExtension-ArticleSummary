/**
 * Article Summary Extension - Frontend JavaScript
 * Article Summary Extension - 前端JavaScript
 */

// Initialize summarize buttons when DOM is loaded
// 当DOM加载完成时初始化总结按钮
if (document.readyState && document.readyState !== 'loading') {
  configureSummarizeButtons();
} else {
  document.addEventListener('DOMContentLoaded', configureSummarizeButtons, false);
}

function getSummaryTextConfig(button) {
  return {
    summarize: button?.dataset?.summarizeText || 'Summarize article',
    summarizeTitle: button?.dataset?.summarizeTitleText || button?.dataset?.summarizeText || 'Summarize article',
    loading: button?.dataset?.loadingText || 'Generating summary…',
    error: button?.dataset?.errorText || 'Something went wrong.',
    requestFailed: button?.dataset?.requestFailedText || 'Couldn\'t start the summary request.',
    timeout: button?.dataset?.timeoutText || 'The summary took too long to finish. Please try again.',
    cancelled: button?.dataset?.cancelledText || 'The summary request was cancelled before it finished.',
    partialError: button?.dataset?.partialErrorText || 'The summary stopped early. The partial result is shown below.',
    configuration: button?.dataset?.configurationText || 'Open the extension settings and complete the required fields before requesting a summary.',
    invalidRequest: button?.dataset?.invalidRequestText || 'This article summary action is currently unavailable. Please refresh the page and try again.',
    invalidProxy: button?.dataset?.invalidProxyText || 'The summary service returned an invalid response. Please try again.',
    emptySummary: button?.dataset?.emptySummaryText || 'The AI service finished without returning any summary text.',
  };
}

function hydrateSummaryButton(button) {
  if (!(button instanceof HTMLElement)) {
    return;
  }

  const texts = getSummaryTextConfig(button);
  button.textContent = texts.summarize;
  button.setAttribute('aria-label', texts.summarizeTitle);
  button.setAttribute('title', texts.summarizeTitle);
}

function hydrateSummaryButtons(root = document) {
  if (!(root instanceof Element || root instanceof Document)) {
    return;
  }

  root.querySelectorAll('.oai-summary-btn').forEach(hydrateSummaryButton);
}

/**
 * Configure event listeners for summarize buttons
 * 为总结按钮配置事件监听器
 */
function configureSummarizeButtons() {
  const globalContainer = document.getElementById('global');
  if (!globalContainer) {
    return;
  }

  hydrateSummaryButtons(document);

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node instanceof Element) {
          if (node.matches('.oai-summary-btn')) {
            hydrateSummaryButton(node);
          } else {
            hydrateSummaryButtons(node);
          }
        }
      });
    });
  });
  observer.observe(globalContainer, { childList: true, subtree: true });

  globalContainer.addEventListener('click', function (e) {
    let target = e.target;
    while (target && target !== this) {
      // Handle summarize button click
      // 处理总结按钮点击
      if (target.matches('.oai-summary-btn')) {
        e.preventDefault();
        e.stopPropagation();
        if (target.dataset.request) {
          summarizeButtonClick(target);
        }
        break;
      }
      target = target.parentNode;
    }
  }, false);
}

/**
 * Set the state of the AI summary component
 * 设置AI总结组件的状态
 * 
 * @param {HTMLElement} container - The summary container element
 * @param {number} statusType - Status type: 1=loading, 2=error, 0=success
 * @param {string} statusMsg - Status message to display
 * @param {string} summaryText - Summary text to display when completed
 */
function setOaiState(container, statusType, statusMsg, summaryText) {
  const button = container.querySelector('.oai-summary-btn');
  const content = container.querySelector('.oai-summary-content');
  const texts = getSummaryTextConfig(button);
  
  // Set different states based on statusType
  // 根据statusType设置不同状态
  if (statusType === 1) {
    // Loading state
    // 加载状态
    container.classList.add('oai-loading');
    container.classList.remove('oai-error');
    content.textContent = statusMsg;
    button.textContent = texts.loading;
    button.disabled = true;
  } else if (statusType === 2) {
    // Error state
    // 错误状态
    container.classList.remove('oai-loading');
    container.classList.add('oai-error');
    content.textContent = statusMsg;
    button.textContent = texts.summarize;
    button.disabled = false;
  } else {
    // Success state
    // 成功状态
    container.classList.remove('oai-loading');
    container.classList.remove('oai-error');
    button.textContent = texts.summarize;
    if (statusMsg === 'finish'){
      button.disabled = false;
    }
  }

  // Update content with summary text if provided
  // 如果提供了总结文本，则更新内容
  if (summaryText) {
    content.innerHTML = summaryText.replace(/(?:\r\n|\r|\n)/g, '<br>');
  }
}

function clearSummaryNote(container) {
  const note = container.querySelector('.oai-summary-note');
  if (note) {
    note.remove();
  }
}

function showSummaryNote(container, message, tone = 'info') {
  clearSummaryNote(container);

  const note = document.createElement('div');
  note.className = `oai-summary-note oai-summary-note-${tone}`;
  note.textContent = message;
  container.appendChild(note);
}

function mapBackendErrorMessage(rawMessage, texts) {
  const normalized = String(rawMessage || '').trim();
  if (normalized === 'configuration') {
    return texts.configuration;
  }

  if (normalized === 'Missing request URL' || normalized === 'Invalid request URL' || normalized === 'Blocked cross-origin summarize URL') {
    return texts.invalidRequest;
  }

  if (normalized === 'Missing proxy URL' || normalized === 'Invalid proxy URL' || normalized === 'Blocked cross-origin proxy URL') {
    return texts.invalidProxy;
  }

  return normalized;
}

function buildFriendlyErrorMessage(container, error) {
  const texts = getSummaryTextConfig(container.querySelector('.oai-summary-btn'));
  const rawMessage = extractErrorMessage(error);
  const mappedMessage = mapBackendErrorMessage(rawMessage, texts);
  if (mappedMessage.includes('upstream_timeout')) {
    return texts.timeout;
  }

  if (mappedMessage.includes('AbortError')) {
    return texts.cancelled;
  }

  return mappedMessage || texts.error;
}

function extractStreamChunkText(json) {
  if (json?.choices?.[0]?.delta?.content) {
    return json.choices[0].delta.content;
  }

  if (json?.type === 'response.output_text.delta' && typeof json.delta === 'string') {
    return json.delta;
  }

  return '';
}

function isStreamComplete(json) {
  return json?.type === 'response.completed';
}

function createStreamState() {
  return { text: '' };
}

function extractErrorMessage(error) {
  const responsePayload = error?.response?.data;
  const requestId = responsePayload?.request_id;
  const baseMessage = responsePayload?.error?.message ||
                      responsePayload?.message ||
                      responsePayload?.error ||
                      error?.message ||
                      'Request Failed';

  if (requestId) {
    return `${baseMessage} [${requestId}]`;
  }

  return baseMessage;
}

function buildFetchError(response, fallbackStatusText = 'Request Failed') {
  const requestId = response.headers.get('x-articlesummary-request-id');
  const base = `${fallbackStatusText} (${response.status})`;
  return requestId ? `${base} [${requestId}]` : base;
}

function normalizeSameOriginUrl(rawUrl, missingMessage, invalidMessage, blockedMessage) {
  const decoded = String(rawUrl || '').replaceAll('&amp;', '&').trim();
  if (!decoded) {
    throw new Error(missingMessage);
  }

  let parsed;
  try {
    parsed = new URL(decoded, window.location.origin);
  } catch {
    throw new Error(invalidMessage);
  }

  if (parsed.origin !== window.location.origin) {
    throw new Error(blockedMessage);
  }

  return `${parsed.pathname}${parsed.search}${parsed.hash}`;
}

function normalizeProxyUrl(rawProxyUrl, invalidProxyMessage) {
  return normalizeSameOriginUrl(
    rawProxyUrl,
    invalidProxyMessage,
    invalidProxyMessage,
    invalidProxyMessage
  );
}

/**
 * Handle summarize button click event
 * 处理总结按钮点击事件
 * 
 * @param {HTMLElement} target - The clicked button element
 */
async function summarizeButtonClick(target) {
  var container = target.parentNode;
  const texts = getSummaryTextConfig(target);
  
  // Prevent multiple requests while loading
  // 加载时防止多次请求
  if (container.classList.contains('oai-loading')) {
    return;
  }

  // Set loading state
  // 设置加载状态
  const loadingText = container.querySelector('.oai-summary-btn').dataset.loadingText;
  clearSummaryNote(container);
  setOaiState(container, 1, loadingText, null);

  // Get the request URL and prepare data
  // 获取请求URL并准备数据
  var url = normalizeSameOriginUrl(
    target.dataset.request,
    texts.invalidRequest,
    texts.invalidRequest,
    texts.invalidRequest
  );
  var data = {
    ajax: true,
    _csrf: context.csrf
  };

  try {
    // Send request to PHP backend
    // 向PHP后端发送请求
    const response = await axios.post(url, data, {
      headers: {
        'Content-Type': 'application/json'
      }
    });

    const xresp = response.data;

    // Check if response is valid
    // 检查响应是否有效
    if (response.status !== 200 || !xresp.response || !xresp.response.proxy_url) {
      const requestFailedText = container.querySelector('.oai-summary-btn').dataset.requestFailedText;
      throw new Error(requestFailedText);
    }

    // Handle error response
    // 处理错误响应
    if (xresp.response.error) {
      setOaiState(container, 2, mapBackendErrorMessage(xresp.response.error, texts), null);
    } else {
      const proxyUrl = normalizeProxyUrl(xresp.response.proxy_url, texts.invalidProxy);
      const oaiProvider = xresp.response.provider;
      
      // Send request to appropriate AI provider
      // 向合适的AI提供商发送请求
      if (oaiProvider === 'openai') {
        await sendOpenAIRequest(container, proxyUrl);
      } else {
        await sendOllamaRequest(container, proxyUrl);
      }
    }
  } catch (error) {
    console.error(error);
    const errorMsg = buildFriendlyErrorMessage(container, error);
    setOaiState(container, 2, errorMsg, null);
  }
}

async function sendOpenAIRequest(container, proxyUrl) {
  const streamState = createStreamState();
  const texts = getSummaryTextConfig(container.querySelector('.oai-summary-btn'));
  try {
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), 60000);
    let response;
    try {
      response = await fetch(proxyUrl, {
        method: 'POST',
        mode: 'same-origin',
        credentials: 'same-origin',
        signal: controller.signal,
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `_csrf=${encodeURIComponent(context.csrf)}&ajax=1`
      });
    } finally {
      window.clearTimeout(timeoutId);
    }

    const responseContentType = response.headers.get('content-type') || '';
    if (responseContentType.includes('application/json')) {
      const errorPayload = await response.json();
      const baseMsg = errorPayload?.message || errorPayload?.error || `${texts.requestFailed} (${response.status})`;
      const errorMsg = errorPayload?.request_id ? `${baseMsg} [${errorPayload.request_id}]` : baseMsg;
      throw new Error(errorMsg);
    }

    if (!response.ok) {
      const rawBody = await response.text();
      const snippet = rawBody.trim().slice(0, 160);
      const message = buildFetchError(response, texts.requestFailed);
      throw new Error(snippet ? `${message}: ${snippet}` : message);
    }

    // Process streaming response with buffer
    // 使用缓冲区处理流式响应
    const reader = response.body.getReader();
    const decoder = new TextDecoder('utf-8');
    let buffer = '';

    while (true) {
      const { done, value } = await reader.read();
      if (done) {
        if (buffer.trim()) {
          buffer = processOpenAIStreamBuffer(container, buffer, streamState, true);
        }
        // Complete the summary process
        // 完成总结过程
        if (!streamState.text.trim()) {
          setOaiState(container, 2, texts.emptySummary, null);
        } else {
          setOaiState(container, 0, 'finish', null);
        }
        break;
      }
      
      // Append new data to buffer
      // 将新数据添加到缓冲区
      buffer += decoder.decode(value, { stream: true });
      
      // Process each line in buffer
      // 处理缓冲区中的每一行
      buffer = processOpenAIStreamBuffer(container, buffer, streamState);
    }
  } catch (error) {
    console.error(error);
    const errorMsg = buildFriendlyErrorMessage(container, error);
    if (streamState.text) {
      setOaiState(container, 0, 'finish', marked.parse(streamState.text));
      showSummaryNote(container, `${texts.partialError} ${errorMsg}`.trim(), 'error');
    } else {
      setOaiState(container, 2, errorMsg, null);
    }
  }
}

function processOpenAIStreamBuffer(container, buffer, streamState, flushTail = false) {
  let nextBuffer = buffer;
  let endIndex = nextBuffer.indexOf('\n');

  while (endIndex !== -1) {
    const line = nextBuffer.slice(0, endIndex).trim();
    nextBuffer = nextBuffer.slice(endIndex + 1);

    if (!line || line.startsWith('event:')) {
      endIndex = nextBuffer.indexOf('\n');
      continue;
    }

    if (line === 'data: [DONE]') {
      break;
    }

    if (line.startsWith('data: ')) {
      const jsonString = line.slice(6).trim();
      try {
        const json = JSON.parse(jsonString);
        const upstreamError = json?.error?.message || json?.message || json?.error;
        if (upstreamError) {
          throw new Error(typeof upstreamError === 'string' ? upstreamError : JSON.stringify(upstreamError));
        }

        const deltaText = extractStreamChunkText(json);
        if (deltaText) {
          streamState.text += deltaText;
          setOaiState(container, 0, null, marked.parse(streamState.text));
        }

        if (isStreamComplete(json)) {
          break;
        }
      } catch (e) {
        console.error('Error parsing OpenAI response:', e, 'Line:', jsonString);
        if (!flushTail) {
          throw e;
        }
      }
    }

    endIndex = nextBuffer.indexOf('\n');
  }

  return nextBuffer;
}


async function sendOllamaRequest(container, proxyUrl){
  const streamState = createStreamState();
  const texts = getSummaryTextConfig(container.querySelector('.oai-summary-btn'));
  try {
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), 60000);
    let response;
    try {
      response = await fetch(proxyUrl, {
        method: 'POST',
        mode: 'same-origin',
        credentials: 'same-origin',
        signal: controller.signal,
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `_csrf=${encodeURIComponent(context.csrf)}&ajax=1`
      });
    } finally {
      window.clearTimeout(timeoutId);
    }

    const responseContentType = response.headers.get('content-type') || '';
    if (responseContentType.includes('application/json')) {
      const errorPayload = await response.json();
      const baseMsg = errorPayload?.message || errorPayload?.error || `${texts.requestFailed} (${response.status})`;
      const errorMsg = errorPayload?.request_id ? `${baseMsg} [${errorPayload.request_id}]` : baseMsg;
      throw new Error(errorMsg);
    }

    if (!response.ok) {
      const rawBody = await response.text();
      const snippet = rawBody.trim().slice(0, 160);
      const message = buildFetchError(response, texts.requestFailed);
      throw new Error(snippet ? `${message}: ${snippet}` : message);
    }

    // Process streaming response with buffer
    // 使用缓冲区处理流式响应
    const reader = response.body.getReader();
    const decoder = new TextDecoder('utf-8');
    let buffer = '';

    while (true) {
      const { done, value } = await reader.read();
      if (done) {
        if (buffer.trim()) {
          buffer = processOllamaStreamBuffer(container, buffer, streamState, true);
        }
        // Complete the summary process
        // 完成总结过程
        if (!streamState.text.trim()) {
          setOaiState(container, 2, texts.emptySummary, null);
        } else {
          setOaiState(container, 0, 'finish', null);
        }
        break;
      }
      
      // Append new data to buffer
      // 将新数据添加到缓冲区
      buffer += decoder.decode(value, { stream: true });
      
      // Process complete JSON objects from the buffer
      // 从缓冲区处理完整的JSON对象
      buffer = processOllamaStreamBuffer(container, buffer, streamState);
    }
  } catch (error) {
    console.error(error);
    const errorMsg = buildFriendlyErrorMessage(container, error);
    if (streamState.text) {
      setOaiState(container, 0, 'finish', marked.parse(streamState.text));
      showSummaryNote(container, `${texts.partialError} ${errorMsg}`.trim(), 'error');
    } else {
      setOaiState(container, 2, errorMsg, null);
    }
  }
}

function processOllamaStreamBuffer(container, buffer, streamState, flushTail = false) {
  let nextBuffer = buffer;
  let endIndex = nextBuffer.indexOf('\n');

  while (endIndex !== -1) {
    const jsonString = nextBuffer.slice(0, endIndex).trim();
    if (!jsonString) {
      nextBuffer = nextBuffer.slice(endIndex + 1);
      endIndex = nextBuffer.indexOf('\n');
      continue;
    }

    try {
      const json = JSON.parse(jsonString);
      const upstreamError = json?.error?.message || json?.message || json?.error;
      if (upstreamError) {
        throw new Error(typeof upstreamError === 'string' ? upstreamError : JSON.stringify(upstreamError));
      }

      if (json.response) {
        streamState.text += json.response;
        setOaiState(container, 0, null, marked.parse(streamState.text));
      }

      nextBuffer = nextBuffer.slice(endIndex + 1);
    } catch (e) {
      if (!flushTail) {
        break;
      }

      console.error('Error parsing JSON:', e, 'Chunk:', jsonString);
      nextBuffer = nextBuffer.slice(endIndex + 1);
    }

    endIndex = nextBuffer.indexOf('\n');
  }

  return nextBuffer;
}
