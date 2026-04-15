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
            <div class="hrms-chatbot__panel-brand">
                <span class="hrms-chatbot__brand-icon">
                    <i class="cil-speech"></i>
                </span>
                <div>
                <span class="hrms-chatbot__eyebrow">AI Assistant</span>
                <h2 class="hrms-chatbot__title mb-0">HRMS Support</h2>
                    <p class="hrms-chatbot__subtitle mb-0">System purpose, modules, workflows, and organization use only.</p>
                </div>
            </div>
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
        </header>

        <div class="hrms-chatbot__intro" data-chatbot-intro>
            <div class="hrms-chatbot__intro-hero">
                <p class="hrms-chatbot__intro-copy mb-0">
                    Hello, {{ $chatbotName }}. I can help with approved {{ $chatbotRole }} HRMS questions and point you to the right module for actual actions.
                </p>
            </div>
            <div class="hrms-chatbot__capability-list">
                <span class="hrms-chatbot__capability-pill">Employee self-service</span>
                <span class="hrms-chatbot__capability-pill">HR and admin help</span>
                <span class="hrms-chatbot__capability-pill">Policies and navigation</span>
                <span class="hrms-chatbot__capability-pill">Role-based access</span>
            </div>
            <div class="hrms-chatbot__presets" data-chatbot-presets>
                <button type="button" class="hrms-chatbot__preset" data-chatbot-preset="What is the purpose of this HRMS?">
                    System purpose
                </button>
                <button type="button" class="hrms-chatbot__preset" data-chatbot-preset="How can employees use the HRMS for leave, attendance, and payslip concerns?">
                    Employee help
                </button>
                <button type="button" class="hrms-chatbot__preset" data-chatbot-preset="What policy, navigation, and reminder questions can the HRMS assistant answer?">
                    Policy help
                </button>
            </div>
        </div>

        <div class="hrms-chatbot__messages" data-chatbot-messages role="log" aria-live="polite">
            <article class="hrms-chatbot__message hrms-chatbot__message--assistant">
                <div class="hrms-chatbot__bubble">
                    Ask about modules, workflows, approvals, or general HRMS guidance.
                </div>
            </article>
        </div>

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
                    placeholder="Ask about the HRMS system..."
                ></textarea>
                <button
                    type="submit"
                    class="hrms-chatbot__send"
                    data-chatbot-send
                    aria-label="Send message"
                >
                    <i class="cil-location-arrow"></i>
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
