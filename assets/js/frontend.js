document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('[data-softland-ai]');
  if (!root || !window.softlandAi) {
    return;
  }

  const launcher = root.querySelector('[data-softland-ai-launcher]');
  const panel = root.querySelector('[data-softland-ai-panel]');
  const closeButton = root.querySelector('[data-softland-ai-close]');
  const form = root.querySelector('[data-softland-ai-form]');
  const input = root.querySelector('[data-softland-ai-input]');
  const submit = root.querySelector('[data-softland-ai-submit]');
  const intro = root.querySelector('[data-softland-ai-intro]');
  const chips = root.querySelector('[data-softland-ai-chips]');
  const messages = root.querySelector('[data-softland-ai-messages]');
  const status = root.querySelector('[data-softland-ai-status]');
  let history = [];

  const renderPromptButtons = (items = []) => {
    if (!chips) {
      return;
    }

    chips.innerHTML = '';
    items.forEach((item) => {
      if (!item) {
        return;
      }
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'softland-ai__chip';
      button.dataset.softlandAiPrompt = item;
      button.textContent = item;
      chips.appendChild(button);
    });
  };

  const setOpen = (open) => {
    if (!launcher || !panel) {
      return;
    }

    launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
    panel.hidden = !open;
    panel.setAttribute('aria-hidden', open ? 'false' : 'true');
    root.classList.toggle('is-open', open);

    if (open) {
      window.setTimeout(() => input?.focus(), 120);
    }
  };

  const setBusy = (busy) => {
    root.classList.toggle('is-busy', busy);
    if (submit) {
      submit.disabled = busy;
    }
    if (input) {
      input.disabled = busy;
    }
  };

  const setStatus = (text, isError = false) => {
    if (!status) {
      return;
    }
    status.textContent = text || '';
    status.classList.toggle('is-error', isError);
  };

  const createMessage = (role, text) => {
    const bubble = document.createElement('article');
    bubble.className = `softland-ai__message softland-ai__message--${role}`;

    const label = document.createElement('span');
    label.className = 'softland-ai__message-label';
    label.textContent = role === 'user' ? softlandAi.userLabel : softlandAi.botLabel;

    const body = document.createElement('div');
    body.className = 'softland-ai__message-body';
    body.textContent = text;

    bubble.append(label, body);
    return bubble;
  };

  const renderLinks = (items = []) => {
    if (!items.length) {
      return null;
    }

    const wrap = document.createElement('div');
    wrap.className = 'softland-ai__links';

    items.forEach((item) => {
      if (!item?.url || !item?.label) {
        return;
      }
      const link = document.createElement('a');
      link.className = 'softland-ai__link';
      link.href = item.url;
      link.textContent = item.label;
      wrap.appendChild(link);
    });

    return wrap.childNodes.length ? wrap : null;
  };

  const appendReply = (payload) => {
    if (!payload?.answer || !messages) {
      return;
    }

    const bubble = createMessage('assistant', payload.answer);
    const links = renderLinks(Array.isArray(payload.links) ? payload.links : []);
    if (links) {
      bubble.appendChild(links);
    }

    messages.appendChild(bubble);
    messages.scrollTop = messages.scrollHeight;

    if (intro) {
      intro.hidden = true;
    }

    history.push({ role: 'assistant', content: payload.answer });
    history = history.slice(-6);

    if (Array.isArray(payload.suggestions) && payload.suggestions.length) {
      renderPromptButtons(payload.suggestions);
    }

    if (softlandAi.isAdmin && payload?.source === 'fallback' && payload?.diagnostics) {
      const requestError = payload.diagnostics.request_error;
      const parts = [];

      if (payload.diagnostics.has_api_key === false) {
        parts.push('DeepSeek API key is missing.');
      }
      if (requestError?.code) {
        parts.push(`Error: ${requestError.code}`);
      }
      if (requestError?.status) {
        parts.push(`HTTP ${requestError.status}`);
      }
      if (requestError?.message) {
        parts.push(requestError.message);
      }
      if (payload.diagnostics.parse_error) {
        parts.push(`Parse issue: ${payload.diagnostics.parse_error}`);
      }

      setStatus(parts.join(' | ') || 'Assistant is running in fallback mode.', true);
    }
  };

  const appendUserMessage = (text) => {
    if (!messages || !text) {
      return;
    }

    messages.appendChild(createMessage('user', text));
    messages.scrollTop = messages.scrollHeight;

    if (intro) {
      intro.hidden = true;
    }

    history.push({ role: 'user', content: text });
    history = history.slice(-6);
  };

  const sendMessage = async (text) => {
    if (!text) {
      setStatus(softlandAi.empty, true);
      return;
    }

    appendUserMessage(text);
    setBusy(true);
    setStatus(softlandAi.thinking);

    try {
      const response = await fetch(softlandAi.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: new URLSearchParams({
          action: softlandAi.action,
          nonce: softlandAi.nonce,
          message: text,
          history: JSON.stringify(history.slice(0, -1)),
          page_id: String(softlandAi.pageId || ''),
          page_title: softlandAi.pageTitle || document.title,
          page_url: softlandAi.pageUrl || window.location.href,
          page_type: softlandAi.pageType || '',
        }),
      });

      const data = await response.json();
      if (!data?.success || !data?.data?.answer) {
        throw new Error(data?.data?.message || softlandAi.error);
      }

      appendReply(data.data);
      if (!(softlandAi.isAdmin && data?.data?.source === 'fallback')) {
        setStatus('');
      }
    } catch (error) {
      setStatus(softlandAi.error, true);
    } finally {
      setBusy(false);
    }
  };

  launcher?.addEventListener('click', () => {
    const open = launcher.getAttribute('aria-expanded') === 'true';
    setOpen(!open);
  });

  closeButton?.addEventListener('click', () => setOpen(false));

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const text = (input?.value || '').trim();
    if (!text) {
      setStatus(softlandAi.empty, true);
      return;
    }

    if (input) {
      input.value = '';
    }

    await sendMessage(text);
  });

  chips?.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }

    const prompt = target.getAttribute('data-softland-ai-prompt') || '';
    if (!prompt) {
      return;
    }

    setOpen(true);
    if (input) {
      input.value = '';
    }
    void sendMessage(prompt);
  });

  document.addEventListener('click', (event) => {
    if (!root.classList.contains('is-open')) {
      return;
    }
    const target = event.target;
    if (target instanceof Node && !root.contains(target)) {
      setOpen(false);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && root.classList.contains('is-open')) {
      setOpen(false);
    }
  });

  renderPromptButtons(Array.isArray(softlandAi.initialSuggestions) ? softlandAi.initialSuggestions : []);
});
