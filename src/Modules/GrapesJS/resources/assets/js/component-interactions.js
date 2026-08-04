/**
 * Make an editable canvas component independently selectable and stylable.
 * GrapesJS 0.23 no longer treats `editable` as implying these capabilities.
 */
export function enableEditableInteraction(component) {
    component.set({
        editable: true,
        hoverable: true,
        selectable: true,
        highlightable: true,
        stylable: true,
    });

    return component;
}

/**
 * Resolve helper/drop-zone components to the nearest useful canvas target.
 */
export function findInteractiveSelectionTarget(component) {
    let candidate = component;
    while (candidate) {
        const hasTraits = candidate.getTraits().length > 0;
        const isInteractive = candidate.get('selectable') && (
            candidate.get('stylable') || candidate.get('editable') || hasTraits
        );
        if (isInteractive) return candidate;
        candidate = candidate.parent();
    }

    return component;
}

/**
 * Resolve Settings independently from the Style Manager target. Prefer the
 * owning page-builder block because its config traits belong to the block root,
 * even when the user clicked an editable/stylable child inside that block.
 */
export function findSettingsTarget(component) {
    let candidate = component;
    let nearestTraitComponent = null;

    while (candidate) {
        if (candidate.getTraits().length > 0) {
            if (candidate.attributes['block-slug'] !== undefined) {
                return candidate;
            }
            nearestTraitComponent ||= candidate;
        }
        candidate = candidate.parent();
    }

    return nearestTraitComponent || component;
}

/**
 * Select the configured block which owns the Settings traits. GrapesJS 0.23's
 * default TraitsView renders from editor.getSelected(), not from the target
 * passed to TraitManager.select(), so changing only the Trait Manager target
 * leaves the visible Settings panel empty.
 */
export function selectSettingsOwner(editor, component) {
    const settingsTarget = findSettingsTarget(component);
    if (settingsTarget.getTraits().length > 0 && editor.getSelected() !== settingsTarget) {
        editor.select(settingsTarget, {forceChange: true});
    }

    return settingsTarget;
}

/**
 * Synchronize the native Trait Manager with the selected settings owner.
 * Rendering remains owned by GrapesJS's open-tm command, as in the original
 * page-builder workflow.
 */
export function synchronizeSettingsTarget(editor, component) {
    const settingsTarget = selectSettingsOwner(editor, component);
    editor.TraitManager.select(settingsTarget);

    return settingsTarget;
}

/**
 * Resolve a canvas DOM node to its component and select the owning configured
 * block. This covers clicks on non-selectable children (eg. a video's
 * responsive wrapper), for which GrapesJS emits no component:selected event.
 */
export function selectSettingsOwnerFromElement(editor, element) {
    let componentElement = element?.closest?.('[data-gjs-type]');
    while (componentElement) {
        const component = componentElement.__gjsv?.model ||
            (componentElement.id ? editor.Components.getById(componentElement.id) : null) ||
            Object.values(editor.Components.allById()).find(candidate => candidate.getEl() === componentElement);
        if (component) {
            const settingsTarget = findSettingsTarget(component);
            if (settingsTarget.getTraits().length > 0) {
                synchronizeSettingsTarget(editor, settingsTarget);
                return settingsTarget;
            }
        }
        componentElement = componentElement.parentElement?.closest?.('[data-gjs-type]');
    }

    return null;
}

/**
 * Keep the Style Manager target synchronized with the canvas selection.
 */
export function synchronizeStyleTarget(editor, component, force = false) {
    if (! component.get('stylable')) return null;

    const expectedTarget = editor.StyleManager.getModelToStyle(component);
    if (force || editor.StyleManager.getSelected() !== expectedTarget) {
        editor.StyleManager.select(component);
    }

    return editor.StyleManager.getSelected();
}
