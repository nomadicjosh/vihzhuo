const DEFAULT_FONT_SIZES = ['10px', '12px', '14px', '16px', '18px', '24px', '32px', '48px'];

/**
 * Apply one inline CSS property to every selected text fragment without
 * wrapping block elements in invalid inline markup.
 */
function applyInlineStyle(rte, property, value) {
    const selection = rte.selection();
    if (!selection || selection.rangeCount === 0 || selection.isCollapsed) return;

    const range = selection.getRangeAt(0);
    if (!rte.el.contains(range.commonAncestorContainer)) return;

    const walker = rte.doc.createTreeWalker(rte.el, rte.doc.defaultView.NodeFilter.SHOW_TEXT);
    const fragments = [];
    let node;
    while ((node = walker.nextNode())) {
        if (!range.intersectsNode(node) || node.nodeValue.length === 0) continue;

        const start = node === range.startContainer ? range.startOffset : 0;
        const end = node === range.endContainer ? range.endOffset : node.nodeValue.length;
        if (start < end) fragments.push({node, start, end});
    }
    if (fragments.length === 0) return;

    const wrappers = [];
    fragments.forEach(fragment => {
        let selectedNode = fragment.node;
        if (fragment.end < selectedNode.nodeValue.length) {
            selectedNode.splitText(fragment.end);
        }
        if (fragment.start > 0) {
            selectedNode = selectedNode.splitText(fragment.start);
        }

        const parent = selectedNode.parentElement;
        if (parent?.tagName === 'SPAN' && parent.childNodes.length === 1) {
            parent.style.setProperty(property, value);
            wrappers.push(parent);
            return;
        }

        const wrapper = rte.doc.createElement('span');
        wrapper.style.setProperty(property, value);
        selectedNode.replaceWith(wrapper);
        wrapper.appendChild(selectedNode);
        wrappers.push(wrapper);
    });

    const updatedRange = rte.doc.createRange();
    updatedRange.setStartBefore(wrappers[0]);
    updatedRange.setEndAfter(wrappers[wrappers.length - 1]);
    selection.removeAllRanges();
    selection.addRange(updatedRange);
    rte.el.dispatchEvent(new rte.doc.defaultView.Event('input', {bubbles: true}));
}

function sanitizeHref(value) {
    const href = value.trim();
    if (/^(?:javascript|data|vbscript):/i.test(href)) return '';
    return href;
}

/**
 * Preserve the previous CKEditor paste policy without depending on CKEditor.
 */
function sanitizePastedHtml(html, doc) {
    const allowedTags = new Set([
        'A', 'TABLE', 'TR', 'TD', 'TH', 'THEAD', 'TBODY', 'TFOOT', 'CAPTION', 'COL', 'COLGROUP',
        'P', 'UL', 'OL', 'LI', 'BR', 'STRONG', 'EM', 'B', 'I', 'U', 'STRIKE', 'SUB', 'SUP',
        'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'PRE', 'HR',
    ]);
    const styledTableTags = new Set(['TABLE', 'TR', 'TD', 'TH', 'THEAD', 'TBODY', 'TFOOT']);
    const discardedTags = new Set(['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED']);
    const template = doc.createElement('template');
    template.innerHTML = html;

    Array.from(template.content.querySelectorAll('*')).forEach(element => {
        if (!allowedTags.has(element.tagName)) {
            if (discardedTags.has(element.tagName)) {
                element.remove();
            } else {
                element.replaceWith(...element.childNodes);
            }
            return;
        }

        const href = element.tagName === 'A' ? sanitizeHref(element.getAttribute('href') || '') : '';
        const style = styledTableTags.has(element.tagName) ? element.getAttribute('style') : null;
        Array.from(element.attributes).forEach(attribute => element.removeAttribute(attribute.name));
        if (href) element.setAttribute('href', href);
        if (style) element.setAttribute('style', style);
    });

    return template.innerHTML;
}

