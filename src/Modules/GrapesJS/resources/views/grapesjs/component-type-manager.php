<script type="text/javascript">

const nativeLinkType = editor.DomComponents.getType('link');
editor.DomComponents.addType('link', {
    model: {
        defaults: {
            traits: [
                {
                    type: 'text',
                    label: '<?= phpb_trans('pagebuilder.trait-manager.link.text') ?>',
                    name: 'content',
                    changeProp: 1,
                },
                {
                    type: 'text',
                    label: 'URL',
                    name: 'href',
                },
                {
                    type: 'text',
                    label: '<?= phpb_trans('pagebuilder.trait-manager.link.tooltip') ?>',
                    name: 'title',
                },
                {
                    type: 'select',
                    label: '<?= phpb_trans('pagebuilder.trait-manager.link.target') ?>',
                    name: 'target',
                    options: [
                        {value: '_blank', name: '<?= phpb_trans('pagebuilder.yes') ?>'},
                        // An empty target keeps navigation in the current tab. A
                        // boolean false is stringified by the native select view
                        // and can become target="false" instead of selecting No.
                        {value: '', name: '<?= phpb_trans('pagebuilder.no') ?>'},
                    ],
                }
            ],
        },
        init() {
            // Saved HTML is parsed back into child components, while the
            // changeProp trait reads the link model's content property. Seed
            // that property silently so Settings shows the persisted label
            // without rewriting the canvas or dirtying the page on load.
            const content = this.get('content');
            if ((content === null || content === undefined || content === '') &&
                this.components().length > 0) {
                this.set('content', this.getInnerHTML(), {silent: true});
            }

            // Parsed links keep their visible label as child components. The
            // changeProp trait updates only the model's content property, so
            // synchronize that value back into the rendered child collection.
            this.on('change:content', this.updateContentFromTrait, this);
        },
        updateContentFromTrait() {
            const content = this.get('content');
            this.components(content === null || content === undefined ? '' : String(content));
        },
    },
    view: {
        updateContent() {
            // updateContentFromTrait() replaces the child collection before
            // GrapesJS handles change:content. The native view then sees that
            // collection and clears the element, leaving the saved model right
            // but the mounted canvas blank until reload. Collection events have
            // already rendered the new child, so preserve it here.
            if (this.model.components().length > 0) return;

            nativeLinkType.view.prototype.updateContent.call(this);
        },
    },
});

const textType = editor.DomComponents.getType('text');
editor.DomComponents.addType('text', {
    model: {
        defaults: {
            traits: [],
            attributes: {},
        },
    },
    // Retain GrapesJS's native text view. Its dblclick event enters edit mode;
    // binding click/touchend to onActive makes ordinary selection show a caret
    // and prevents the canvas selection command from updating the sidebar.
    view: textType.view,
});

/**
 * The raw-content type does not transform child elements into GrapesJS components.
 * This is important to prevent the RTE editor from dealing with html elements modified by GrapesJS (for instance removed inline styling).
 * Source: https://github.com/artf/grapesjs/issues/774#issuecomment-358963099
 */
editor.DomComponents.addType('raw-content', {
    model: textType.model.extend({
        },{
            isComponent: function(el) {
                if (el.hasAttribute && (el.hasAttribute('data-raw-content') || el.hasAttribute('phpb-editable'))) {
                    return {
                        type: 'raw-content',
                        content: el.innerHTML,
                        components: [] // avoid parsing children
                    };
                }
            }
        }
    ),
    view: textType.view
});

editor.DomComponents.addType('row', {
    model: {
        defaults: {
            traits: [],
            attributes: {},
        },
    },
});

editor.DomComponents.addType('cell', {
    model: {
        defaults: {
            traits: [],
            attributes: {},
        },
    },
});

editor.DomComponents.addType('default', {
    model: {
        defaults: {
            traits: [],
            attributes: {},
        },
    },
});

editor.DomComponents.addType('image', {
    model: {
        defaults: {
            traits: [
                {
                    type: 'text',
                    label: "<?= phpb_trans('pagebuilder.trait-manager.image.title') ?>",
                    name: 'title',
                },
                {
                    type: 'text',
                    label: "<?= phpb_trans('pagebuilder.trait-manager.image.alt') ?>",
                    name: 'alt',
                },
            ]
        },
    },
});
</script>
