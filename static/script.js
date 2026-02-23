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

/**
 * Configure event listeners for summarize buttons
 * 为总结按钮配置事件监听器
 */
function configureSummarizeButtons() {
  document.getElementById('global').addEventListener('click', function (e) {
    let target = e.target;
    while (target && target !== this) {
      
      // Handle article header click to add text to summary button
      // 处理文章标题点击，为总结按钮添加文本
      if (target.matches('.flux_header')) {
        const button = target.nextElementSibling.querySelector('.oai-summary-btn');
        button.innerHTML = button.dataset.summarizeText;
      }

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
  
  // Set different states based on statusType
  // 根据statusType设置不同状态
  if (statusType === 1) {
    // Loading state
    // 加载状态
    container.classList.add('oai-loading');
    container.classList.remove('oai-error');
    content.innerHTML = statusMsg;
    button.disabled = true;
  } else if (statusType === 2) {
    // Error state
    // 错误状态
    container.classList.remove('oai-loading');
    container.classList.add('oai-error');
    content.innerHTML = statusMsg;
    button.disabled = false;
  } else {
    // Success state
    // 成功状态
    container.classList.remove('oai-loading');
    container.classList.remove('oai-error');
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

function normalizeProxyUrl(rawProxyUrl) {
  return normalizeSameOriginUrl(
    rawProxyUrl,
    'Missing proxy URL',
    'Invalid proxy URL',
    'Blocked cross-origin proxy URL'
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
  
  // Prevent multiple requests while loading
  // 加载时防止多次请求
  if (container.classList.contains('oai-loading')) {
    return;
  }

  // Set loading state
  // 设置加载状态
  const loadingText = container.querySelector('.oai-summary-btn').dataset.loadingText;
  setOaiState(container, 1, loadingText, null);

  // Get the request URL and prepare data
  // 获取请求URL并准备数据
  var url = normalizeSameOriginUrl(
    target.dataset.request,
    'Missing request URL',
    'Invalid request URL',
    'Blocked cross-origin summarize URL'
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
      setOaiState(container, 2, xresp.response.error, null);
    } else {
      const proxyUrl = normalizeProxyUrl(xresp.response.proxy_url);
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
    const errorMsg = extractErrorMessage(error);
    setOaiState(container, 2, errorMsg, null);
  }
}

async function sendOpenAIRequest(container, proxyUrl) {
  try {
    const response = await fetch(proxyUrl, {
      method: 'POST',
      mode: 'same-origin',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: `_csrf=${encodeURIComponent(context.csrf)}&ajax=1`
    });

    const responseContentType = response.headers.get('content-type') || '';
    if (responseContentType.includes('application/json')) {
      const errorPayload = await response.json();
      const baseMsg = errorPayload?.message || errorPayload?.error || `Request Failed (${response.status})`;
      const errorMsg = errorPayload?.request_id ? `${baseMsg} [${errorPayload.request_id}]` : baseMsg;
      throw new Error(errorMsg);
    }

    if (!response.ok) {
      const rawBody = await response.text();
      const snippet = rawBody.trim().slice(0, 160);
      const message = buildFetchError(response, 'Request Failed');
      throw new Error(snippet ? `${message}: ${snippet}` : message);
    }

    // Process streaming response with buffer
    // 使用缓冲区处理流式响应
    const reader = response.body.getReader();
    const decoder = new TextDecoder('utf-8');
    let text = '';
    let buffer = '';

    while (true) {
      const { done, value } = await reader.read();
      if (done) {
        // Complete the summary process
        // 完成总结过程
        setOaiState(container, 0, 'finish', null);
        break;
      }
      
      // Append new data to buffer
      // 将新数据添加到缓冲区
      buffer += decoder.decode(value, { stream: true });
      
      // Process each line in buffer
      // 处理缓冲区中的每一行
      let endIndex = buffer.indexOf('\n');
      while (endIndex !== -1) {
        const line = buffer.slice(0, endIndex).trim();
        buffer = buffer.slice(endIndex + 1);
        
        // Skip empty lines
        // 跳过空行
        if (!line) continue;
        
        // Check for done signal
        // 检查是否完成
        if (line === 'data: [DONE]') {
          break;
        }
        
        // Extract JSON data from line (remove "data: " prefix)
        // 从行中提取JSON数据（移除"data: "前缀）
        if (line.startsWith('data: ')) {
          const jsonString = line.slice(6).trim();
          try {
            const json = JSON.parse(jsonString);
            if (json.choices?.[0]?.delta) {
              const delta = json.choices[0].delta;
              if (delta.content) {
                text += delta.content;
                // Update the summary content
                // 更新总结内容
                setOaiState(container, 0, null, marked.parse(text));
              }
            }
          } catch (e) {
            // If JSON parsing fails, output the error
            // 如果JSON解析失败，输出错误
            console.error('Error parsing OpenAI response:', e, 'Line:', jsonString);
          }
        }

        endIndex = buffer.indexOf('\n');
      }
    }
  } catch (error) {
    console.error(error);
    const errorMsg = extractErrorMessage(error);
    setOaiState(container, 2, errorMsg, null);
  }
}


async function sendOllamaRequest(container, proxyUrl){
  try {
    const response = await fetch(proxyUrl, {
      method: 'POST',
      mode: 'same-origin',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: `_csrf=${encodeURIComponent(context.csrf)}&ajax=1`
    });

    const responseContentType = response.headers.get('content-type') || '';
    if (responseContentType.includes('application/json')) {
      const errorPayload = await response.json();
      const baseMsg = errorPayload?.message || errorPayload?.error || `Request Failed (${response.status})`;
      const errorMsg = errorPayload?.request_id ? `${baseMsg} [${errorPayload.request_id}]` : baseMsg;
      throw new Error(errorMsg);
    }

    if (!response.ok) {
      const rawBody = await response.text();
      const snippet = rawBody.trim().slice(0, 160);
      const message = buildFetchError(response, 'Request Failed');
      throw new Error(snippet ? `${message}: ${snippet}` : message);
    }

    // Process streaming response with buffer
    // 使用缓冲区处理流式响应
    const reader = response.body.getReader();
    const decoder = new TextDecoder('utf-8');
    let text = '';
    let buffer = '';

    while (true) {
      const { done, value } = await reader.read();
      if (done) {
        // Complete the summary process
        // 完成总结过程
        setOaiState(container, 0, 'finish', null);
        break;
      }
      
      // Append new data to buffer
      // 将新数据添加到缓冲区
      buffer += decoder.decode(value, { stream: true });
      
      // Process complete JSON objects from the buffer
      // 从缓冲区处理完整的JSON对象
      let endIndex = buffer.indexOf('\n');
      while (endIndex !== -1) {
        const jsonString = buffer.slice(0, endIndex).trim();
        try {
          if (jsonString) {
            const json = JSON.parse(jsonString);
            text += json.response;
            
            // Update the summary content
            // 更新总结内容
            setOaiState(container, 0, null, marked.parse(text));
          }
        } catch (e) {
          // If JSON parsing fails, output the error and keep the chunk for future attempts
          // 如果JSON解析失败，输出错误并保留该块用于后续尝试
          console.error('Error parsing JSON:', e, 'Chunk:', jsonString);
        }
        
        // Remove the processed part from the buffer
        // 从缓冲区移除已处理的部分
        buffer = buffer.slice(endIndex + 1); // +1 to remove the newline character
        endIndex = buffer.indexOf('\n');
      }
    }
  } catch (error) {
    console.error(error);
    const errorMsg = extractErrorMessage(error);
    setOaiState(container, 2, errorMsg, null);
  }
}
