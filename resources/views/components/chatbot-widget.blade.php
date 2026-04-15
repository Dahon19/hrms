<div
    class="hrms-chatbot"
    data-chatbot
    data-chat-endpoint="{{ route('ai.chat') }}"
    data-chatbot-user="{{ Auth::id() }}"
>
    <section
        class="hrms-chatbot__panel"
        data-chatbot-panel
        aria-label="HRMS assistant"
        aria-hidden="true"
    >
        <header class="hrms-chatbot__panel-header">
            <div>
                <span class="hrms-chatbot__eyebrow">AI Assistant</span>
                <h2 class="hrms-chatbot__title mb-0">HRMS Support</h2>
            </div>
            <button
                type="button"
                class="hrms-chatbot__close"
                data-chatbot-close
                aria-label="Close assistant"
            >
                <i class="cil-x"></i>
            </button>
        </header>

        <div class="hrms-chatbot__messages" data-chatbot-messages role="log" aria-live="polite">
            <article class="hrms-chatbot__message hrms-chatbot__message--assistant">
                <div class="hrms-chatbot__bubble">
                    Ask about modules, workflows, approvals, or general HRMS guidance.
                </div>
            </article>
        </div>

        <div class="hrms-chatbot__status" data-chatbot-status aria-live="polite"></div>

        <form class="hrms-chatbot__composer" data-chatbot-form>
            <label class="sr-only" for="hrmsChatbotInput">Message</label>
            <textarea
                id="hrmsChatbotInput"
                class="hrms-chatbot__input"
                data-chatbot-input
                rows="1"
                maxlength="1000"
                placeholder="Ask HRMS Assistant..."
            ></textarea>
            <button
                type="submit"
                class="hrms-chatbot__send"
                data-chatbot-send
                aria-label="Send message"
            >
                <i class="cil-location-arrow"></i>
            </button>
        </form>
    </section>

    <button
        type="button"
        class="hrms-chatbot__toggle"
        data-chatbot-toggle
        aria-expanded="false"
        aria-controls="hrmsChatbotInput"
    >
        <span class="hrms-chatbot__toggle-icon">
            <i class="cil-speech"></i>
        </span>
        <span class="hrms-chatbot__toggle-copy">
            <strong>HRMS Assistant</strong>
            <small>Ask a question</small>
        </span>
    </button>
</div>
