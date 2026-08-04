/**
 * Activate one of the sidebar views through GrapesJS models. This keeps the
 * button state and command state synchronized, even if the tab is active.
 */
export function activateSidebarTab(editor, buttonId, commandId) {
    const panel = editor.Panels.getPanel('views');
    if (! panel) return;

    const buttons = panel.get('buttons');
    const button = editor.Panels.getButton('views', buttonId) ||
        buttons.find(candidate => candidate.get('command') === commandId);
    if (! button) return;

    buttons.deactivateAllExceptOne(button);
    if (! button.get('active')) {
        button.set('active', true);
    }
    // Re-run idempotently so a panel whose command state was activated before
    // its view mounted is repaired immediately.
    editor.runCommand(commandId, {sender: button, force: true});
}

/**
 * Add an empty state without replacing GrapesJS's managed trait container.
 * GrapesJS rebuilds the target child on each component selection.
 */
export function renderEmptyTraitsMessage(editor, component, messageText) {
    const documentRoot = editor.getContainer()?.ownerDocument || document;
    const traitsRoot = editor.TraitManager.getTraitsViewer()?.el ||
        documentRoot.querySelector('.gjs-traits-cs, .gjs-trt-traits');
    if (! traitsRoot) return;

    traitsRoot.querySelectorAll('.no-settings').forEach(message => message.remove());
    if (component.getTraits().length > 0) return;

    const message = documentRoot.createElement('p');
    message.className = 'no-settings';
    message.textContent = messageText;
    const emptyTraitsContainer = traitsRoot.querySelector('[data-no-categories]') || traitsRoot;
    emptyTraitsContainer.appendChild(message);
}
