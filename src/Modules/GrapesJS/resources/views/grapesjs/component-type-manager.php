<script type="text/javascript">

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
                        {value: false, name: '<?= phpb_trans('pagebuilder.no') ?>'},
                    ],
                }
            ],
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
