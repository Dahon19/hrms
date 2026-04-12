(function () {
    const page = document.getElementById('jobPostingApplicantsPage');
    const tableBody = document.getElementById('jobPostingApplicantsTableBody');
    const searchInput = document.getElementById('jobPostingApplicantsSearchInput');
    const applyButton = document.getElementById('jobPostingApplicantsApply');

    const applyApplicantFilters = () => {
        if (!tableBody) return;
        const search = String(searchInput?.value || '').trim().toLowerCase();
        let visible = 0;

        Array.from(tableBody.querySelectorAll('tr')).forEach((row) => {
            if (!row.querySelector('td')) return;

            const rowSearch = String(row.dataset.search || '').toLowerCase();
            const matched = !search || rowSearch.includes(search);
            row.classList.toggle('d-none', !matched);
            if (matched) visible += 1;
        });

        let empty = document.getElementById('jobPostingApplicantsEmptyClient');
        if (!empty && tableBody) {
            empty = document.createElement('tr');
            empty.id = 'jobPostingApplicantsEmptyClient';
            empty.className = 'd-none';
            empty.innerHTML = '<td colspan="5" class="text-center py-4 text-muted">No applicants match the current filters.</td>';
            tableBody.appendChild(empty);
        }
        if (empty) {
            empty.classList.toggle('d-none', visible !== 0);
        }
    };

    if (page) {
        applyButton?.addEventListener('click', applyApplicantFilters);
        searchInput?.addEventListener('input', applyApplicantFilters);
    }

    const modalEl = document.getElementById('applicantDocumentsModal');
    if (!modalEl && !page) return;
    let lastTrigger = null;

    const onModalEvent = (eventName, handler) => {
        if (window.bootstrap && window.bootstrap.Modal) {
            modalEl.addEventListener(eventName, handler);
            return;
        }
        if (window.jQuery) {
            window.jQuery(modalEl).on(eventName, handler);
        }
    };

    const frame = document.getElementById('applicantDocPreviewFrame');
    const empty = document.getElementById('applicantDocPreviewEmpty');
    const title = document.getElementById('applicantDocPreviewTitle');

    const isPreviewable = (url) => {
        if (!url) return false;
        const cleanUrl = url.split('?')[0].toLowerCase();
        return [
            '.pdf',
            '.png',
            '.jpg',
            '.jpeg',
            '.gif',
            '.webp',
            '.bmp',
            '.svg',
            '.txt',
        ].some((ext) => cleanUrl.endsWith(ext));
    };

    const setPreview = (url, label) => {
        if (!frame || !empty || !title) return;
        if (!url) {
            frame.style.display = 'none';
            frame.removeAttribute('src');
            empty.style.display = 'block';
            empty.innerHTML = 'Document preview will appear here.';
            title.textContent = label + ' not submitted.';
            return;
        }

        if (!isPreviewable(url)) {
            frame.style.display = 'none';
            frame.removeAttribute('src');
            empty.style.display = 'block';
            title.textContent = label + ' (opens in new tab)';
            empty.innerHTML = 'This file type cannot be previewed here.<br><a class="btn btn-sm btn-outline-primary mt-2" href="' + url + '" target="_blank" rel="noopener">Open document</a>';
            return;
        }

        title.textContent = label;
        frame.src = url;
        frame.style.display = 'block';
        empty.style.display = 'none';
    };

    const bindButton = (buttonId, url, label) => {
        const btn = document.getElementById(buttonId);
        if (!btn) return;
        btn.disabled = !url;
        btn.classList.toggle('disabled', !url);
        btn.onclick = function () {
            setPreview(url, label);
        };
    };

    const populateFromTrigger = (button) => {
        if (!button) return false;

        document.getElementById('applicantDocsName').textContent = button.getAttribute('data-applicant') || 'Applicant';
        document.getElementById('applicantDocsJob').textContent = button.getAttribute('data-job-title') || 'Job Posting';
        document.getElementById('applicantDocsGender').textContent = 'Gender: ' + (button.getAttribute('data-gender') || 'N/A');
        document.getElementById('applicantDocsBirthday').textContent = 'Birthday: ' + (button.getAttribute('data-birthday') || 'N/A');
        document.getElementById('applicantDocsAddress').textContent = 'Address: ' + (button.getAttribute('data-address') || 'N/A');

        const letterUrl = button.getAttribute('data-letter-url') || '';
        const resumeUrl = button.getAttribute('data-resume-url') || '';
        const transcriptUrl = button.getAttribute('data-transcript-url') || '';
        const gmailUrl = button.getAttribute('data-gmail-url') || '';
        const yahooUrl = button.getAttribute('data-yahoo-url') || '';
        const applicantEmail = (button.getAttribute('data-email') || '').toLowerCase().trim();
        const emailDomain = applicantEmail.includes('@') ? applicantEmail.split('@').pop() : '';

        const emailBtn = document.getElementById('applicantMailAction');
        if (emailBtn) {
            let emailHref = '';
            let useBlankTarget = false;

            if ((emailDomain === 'gmail.com' || emailDomain === 'googlemail.com') && gmailUrl) {
                emailHref = gmailUrl;
                useBlankTarget = true;
            } else if ((emailDomain === 'yahoo.com' || emailDomain === 'ymail.com' || emailDomain === 'rocketmail.com') && yahooUrl) {
                emailHref = yahooUrl;
                useBlankTarget = true;
            } else if (applicantEmail) {
                emailHref = 'mailto:' + applicantEmail;
                useBlankTarget = false;
            }

            emailBtn.href = emailHref || '#';
            emailBtn.classList.toggle('disabled', !emailHref);
            emailBtn.classList.toggle('d-none', !emailHref);
            emailBtn.setAttribute('title', applicantEmail ? ('Email ' + applicantEmail) : 'Email applicant');
            emailBtn.setAttribute('aria-label', applicantEmail ? ('Email ' + applicantEmail) : 'Email applicant');

            if (emailHref && useBlankTarget) {
                emailBtn.setAttribute('target', '_blank');
            } else {
                emailBtn.removeAttribute('target');
            }
        }

        bindButton('applicantDocLetterBtn', letterUrl, 'Application Letter');
        bindButton('applicantDocResumeBtn', resumeUrl, 'Resume');
        bindButton('applicantDocTranscriptBtn', transcriptUrl, 'Transcript');

        if (letterUrl) {
            setPreview(letterUrl, 'Application Letter');
        } else if (resumeUrl) {
            setPreview(resumeUrl, 'Resume');
        } else if (transcriptUrl) {
            setPreview(transcriptUrl, 'Transcript');
        } else {
            setPreview('', 'No documents');
        }

        return true;
    };

    document.addEventListener('click', function (event) {
        const trigger = event.target && typeof event.target.closest === 'function'
            ? event.target.closest('.applicant-docs-trigger')
            : null;
        if (trigger) {
            lastTrigger = trigger;
            populateFromTrigger(trigger);
        }
    });

    if (!modalEl) {
        applyApplicantFilters();
        return;
    }

    onModalEvent('show.bs.modal', function (event) {
        const button = event.relatedTarget || (event.detail ? event.detail.relatedTarget : null) || lastTrigger;
        populateFromTrigger(button);
    });

    onModalEvent('hidden.bs.modal', function () {
        if (!frame || !empty || !title) return;
        frame.style.display = 'none';
        frame.removeAttribute('src');
        empty.style.display = 'block';
        empty.textContent = 'Document preview will appear here.';
        title.textContent = 'Select a document to preview.';
        document.getElementById('applicantDocsGender').textContent = 'Gender: N/A';
        document.getElementById('applicantDocsBirthday').textContent = 'Birthday: N/A';
        document.getElementById('applicantDocsAddress').textContent = 'Address: N/A';
        const emailBtn = document.getElementById('applicantMailAction');
        if (emailBtn) {
            emailBtn.href = '#';
            emailBtn.classList.remove('disabled');
            emailBtn.classList.add('d-none');
            emailBtn.removeAttribute('target');
            emailBtn.setAttribute('title', 'Email applicant');
            emailBtn.setAttribute('aria-label', 'Email applicant');
        }
    });

    applyApplicantFilters();
})();



