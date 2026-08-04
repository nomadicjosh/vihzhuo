const SELECTOR_MANAGER_TYPE = 'vihzhuo-selector-manager';

/**
 * Add a rendered host for GrapesJS's class selector UI. GrapesJS 0.23 does
 * not provide a usable properties container for a completely empty sector.
 */
export function installAdvancedSector(editor, name) {
    const styleManager = editor.StyleManager;
    if (! styleManager.getType(SELECTOR_MANAGER_TYPE)) {
        styleManager.addType(SELECTOR_MANAGER_TYPE, {
            create() {
                const host = editor.getContainer().ownerDocument.createElement('div');
                host.className = 'vihzhuo-selector-manager-host';
                return host;
            },
            // Selector Manager owns its inputs; never treat their change events
            // as a value for this host-only custom property.
            emit() {},
            update() {},
        });
    }

    const sector = styleManager.addSector('advanced', {
        name,
        open: false,
        properties: [{
            id: 'selector-manager',
            property: '--vihzhuo-selector-manager',
            type: SELECTOR_MANAGER_TYPE,
            className: 'vihzhuo-selector-manager-property',
            name: '',
            full: true,
        }],
    }, {at: 10});

    if (! sector.__vihzhuoSelectorManagerListener) {
        sector.__vihzhuoSelectorManagerListener = true;
        sector.on('change:open', () => {
            editor.getContainer().ownerDocument.defaultView.requestAnimationFrame(() => {
                mountSelectorManager(editor);
            });
        });
    }

    return sector;
}

/** Move the already-rendered Selector Manager into the Advanced sector. */
export function mountSelectorManager(editor) {
    const documentRoot = editor.getContainer().ownerDocument;
    const host = documentRoot.querySelector('.vihzhuo-selector-manager-host');
    const selectorManager = documentRoot.querySelector('.gjs-clm-tags');
    if (host && selectorManager && selectorManager.parentElement !== host) {
        host.appendChild(selectorManager);
    }

    return Boolean(host?.contains(selectorManager));
}