function editableTextComponentFrom(element) {
    while (element) {
        const component = element.__gjsv?.model;
        if (component?.get('editable') && component.isInstanceOf('text')) {
            return component;
        }
        element = element.parentElement;
    }

    return null;
}

/**
 * Keep component selection and text editing as separate interactions. GrapesJS
 * can select on one click, but only an explicit double-click may enable its RTE.
 */
function installDoubleClickActivation(editor) {
    const documents = new Set();
    const listeners = new Map();

    const attach = doc => {
        if (! doc || documents.has(doc)) return;

        const onDoubleClick = event => {
            const target = event.target?.nodeType === 1 ? event.target : event.target?.parentElement;
            const component = editableTextComponentFrom(target);
            if (! component) return;

            // Stop the native ComponentTextView handler so activation happens
            // exactly once through this explicit interaction path.
            event.stopPropagation();
            if (editor.getSelected() !== component) {
                editor.select(component);
            }
            component.trigger('active', event);
        };

        doc.addEventListener('dblclick', onDoubleClick, true);
        documents.add(doc);
        listeners.set(doc, onDoubleClick);
    };

    const attachFrame = frame => attach(frame?.window?.document || editor.Canvas.getDocument());
    editor.on('canvas:frame:load', attachFrame);
    editor.on('load', () => attach(editor.Canvas.getDocument()));
    setTimeout(() => attach(editor.Canvas.getDocument()), 0);

    return () => {
        editor.off('canvas:frame:load', attachFrame);
        listeners.forEach((onDoubleClick, doc) => {
            doc.removeEventListener('dblclick', onDoubleClick, true);
        });
        documents.clear();
        listeners.clear();
    };
}

function keepToolbarVisibleAndClearOfText(toolbarPosition) {
    const gap = 8;
    const toolbarIsAbove = toolbarPosition.top < 0;

    // `top` is relative to the edited element. Keep a visible gap and retain
    // GrapesJS's viewport-aware choice between placing it above or below.
    toolbarPosition.top = toolbarIsAbove
        ? -toolbarPosition.targetHeight - gap
        : toolbarPosition.elRect.height + gap;

    // GrapesJS 0.23 clamps the toolbar to the edited element after clamping it
    // to the canvas. If the text is narrower than the toolbar, that second
    // clamp produces a negative `left` value and hides the toolbar beyond the
    // canvas edge. Anchor at the text's left edge, then clamp that position to
    // the visible canvas instead.
    const canvasLeft = Number(
        toolbarPosition.canvasOffsetLeft ?? toolbarPosition.canvasOffset?.left ?? 0
    );
    const canvasWidth = Number(toolbarPosition.canvasRect?.width ?? 0);
    const toolbarWidth = Number(toolbarPosition.targetWidth ?? 0);
    if (canvasWidth <= 0 || toolbarWidth <= 0) return;

    const minimumLeft = gap - canvasLeft;
    const maximumLeft = canvasWidth - toolbarWidth - gap - canvasLeft;
    toolbarPosition.left = maximumLeft >= minimumLeft
        ? Math.min(Math.max(0, minimumLeft), maximumLeft)
        : -canvasLeft;
}

