@php
    $chatbotUser = Auth::user();
    $chatbotName = $chatbotUser?->employee?->first_name ?: ($chatbotUser?->name ? explode(' ', trim($chatbotUser->name))[0] : 'there');
    $chatbotRole = $chatbotUser?->isAdmin()
        ? 'administrator'
        : (\App\Services\AccessControl::isHrStaff($chatbotUser)
            ? 'HR staff'
            : (optional(optional($chatbotUser?->employee)->position)->name ?: 'team member'));
@endphp
<div
    class="hrms-chatbot"
    data-chatbot
    data-chat-endpoint="{{ route('ai.chat') }}"
    data-chatbot-user="{{ Auth::id() }}"
    data-chatbot-name="{{ $chatbotName }}"
    data-chatbot-role="{{ $chatbotRole }}"
>
    <section
        class="hrms-chatbot__panel"
        data-chatbot-panel
        aria-label="HRMS assistant"
        aria-hidden="true"
    >
        <header class="hrms-chatbot__panel-header">
            <div class="hrms-chatbot__panel-top">
                <div class="hrms-chatbot__panel-brand">
                    <span class="hrms-chatbot__brand-icon">
                        <i class="cil-speech"></i>
                    </span>
                    <div>
                        <span class="hrms-chatbot__eyebrow">AI Assistant</span>
                        <h2 class="hrms-chatbot__title mb-0">HRMS Support</h2>
                    </div>
                </div>
                <div class="hrms-chatbot__panel-actions">
                    <button
                        type="button"
                        class="hrms-chatbot__clear"
                        data-chatbot-clear
                        aria-label="Clear chat history"
                    >
                        Clear
                    </button>
                    <button
                        type="button"
                        class="hrms-chatbot__close"
                        data-chatbot-close
                        aria-label="Close assistant"
                    >
                        <i class="cil-x"></i>
                    </button>
                </div>
            </div>
            <p class="hrms-chatbot__subtitle mb-0">
                Guidance for navigation, requests, attendance, leave, and profile tools.
            </p>
        </header>

        <div class="hrms-chatbot__intro" data-chatbot-intro>
            <p class="hrms-chatbot__intro-copy mb-0">
                Hi {{ $chatbotName }}. Start with a preset or type a short question.
            </p>
            <div class="hrms-chatbot__presets" data-chatbot-presets>
                <button type="button" class="hrms-chatbot__preset" data-chatbot-preset="Where can I find leave requests, attendance logs, and payslip features?">
                    Find a feature
                </button>
                <button type="button" class="hrms-chatbot__preset" data-chatbot-preset="How do approvals and employee requests work in this HRMS?">
                    Requests
                </button>
                <button type="button" class="hrms-chatbot__preset" data-chatbot-preset="What can this HRMS help me with based on my role?">
                    What can you do
                </button>
            </div>
        </div>

        <div class="hrms-chatbot__messages" data-chatbot-messages role="log" aria-live="polite"></div>

        <div class="hrms-chatbot__status" data-chatbot-status aria-live="polite"></div>

        <form class="hrms-chatbot__composer" data-chatbot-form>
            <div class="hrms-chatbot__composer-shell">
                <label class="sr-only" for="hrmsChatbotInput">Message</label>
                <textarea
                    id="hrmsChatbotInput"
                    class="hrms-chatbot__input"
                    data-chatbot-input
                    rows="1"
                    maxlength="1000"
                    placeholder="Ask a short HRMS question..."
                ></textarea>
                <button
                    type="submit"
                    class="hrms-chatbot__send"
                    data-chatbot-send
                    aria-label="Send message"
                >
                    <i class="cil-paper-plane"></i>
                </button>
            </div>
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
