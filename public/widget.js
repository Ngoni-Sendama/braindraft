// console.log("Hello, World!");
(function () {
  'use strict';

  /**
   * Simple embeddable widget wrapper
   * --------------------------------
   * Goal:
   * - Load from any website using one <script> tag
   * - Read bot number as the public unique ID
   * - Fetch branding/config from your backend
   * - Render a floating chat button + panel
   * - Allow easy color/brand customization only
   *
   * Example install:
   * <script
   *   src="https://your-domain.com/widget.js"
   *   data-bot-id="1001"
   *   data-api-base="https://your-domain.com/api/widget"
   * ></script>
   */

  const currentScript = document.currentScript;

  if (!currentScript) {
    console.error('[Widget] Unable to detect current script element.');
    return;
  }

  const botId = currentScript.getAttribute('data-bot-id');
  const apiBase = currentScript.getAttribute('data-api-base') || 'http://localhost:8000/api/widget';
  const position = currentScript.getAttribute('data-position') || 'bottom-right';

  if (!botId) {
    console.error('[Widget] Missing required data-bot-id attribute.');
    return;
  }

  const state = {
    isOpen: false,
    isLoaded: false,
    isLoading: false,
    config: null,
    elements: {
      root: null,
      button: null,
      panel: null,
      header: null,
      messages: null,
      form: null,
      input: null,
      sendButton: null,
      body: null,
      footer: null,
      status: null,
    },
  };

  function createEl(tag, className, text) {
    const el = document.createElement(tag);
    if (className) el.className = className;
    if (typeof text === 'string') el.textContent = text;
    return el;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function injectStyles() {
    if (document.getElementById('simple-widget-styles')) {
      return;
    }

    const style = document.createElement('style');
    style.id = 'simple-widget-styles';
    style.textContent = `
      .sw-root {
        position: fixed;
        z-index: 999999;
        font-family: Arial, sans-serif;
      }

      .sw-root.bottom-right {
        right: 20px;
        bottom: 20px;
      }

      .sw-root.bottom-left {
        left: 20px;
        bottom: 20px;
      }

      .sw-button {
        width: 60px;
        height: 60px;
        border: none;
        border-radius: 999px;
        cursor: pointer;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.18);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
      }

      .sw-panel {
        width: 360px;
        max-width: calc(100vw - 32px);
        height: 520px;
        max-height: calc(100vh - 100px);
        background: #ffffff;
        border-radius: 18px;
        box-shadow: 0 18px 50px rgba(0, 0, 0, 0.18);
        overflow: hidden;
        display: none;
        flex-direction: column;
        margin-bottom: 14px;
      }

      .sw-panel.open {
        display: flex;
      }

      .sw-header {
        padding: 16px;
        color: #ffffff;
      }

      .sw-brand-row {
        display: flex;
        align-items: center;
        gap: 12px;
      }

      .sw-logo {
        width: 38px;
        height: 38px;
        border-radius: 999px;
        background: rgba(255,255,255,0.16);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        flex-shrink: 0;
      }

      .sw-logo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }

      .sw-title {
        font-size: 16px;
        font-weight: 700;
        margin: 0;
      }

      .sw-subtitle {
        font-size: 12px;
        opacity: 0.9;
        margin-top: 4px;
      }

      .sw-body {
        flex: 1;
        background: #f7f7f9;
        padding: 14px;
        overflow-y: auto;
      }

      .sw-message {
        max-width: 82%;
        margin-bottom: 10px;
        padding: 10px 12px;
        border-radius: 14px;
        font-size: 14px;
        line-height: 1.45;
        white-space: pre-wrap;
        word-break: break-word;
      }

      .sw-message.bot {
        background: #ffffff;
        color: #111827;
        border-top-left-radius: 4px;
      }

      .sw-message.user {
        background: #111827;
        color: #ffffff;
        margin-left: auto;
        border-top-right-radius: 4px;
      }

      .sw-footer {
        border-top: 1px solid #e5e7eb;
        padding: 12px;
        background: #ffffff;
      }

      .sw-form {
        display: flex;
        gap: 8px;
      }

      .sw-input {
        flex: 1;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        padding: 12px;
        font-size: 14px;
        outline: none;
      }

      .sw-input:focus {
        border-color: var(--sw-primary, #2563eb);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
      }

      .sw-send {
        border: none;
        border-radius: 12px;
        padding: 0 16px;
        color: #ffffff;
        font-weight: 700;
        cursor: pointer;
      }

      .sw-status {
        font-size: 12px;
        color: #6b7280;
        margin-top: 8px;
      }
    `;

    document.head.appendChild(style);
  }

  async function fetchConfig() {
    const url = `${apiBase}/bootstrap?bot_id=${encodeURIComponent(botId)}&domain=${encodeURIComponent(window.location.hostname)}`;

    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
      },
    });

    if (!response.ok) {
      throw new Error(`Bootstrap failed with status ${response.status}`);
    }

    return response.json();
  }

  function applyBranding(config) {
    const primary = config.brand?.primary_color || '#2563eb';
    const title = config.brand?.title || 'Support';
    const subtitle = config.brand?.subtitle || 'Ask us anything';
    const logoUrl = config.brand?.logo_url || '';
    const greeting = config.brand?.greeting || 'Hi! How can we help you today?';

    state.elements.button.style.background = primary;
    state.elements.header.style.background = primary;
    state.elements.sendButton.style.background = primary;
    state.elements.input.style.setProperty('--sw-primary', primary);

    state.elements.header.innerHTML = `
      <div class="sw-brand-row">
        <div class="sw-logo">${logoUrl ? `<img src="${escapeHtml(logoUrl)}" alt="${escapeHtml(title)} logo">` : '💬'}</div>
        <div>
          <p class="sw-title">${escapeHtml(title)}</p>
          <div class="sw-subtitle">${escapeHtml(subtitle)}</div>
        </div>
      </div>
    `;

    appendMessage('bot', greeting);
  }

  function appendMessage(role, content) {
    const message = createEl('div', `sw-message ${role}`);
    message.textContent = content;
    state.elements.body.appendChild(message);
    state.elements.body.scrollTop = state.elements.body.scrollHeight;
  }

  function setStatus(text) {
    state.elements.status.textContent = text || '';
  }

  async function sendMessage(text) {
    appendMessage('user', text);
    setStatus('Sending...');

    try {
      const response = await fetch(`${apiBase}/message`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          bot_id: botId,
          message: text,
          domain: window.location.hostname,
        }),
      });

      if (!response.ok) {
        throw new Error(`Message request failed with status ${response.status}`);
      }

      const data = await response.json();
      appendMessage('bot', data.reply || 'Thanks, we received your message.');
      setStatus('');
    } catch (error) {
      console.error('[Widget] Failed to send message:', error);
      appendMessage('bot', 'Sorry, the widget could not reach the server.');
      setStatus('Connection error');
    }
  }

  function togglePanel() {
    state.isOpen = !state.isOpen;
    state.elements.panel.classList.toggle('open', state.isOpen);
  }

  function buildUi() {
    injectStyles();

    const root = createEl('div', `sw-root ${position}`);
    const panel = createEl('div', 'sw-panel');
    const header = createEl('div', 'sw-header');
    const body = createEl('div', 'sw-body');
    const footer = createEl('div', 'sw-footer');
    const form = createEl('form', 'sw-form');
    const input = createEl('input', 'sw-input');
    const sendButton = createEl('button', 'sw-send', 'Send');
    const status = createEl('div', 'sw-status');
    const button = createEl('button', 'sw-button', '💬');

    input.type = 'text';
    input.placeholder = 'Type your message...';
    sendButton.type = 'submit';

    form.appendChild(input);
    form.appendChild(sendButton);
    footer.appendChild(form);
    footer.appendChild(status);

    panel.appendChild(header);
    panel.appendChild(body);
    panel.appendChild(footer);

    root.appendChild(panel);
    root.appendChild(button);
    document.body.appendChild(root);

    button.addEventListener('click', togglePanel);

    form.addEventListener('submit', async function (event) {
      event.preventDefault();

      const value = input.value.trim();
      if (!value) return;

      input.value = '';
      await sendMessage(value);
    });

    state.elements = {
      root,
      button,
      panel,
      header,
      messages: body,
      form,
      input,
      sendButton,
      body,
      footer,
      status,
    };
  }

  function renderDisabled(message) {
    state.elements.header.innerHTML = `
      <div class="sw-brand-row">
        <div class="sw-logo">⚠️</div>
        <div>
          <p class="sw-title">Unavailable</p>
          <div class="sw-subtitle">Widget disabled</div>
        </div>
      </div>
    `;

    state.elements.button.style.background = '#6b7280';
    state.elements.header.style.background = '#6b7280';
    state.elements.sendButton.style.background = '#6b7280';
    state.elements.input.disabled = true;
    state.elements.sendButton.disabled = true;

    appendMessage('bot', message || 'This widget is currently unavailable.');
  }

  async function init() {
    if (state.isLoading || state.isLoaded) return;

    state.isLoading = true;
    buildUi();

    try {
      const config = await fetchConfig();
      state.config = config;

      if (config.status !== 'active') {
        renderDisabled(config.message || 'Subscription inactive.');
        state.isLoaded = true;
        state.isLoading = false;
        return;
      }

      applyBranding(config);
      state.isLoaded = true;
      state.isLoading = false;
    } catch (error) {
      console.error('[Widget] Initialization failed:', error);
      renderDisabled('Could not load widget configuration.');
      state.isLoaded = true;
      state.isLoading = false;
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
