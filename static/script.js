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
    for (var target = e.target; target && target != this; target = target.parentNode) {
      
      // Handle article header click to add text to summary button
      // 处理文章标题点击，为总结按钮添加文本
      if (target.matches('.flux_header')) {
        target.nextElementSibling.querySelector('.oai-summary-btn').innerHTML = 'Summarize'
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
  setOaiState(container, 1, 'Loading...', null);

  // Get the request URL and prepare data
  // 获取请求URL并准备数据
  var url = target.dataset.request;
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
    console.log(xresp);

    // Check if response is valid
    // 检查响应是否有效
    if (response.status !== 200 || !xresp.response || !xresp.response.data) {
      throw new Error('Request Failed');
    }

    // Handle error response
    // 处理错误响应
    if (xresp.response.error) {
      setOaiState(container, 2, xresp.response.data, null);
    } else {
      // Parse parameters returned by PHP
      // 解析PHP返回的参数
      const oaiParams = xresp.response.data;
      const oaiProvider = xresp.response.provider;
      
      // Send request to appropriate AI provider
      // 向合适的AI提供商发送请求
      if (oaiProvider === 'openai') {
        await sendOpenAIRequest(container, oaiParams);
      } else {
        await sendOllamaRequest(container, oaiParams);
      }
    }
  } catch (error) {
    console.error(error);
    // Show more specific error message
    // 显示更具体的错误信息
    const errorMsg = error.response?.data?.error?.message || 
                    error.message || 
                    'Request Failed';
    setOaiState(container, 2, errorMsg, null);
  }
}

/**
 * Send summarization request to OpenAI API
 * 向OpenAI API发送总结请求
 * 
 * @param {HTMLElement} container - The summary container element
 * @param {Object} oaiParams - OpenAI API parameters
 */
async function sendOpenAIRequest(container, oaiParams) {
  try {
    // Prepare request body by removing URL and key
    // 准备请求体，移除URL和密钥
    let body = JSON.parse(JSON.stringify(oaiParams));
    delete body['oai_url'];
    delete body['oai_key'];	  
    
    // Send POST request to OpenAI API
    // 向OpenAI API发送POST请求
    const response = await fetch(oaiParams.oai_url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${oaiParams.oai_key}`
      },
      body: JSON.stringify(body)
    });

    if (!response.ok) {
      throw new Error('Request Failed');
    }

    // Process streaming response
    // 处理流式响应
    const reader = response.body.getReader();
    const decoder = new TextDecoder('utf-8');

    while (true) {
      const { done, value } = await reader.read();
      if (done) {
        // Complete the summary process
        // 完成总结过程
        setOaiState(container, 0, 'finish', null);
        break;
      }

      // Decode and parse the response chunk
      // 解码并解析响应块
      const chunk = decoder.decode(value, { stream: true });
      let text = '';
      try {
        text = JSON.parse(chunk)?.choices[0]?.message?.content || '';
        // Update the summary content
        // 更新总结内容
        setOaiState(container, 0, null, marked.parse(text));
      } catch (parseError) {
        // Ignore parsing errors for incomplete chunks
        // 忽略不完整块的解析错误
      }
    }
  } catch (error) {
    console.error(error);
    // Show more specific error message
    // 显示更具体的错误信息
    const errorMsg = error.response?.data?.error?.message || 
                    error.message || 
                    'Request Failed';
    setOaiState(container, 2, errorMsg, null);
  }
}


/**
 * Send summarization request to Ollama API
 * 向Ollama API发送总结请求
 * 
 * @param {HTMLElement} container - The summary container element
 * @param {Object} oaiParams - Ollama API parameters
 */
async function sendOllamaRequest(container, oaiParams){
  try {
    // Send POST request to Ollama API
    // 向Ollama API发送POST请求
    const response = await fetch(oaiParams.oai_url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${oaiParams.oai_key}`
      },
      body: JSON.stringify(oaiParams)
    });

    if (!response.ok) {
      throw new Error('Request Failed');
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
      let endIndex;
      while ((endIndex = buffer.indexOf('\n')) !== -1) {
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
      }
    }
  } catch (error) {
    console.error(error);
    // Show more specific error message
    // 显示更具体的错误信息
    const errorMsg = error.response?.data?.error?.message || 
                    error.message || 
                    'Request Failed';
    setOaiState(container, 2, errorMsg, null);
  }
}
