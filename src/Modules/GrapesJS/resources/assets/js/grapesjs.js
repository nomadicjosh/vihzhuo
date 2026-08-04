import grapesjs from 'grapesjs';
import ckeditorPlugin from 'grapesjs-plugin-ckeditor';
import touchPlugin from 'grapesjs-touch';
import builtInRtePlugin from './built-in-rte';
import {activateSidebarTab, renderEmptyTraitsMessage} from './sidebar-panels';
import {
    enableEditableInteraction,
    findInteractiveSelectionTarget,
    findSettingsTarget,
    selectSettingsOwner,
    selectSettingsOwnerFromElement,
    synchronizeSettingsTarget,
    synchronizeStyleTarget,
} from './component-interactions';
import {installAdvancedSector, mountSelectorManager} from './style-manager-extensions';

window.grapesjs = grapesjs;
window.VihzhuoGrapesJS = Object.freeze({
    version: grapesjs.version,
    plugins: Object.freeze({
        ckeditor: ckeditorPlugin,
        touch: touchPlugin,
        builtInRte: builtInRtePlugin,
    }),
    sidebarPanels: Object.freeze({
        activate: activateSidebarTab,
        renderEmptyTraitsMessage,
    }),
    componentInteractions: Object.freeze({
        enableEditable: enableEditableInteraction,
        findSelectionTarget: findInteractiveSelectionTarget,
        findSettingsTarget,
        selectSettingsOwner,
        selectSettingsOwnerFromElement,
        synchronizeSettingsTarget,
        synchronizeStyleTarget,
    }),
    styleManager: Object.freeze({
        installAdvancedSector,
        mountSelectorManager,
    }),
});
