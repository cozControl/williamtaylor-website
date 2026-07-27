import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';

const editors = new WeakMap();
let unsavedWarningReady = false;

function initializeRichText(root) {
    if (editors.has(root)) return;
    const surface = root.querySelector('[data-editor-surface]');
    const component = root.closest('[wire\\:id]');
    const wireId = component?.getAttribute('wire:id');
    const index = Number(root.dataset.sectionIndex);
    const document = JSON.parse(root.dataset.document || '{"type":"doc","content":[]}');
    const editor = new Editor({
        element: surface,
        content: document,
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3, 4] },
                code: false,
                codeBlock: false,
                horizontalRule: false,
                strike: false,
                hardBreak: false,
            }),
        ],
        editorProps: {
            attributes: {
                class: 'cms-rich-prosemirror',
                role: 'textbox',
                'aria-multiline': 'true',
                'aria-label': 'Restricted page rich text',
            },
            handlePaste(view, event) {
                if ([...(event.clipboardData?.items || [])].some((item) => item.kind === 'file')) {
                    event.preventDefault();
                    return true;
                }
                return false;
            },
            handleDrop(view, event) {
                if (event.dataTransfer?.files?.length) {
                    event.preventDefault();
                    return true;
                }
                return false;
            },
        },
        onUpdate({ editor: current }) {
            window.Livewire.find(wireId)?.set(`sections.${index}.data.document`, current.getJSON());
        },
    });
    root.querySelectorAll('[data-command]').forEach((button) => button.addEventListener('click', () => {
        const chain = editor.chain().focus();
        switch (button.dataset.command) {
            case 'bold': chain.toggleBold().run(); break;
            case 'italic': chain.toggleItalic().run(); break;
            case 'heading2': chain.toggleHeading({ level: 2 }).run(); break;
            case 'bulletList': chain.toggleBulletList().run(); break;
            case 'orderedList': chain.toggleOrderedList().run(); break;
            case 'blockquote': chain.toggleBlockquote().run(); break;
            case 'link': {
                const href = window.prompt('Enter an internal path or HTTPS URL');
                if (href) chain.setLink({ href }).run();
                break;
            }
        }
    }));
    editors.set(root, editor);
}

function bootCmsEditors() {
    document.querySelectorAll('[data-rich-text]').forEach(initializeRichText);
    document.querySelectorAll('[data-cms-editor]').forEach((root) => {
        if (root.dataset.controlsReady) return;
        root.dataset.controlsReady = 'true';
        root.querySelector('[data-add-section-button]')?.addEventListener('click', () => {
            const select = root.querySelector('[data-add-section]');
            if (select.value) {
                window.Livewire.find(root.closest('[wire\\:id]').getAttribute('wire:id'))?.call('addSection', select.value);
                select.value = '';
            }
        });
    });
    if (!unsavedWarningReady) {
        window.addEventListener('beforeunload', (event) => {
            if (document.querySelector('[data-cms-editor][data-dirty="true"]')) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
        unsavedWarningReady = true;
    }
}

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('morph.updated', bootCmsEditors);
    bootCmsEditors();
});
document.addEventListener('livewire:navigated', bootCmsEditors);
if (document.readyState !== 'loading') bootCmsEditors();
else document.addEventListener('DOMContentLoaded', bootCmsEditors);
