const getChatbotStorageKey = (userId) => `hrms-chatbot-history-${userId || 'guest'}`;

const autoResize = (input) => {
    input.style.height = 'auto';
    input.style.height = `${Math.min(input.scrollHeight, 120)}px`;
};

const sanitizeMessage = (value) => value.replace(/\s+/g, ' ').trim();
const defaultAssistantPrompt =
    'Choose a preset or ask a short question about HRMS features, requests, attendance, or navigation.';

const appendMessage = (container, role, text) => {
    const article = document.createElement('article');
    article.className = `hrms-chatbot__message hrms-chatbot__message--${role}`;

    const bubble = document.createElement('div');
    bubble.className = 'hrms-chatbot__bubble';
    bubble.textContent = text;

    article.appendChild(bubble);
    container.appendChild(article);
    container.scrollTop = container.scrollHeight;

    return article;
};

const appendTypingIndicator = (container) => {
    const article = document.createElement('article');
    article.className = 'hrms-chatbot__message hrms-chatbot__message--assistant hrms-chatbot__message--typing';

    const bubble = document.createElement('div');
    bubble.className = 'hrms-chatbot__bubble hrms-chatbot__bubble--typing';
    bubble.innerHTML = '<span></span><span></span><span></span>';

    article.appendChild(bubble);
    container.appendChild(article);
    container.scrollTop = container.scrollHeight;

    return article;
};

const renderHistory = (container, history) => {
    container.innerHTML = '';

    if (!history.length) {
        appendMessage(
            container,
            'assistant',
            defaultAssistantPrompt,
        );
        return;
    }

    history.forEach((message) => appendMessage(container, message.role, message.text));
};

const setStatus = (statusNode, message = '', tone = 'default') => {
    statusNode.textContent = message;
    statusNode.dataset.tone = tone;
};

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-chatbot]');

    if (!root) {
        return;
    }

    const endpoint = root.dataset.chatEndpoint;
    const userId = root.dataset.chatbotUser;
    const toggles = Array.from(document.querySelectorAll('[data-chatbot-toggle]'));
    const panel = root.querySelector('[data-chatbot-panel]');
    const clear = root.querySelector('[data-chatbot-clear]');
    const close = root.querySelector('[data-chatbot-close]');
    const form = root.querySelector('[data-chatbot-form]');
    const input = root.querySelector('[data-chatbot-input]');
    const send = root.querySelector('[data-chatbot-send]');
    const messages = root.querySelector('[data-chatbot-messages]');
    const status = root.querySelector('[data-chatbot-status]');
    const intro = root.querySelector('[data-chatbot-intro]');
    const presetButtons = Array.from(root.querySelectorAll('[data-chatbot-preset]'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const storageKey = getChatbotStorageKey(userId);
    let history = [];
    let open = false;
    let pending = false;
    let typingNode = null;

    try {
        const stored = window.localStorage.getItem(storageKey);
        history = stored ? JSON.parse(stored) : [];
        if (!Array.isArray(history)) {
            history = [];
        }
    } catch (error) {
        history = [];
    }

    const persistHistory = () => {
        try {
            window.localStorage.setItem(storageKey, JSON.stringify(history.slice(-12)));
        } catch (error) {
            // Ignore storage failures and keep the session in memory.
        }
    };

    const syncIntroState = () => {
        if (!intro) {
            return;
        }

        intro.hidden = history.length > 0;
    };

    const syncOpenState = () => {
        root.classList.toggle('is-open', open);
        toggles.forEach(t => t.setAttribute('aria-expanded', open ? 'true' : 'false'));
        panel.setAttribute('aria-hidden', open ? 'false' : 'true');

        if (open) {
            window.setTimeout(() => input.focus(), 120);
        }
    };

    const setPending = (value) => {
        pending = value;
        root.classList.toggle('is-loading', value);
        send.disabled = value;
        input.disabled = value;
        if (clear) {
            clear.disabled = value;
        }
        presetButtons.forEach((button) => {
            button.disabled = value;
        });
    };

    const showTyping = () => {
        typingNode = appendTypingIndicator(messages);
    };

    const hideTyping = () => {
        if (!typingNode) {
            return;
        }

        typingNode.remove();
        typingNode = null;
    };

    const resetChat = () => {
        history = [];
        try {
            window.localStorage.removeItem(storageKey);
        } catch (error) {
            // Ignore storage failures.
        }
        hideTyping();
        renderHistory(messages, history);
        syncIntroState();
        setStatus(status);
    };

    renderHistory(messages, history);
    syncIntroState();
    autoResize(input);
    syncOpenState();

    toggles.forEach(toggle => {
        toggle.addEventListener('click', () => {
            open = !open;
            syncOpenState();
        });
    });

    close.addEventListener('click', () => {
        open = false;
        syncOpenState();
    });

    if (clear) {
        clear.addEventListener('click', () => {
            resetChat();
            input.focus();
        });
    }

    input.addEventListener('input', () => autoResize(input));

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    presetButtons.forEach((button) => {
        button.addEventListener('click', () => {
            input.value = button.dataset.chatbotPreset || '';
            autoResize(input);
            input.focus();
            form.requestSubmit();
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (pending) {
            return;
        }

        const message = sanitizeMessage(input.value);

        if (!message) {
            setStatus(status, 'Enter a message first.', 'error');
            input.focus();
            return;
        }

        open = true;
        syncOpenState();

        history.push({
            role: 'user',
            text: message,
        });
        renderHistory(messages, history);
        persistHistory();
        syncIntroState();
        input.value = '';
        autoResize(input);
        setPending(true);
        setStatus(status);
        showTyping();

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    message,
                    history: history.slice(0, -1),
                    system_instruction:
                        'Answer only within approved HRMS features: self-service, HR/admin assistance, policy guidance, conversational request guidance, reminders, navigation, analytics, and role-based access. Use AI for guidance only and never claim that a real system action was completed unless system data was provided.',
                }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok || !payload.ok || !payload.reply) {
                throw new Error(payload.message || 'The assistant is unavailable right now.');
            }

            history.push({
                role: 'assistant',
                text: payload.reply,
            });
            hideTyping();
            renderHistory(messages, history);
            persistHistory();
            syncIntroState();
            setStatus(status);
        } catch (error) {
            hideTyping();
            history.push({
                role: 'assistant',
                text: 'I could not reach the assistant right now. Try again in a moment.',
            });
            renderHistory(messages, history);
            persistHistory();
            syncIntroState();
            setStatus(
                status,
                error.message || 'The assistant is unavailable right now. Please try again in a moment.',
                'error',
            );
        } finally {
            hideTyping();
            setPending(false);
        }
    });
});