function sourceAction(editor, labels) {
    return {
        icon: '<span aria-hidden="true">&lt;/&gt;</span>',
        attributes: {
            title: labels.source,
            'aria-label': labels.source,
            'data-vihzhuo-rte-control': 'source',
        },
        result(rte) {
            const editingView = editor.getEditing();
            const component = editingView?.model || editor.getSelected();
            const content = document.createElement('div');
            content.className = 'vihzhuo-source-editor';

            const textarea = document.createElement('textarea');
            textarea.className = 'form-control';
            textarea.setAttribute('aria-label', labels.source);
            textarea.spellcheck = false;
            textarea.value = rte.el.innerHTML;

            const actions = document.createElement('div');
            actions.className = 'vihzhuo-source-editor__actions';
            const cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'btn btn-secondary';
            cancel.textContent = labels.cancel;
            cancel.addEventListener('click', () => editor.Modal.close());

            const apply = document.createElement('button');
            apply.type = 'button';
            apply.className = 'btn btn-primary';
            apply.textContent = labels.apply;
            apply.addEventListener('click', () => {
                const html = textarea.value;
                rte.el.innerHTML = html;
                if (component?.get('type') === 'raw-content') {
                    component.components().reset([]);
                    component.set('content', html);
                } else if (component) {
                    component.components(html);
                }
                editor.Modal.close();
            });

            actions.append(cancel, apply);
            content.append(textarea, actions);
            editor.Modal.open({title: labels.sourceTitle, content});
            setTimeout(() => textarea.focus(), 0);
        },
    };
}

export default function builtInRtePlugin(editor, options = {}) {
    const labels = Object.assign({
        source: 'HTML source',
        sourceTitle: 'Edit HTML source',
        fontColor: 'Font color',
        fontSize: 'Font size',
        cancel: 'Cancel',
        apply: 'Apply',
    }, options.labels || {});
    const fontSizes = options.fontSizes || DEFAULT_FONT_SIZES;
    const rteConfig = editor.RichTextEditor.getConfig();
    const colorInput = document.createElement('input');
    colorInput.type = 'color';
    colorInput.value = '#000000';
    colorInput.setAttribute('aria-label', labels.fontColor);
    const fontSizeSelect = document.createElement('select');
    fontSizeSelect.setAttribute('aria-label', labels.fontSize);
    fontSizes.forEach(size => {
        const option = document.createElement('option');
        option.value = size;
        option.textContent = size;
        fontSizeSelect.appendChild(option);
    });

    const actions = [
        {
            name: 'fontColor',
            icon: colorInput,
            event: 'input',
            attributes: {
                title: labels.fontColor,
                'aria-label': labels.fontColor,
                'data-vihzhuo-rte-control': 'color',
            },
            result: (rte, action) => applyInlineStyle(rte, 'color', action.btn.querySelector('input').value),
        },
        {
            name: 'fontSize',
            icon: fontSizeSelect,
            event: 'change',
            attributes: {
                title: labels.fontSize,
                'aria-label': labels.fontSize,
                'data-vihzhuo-rte-control': 'size',
            },
            result: (rte, action) => applyInlineStyle(rte, 'font-size', action.btn.querySelector('select').value),
        },
        Object.assign({name: 'source'}, sourceAction(editor, labels)),
    ];

    // GrapesJS initializes the RTE toolbar immediately after its plugins.
    // Defer one tick so custom controls can be registered through the public API.
    const registration = setTimeout(() => {
        actions.forEach(action => {
            const {name, ...definition} = action;
            editor.RichTextEditor.add(name, definition);
        });
    }, 0);
    const removeDoubleClickActivation = installDoubleClickActivation(editor);
    editor.on('rteToolbarPosUpdate', keepToolbarVisibleAndClearOfText);

    rteConfig.onPaste = ({ev, rte}) => {
        const clipboard = ev.clipboardData;
        if (!clipboard) return;

        const html = clipboard.getData('text/html');
        const text = clipboard.getData('text/plain');
        ev.preventDefault();
        if (html) {
            rte.insertHTML(sanitizePastedHtml(html, rte.doc));
            return;
        }

        const holder = rte.doc.createElement('div');
        holder.textContent = text;
        rte.insertHTML(holder.innerHTML.replace(/(?:\r\n|\r|\n)/g, '<br>'));
    };

    return () => {
        clearTimeout(registration);
        removeDoubleClickActivation();
        editor.off('rteToolbarPosUpdate', keepToolbarVisibleAndClearOfText);
        actions.forEach(action => editor.RichTextEditor.remove(action.name));
    };
}
