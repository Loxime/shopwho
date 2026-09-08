(() => {
  const chat = document.querySelector(
    '[data-support-chat]'
  );

  if (!chat) {
    return;
  }

  const toggle = chat.querySelector(
    '[data-support-chat-toggle]'
  );

  const close = chat.querySelector(
    '[data-support-chat-close]'
  );

  const panel = chat.querySelector(
    '[data-support-chat-panel]'
  );

  const form = chat.querySelector(
    '[data-support-chat-form]'
  );

  const input = chat.querySelector(
    '[data-support-chat-input]'
  );

  const send = chat.querySelector(
    '[data-support-chat-send]'
  );

  const messages = chat.querySelector(
    '[data-support-chat-messages]'
  );

  const endpoint = chat.dataset.endpoint;

  if (
    !toggle
    || !close
    || !panel
    || !form
    || !input
    || !send
    || !messages
    || !endpoint
  ) {
    return;
  }

  const setOpen = (open) => {
    panel.hidden = !open;

    toggle.setAttribute(
      'aria-expanded',
      open ? 'true' : 'false'
    );

    toggle.setAttribute(
      'aria-label',
      open
        ? 'Fermer l’assistant Shopwho'
        : 'Ouvrir l’assistant Shopwho'
    );

    if (open) {
      input.focus();
    }
  };

  const scrollToLatestMessage = () => {
    messages.scrollTop =
      messages.scrollHeight;
  };

  const appendMessage = (
    content,
    role,
    action = null
  ) => {
    const wrapper =
      document.createElement('div');

    wrapper.classList.add(
      'support-chat-message',
      role === 'user'
        ? 'support-chat-message-user'
        : 'support-chat-message-assistant'
    );

    const text =
      document.createElement('div');

    text.textContent = content;

    wrapper.appendChild(text);

    if (
      action
      && typeof action.label === 'string'
      && typeof action.url === 'string'
    ) {
      const link =
        document.createElement('a');

      link.className =
        'support-chat-action';

      link.href = action.url;
      link.textContent = action.label;

      wrapper.appendChild(link);
    }

    messages.appendChild(wrapper);

    scrollToLatestMessage();
  };

  const setLoading = (loading) => {
    input.disabled = loading;
    send.disabled = loading;

    send.setAttribute(
      'aria-busy',
      loading ? 'true' : 'false'
    );
  };

  toggle.addEventListener(
    'click',
    () => {
      setOpen(panel.hidden);
    }
  );

  close.addEventListener(
    'click',
    () => {
      setOpen(false);
      toggle.focus();
    }
  );

  document.addEventListener(
    'keydown',
    (event) => {
      if (
        event.key === 'Escape'
        && !panel.hidden
      ) {
        setOpen(false);
        toggle.focus();
      }
    }
  );

  form.addEventListener(
    'submit',
    async (event) => {
      event.preventDefault();

      const message =
        input.value.trim();

      if (!message) {
        return;
      }

      appendMessage(
        message,
        'user'
      );

      input.value = '';
      setLoading(true);

      try {
        const response = await fetch(
          endpoint,
          {
            method: 'POST',
            headers: {
              'Content-Type':
                'application/json',
              Accept:
                'application/json',
            },
            body: JSON.stringify({
              message,
            }),
          }
        );

        const payload =
          await response.json();

        if (!response.ok) {
          throw new Error(
            typeof payload.error === 'string'
              ? payload.error
              : 'Réponse invalide du serveur.'
          );
        }

        appendMessage(
          payload.message,
          'assistant',
          payload.action
        );
      } catch (error) {
        appendMessage(
          error instanceof Error
            ? error.message
            : 'Le service d’aide est momentanément indisponible.',
          'assistant'
        );
      } finally {
        setLoading(false);
        input.focus();
      }
    }
  );
})();
