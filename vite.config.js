import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules')) {
                        return;
                    }

                    if (
                        id.includes('pdfmake') ||
                        id.includes('jszip')
                    ) {
                        return 'vendor-doc-export';
                    }

                    if (
                        id.includes('filepond') ||
                        id.includes('filepond-plugin')
                    ) {
                        return 'vendor-filepond';
                    }

                    if (
                        id.includes('datatables.net')
                    ) {
                        return 'vendor-datatables';
                    }

                    if (
                        id.includes('select2')
                    ) {
                        return 'vendor-select2';
                    }

                    if (
                        id.includes('jquery')
                    ) {
                        return 'vendor-jquery';
                    }

                    if (
                        id.includes('pusher-js') ||
                        id.includes('laravel-echo')
                    ) {
                        return 'vendor-realtime';
                    }

                    if (
                        id.includes('@coreui') ||
                        id.includes('bootstrap')
                    ) {
                        return 'vendor-ui';
                    }
                },
            },
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/coreui-shell.css',
                'resources/css/ui-components.css',
                'resources/css/ui-tables.css',
                'resources/css/ui-hero.css',
                'resources/css/mobile-responsive.css',
                'resources/css/module-overrides.css',
                'resources/css/dark-mode-consistency.css',
                'resources/css/dashboard.css',
                'resources/css/attendance-kiosk.css',
                'resources/css/leaves-index.css',
                'resources/css/documents-index.css',
                'resources/css/employees-index.css',
                'resources/css/departments-index.css',
                'resources/css/positions-index.css',
                'resources/css/job-postings-index.css',
                'resources/css/reports-index.css',
                'resources/css/eligibility-index.css',
                'resources/css/idp-index.css',
                'resources/css/offboarding.css',
                'resources/css/leave-balances.css',
                'resources/css/audit-logs.css',
                'resources/css/landing.css',
                'resources/css/job-portal.css',
                'resources/css/emerald-theme.css',
                'resources/css/toasts.css',
                'resources/css/attendance-calendar.css',
                'resources/css/travel-orders.css',
                'resources/css/spms-index.css',
                'resources/css/pds.css',

                'resources/js/spms.js',
                'resources/js/rewards.js',
                'resources/js/app.js',
                'resources/js/ui-interactions.js',
                'resources/js/datatables-init.js',
                'resources/js/flash-toasts.js',
                'resources/js/notifications.js',
                'resources/js/attendance.js',
                'resources/js/audit-logs.js',
                'resources/js/documents.js',
                'resources/js/departments.js',
                'resources/js/employees.js',
                'resources/js/positions.js',
                'resources/js/leave-types.js',
                'resources/js/leaves.js',
                'resources/js/profile.js',
                'resources/js/chatbot.js',
                'resources/js/dashboard-ui.js',
                'resources/js/dashboard-report-hub.js',
                'resources/js/reports-index.js',
                'resources/js/offboarding.js',
                'resources/js/idp.js',
                'resources/js/job-portal.js',
                'resources/js/job-postings-index.js',
                'resources/js/job-postings-applicants.js',
                'resources/js/attendance-calendar.js',
                'resources/js/eligibility.js',
                'resources/js/pds.js',
            ],
            refresh: true,
        }),
    ],
});
