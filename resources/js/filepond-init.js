import { create, find, registerPlugin } from 'filepond';
import 'filepond/dist/filepond.min.css';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';

let pluginsRegistered = false;

function registerPlugins() {
    if (pluginsRegistered || typeof registerPlugin !== 'function') {
        return;
    }

    registerPlugin(FilePondPluginFileValidateSize);
    pluginsRegistered = true;
}

export function initFilePond(root = document) {
    if (!root || typeof create !== 'function') {
        return [];
    }

    registerPlugins();

    const inputs = Array.from(root.querySelectorAll('input[type="file"].filepond'));

    return inputs.map((input) => {
        if (input.dataset.filepondReady === '1') {
            return find(input);
        }

        const pond = create(input, {
            credits: false,
            storeAsFile: true,
            allowMultiple: input.hasAttribute('multiple'),
            allowReorder: false,
            allowProcess: false,
            maxFileSize: input.dataset.maxFileSize || undefined,
            labelIdle: input.dataset.filepondLabelIdle || 'Drop files here or <span class="filepond--label-action">Browse</span>',
        });

        input.dataset.filepondReady = '1';
        return pond;
    });
}

if (typeof window !== 'undefined') {
    window.FilePond = { create, find, registerPlugin };
}
