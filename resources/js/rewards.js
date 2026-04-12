import { onReady } from './utils';

function getTitlesByType() {
    const page = document.getElementById('rewardsIndexPage') || document.getElementById('eligibilityIndexPage');
    if (!page) {
        return {};
    }

    try {
        return JSON.parse(page.dataset.rewardTitleOptionsByType || '{}');
    } catch (error) {
        return {};
    }
}

function getEligibleTypesByEmployee() {
    const page = document.getElementById('rewardsIndexPage') || document.getElementById('eligibilityIndexPage');
    if (!page) {
        return {};
    }

    try {
        return JSON.parse(page.dataset.assignableRewardTypesByEmployee || '{}');
    } catch (error) {
        return {};
    }
}

function updateRewardTitleOptions(employeeInput, titleInput, titlesByType, eligibleTypesByEmployee) {
    if (!employeeInput || !titleInput) {
        return;
    }

    const employeeId = employeeInput.value;
    const eligibleTypes = Array.isArray(eligibleTypesByEmployee[employeeId]) ? eligibleTypesByEmployee[employeeId] : [];
    const selectedTitleId = titleInput.dataset.selectedTitleId || titleInput.value || '';
    const allowedTypes = eligibleTypes.length ? eligibleTypes : ['special'];
    const options = allowedTypes.flatMap((type) => {
        const titles = Array.isArray(titlesByType[type]) ? titlesByType[type] : [];
        return titles.map((title) => ({
            ...title,
            award_type: type,
        }));
    });

    if (!employeeId) {
        titleInput.disabled = true;
        titleInput.innerHTML = '';
        titleInput.append(new Option('Select employee first', ''));
        return;
    }

    titleInput.innerHTML = '';

    if (!options.length) {
        titleInput.disabled = true;
        titleInput.append(new Option('No recognition titles available', ''));
        return;
    }

    titleInput.disabled = false;
    titleInput.append(new Option('Select recognition title', ''));

    options.forEach((title) => {
        const label = title.award_type === 'special'
            ? `${title.title} (Special Recognition)`
            : title.title;
        const option = new Option(label, String(title.id), false, String(title.id) === selectedTitleId);
        titleInput.append(option);
    });

    if (!options.some((option) => String(option.id) === selectedTitleId)) {
        titleInput.value = '';
    }

    titleInput.dataset.selectedTitleId = titleInput.value;
}

onReady(() => {
    const page = document.body?.dataset?.page || '';
    if (!page.startsWith('rewards.') && !page.startsWith('eligibility.')) {
        return;
    }

    const employeeInput = document.querySelector('#assignRewardForm select[name="employee_id"]');
    const titleInput = document.getElementById('rewardAwardTitle');
    const titlesByType = getTitlesByType();
    const eligibleTypesByEmployee = getEligibleTypesByEmployee();

    if (!employeeInput || !titleInput) {
        return;
    }

    updateRewardTitleOptions(employeeInput, titleInput, titlesByType, eligibleTypesByEmployee);

    const refreshTitles = () => {
        titleInput.dataset.selectedTitleId = '';
        updateRewardTitleOptions(employeeInput, titleInput, titlesByType, eligibleTypesByEmployee);
    };

    employeeInput.addEventListener('change', refreshTitles);

    if (window.jQuery) {
        window.jQuery(employeeInput).on('select2:select select2:clear', refreshTitles);
    }

    const assignModal = document.getElementById('assignRewardModal');
    if (assignModal) {
        assignModal.addEventListener('shown.coreui.modal', () => {
            updateRewardTitleOptions(employeeInput, titleInput, titlesByType, eligibleTypesByEmployee);
        });

        assignModal.addEventListener('shown.bs.modal', () => {
            updateRewardTitleOptions(employeeInput, titleInput, titlesByType, eligibleTypesByEmployee);
        });
    }

    titleInput.addEventListener('change', () => {
        titleInput.dataset.selectedTitleId = titleInput.value;
    });
});



