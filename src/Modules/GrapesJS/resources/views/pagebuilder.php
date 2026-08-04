<div id="phpb-loading">
    <div class="circle">
        <div class="loader">
            <div class="loader">
                <div class="loader">
                    <div class="loader"></div>
                </div>
            </div>
        </div>
        <div class="text">
            <?= phpb_trans('pagebuilder.loading-text') ?>
        </div>
    </div>
</div>

<div id="gjs"></div>

<script type="text/javascript">
<?php
$currentLanguage = in_array(phpb_config('general.language'), phpb_active_languages()) ?
phpb_config('general.language') : array_keys(phpb_active_languages())[0];
if (! empty($_SESSION['phpagebuilder_language'])) {
    $currentLanguage = $_SESSION['phpagebuilder_language'];
}
?>
window.languages = <?= phpb_json(phpb_active_languages()) ?>;
window.currentLanguage = <?= phpb_json($currentLanguage) ?>;
window.translations = <?= phpb_json(phpb_trans('pagebuilder')) ?>;
window.contentContainerComponents = <?= phpb_json($pageBuilder->getPageComponents($page)) ?>;
window.themeBlocks = <?= phpb_json($blocks) ?>;
window.blockSettings = <?= phpb_json($blockSettings) ?>;
window.pageBlocks = <?= phpb_json($pageRenderer->getPageBlocksData()) ?>;
window.pages = <?= phpb_json($pageBuilder->getPages()) ?>;
window.renderBlockUrl = <?= phpb_json(phpb_url('pagebuilder', ['action' => 'renderBlock', 'page' => $page->getId()])) ?>;
window.injectionScriptUrl = <?= phpb_json(phpb_asset('pagebuilder/page-injection.js')) ?>;
window.renderLanguageVariantUrl = <?= phpb_json(phpb_url('pagebuilder', ['action' => 'renderLanguageVariant', 'page' => $page->getId()])) ?>;

<?php
$config = require __DIR__ . '/grapesjs/config.php';
?>
let config = <?= phpb_json($config) ?>;
if (window.customConfig !== undefined) {
    config = $.extend(true, {}, config, window.customConfig);
}

// Keep the pre-1.0 plugin alias working and pass bundled plugins directly to
// GrapesJS. String-based global registration is deprecated as of GrapesJS 0.23.
const bundledPlugins = window.VihzhuoGrapesJS.plugins;
config.plugins = (config.plugins || []).map(plugin => {
    if (plugin === 'gjs-plugin-ckeditor' || plugin === 'grapesjs-plugin-ckeditor') {
        return {id: 'grapesjs-plugin-ckeditor', plugin: bundledPlugins.ckeditor};
    }
    if (plugin === 'grapesjs-touch') {
        return {id: 'grapesjs-touch', plugin: bundledPlugins.touch};
    }
    if (plugin === 'vihzhuo-rte') {
        return {id: 'vihzhuo-rte', plugin: bundledPlugins.builtInRte};
    }
    return plugin;
});
if (config.pluginsOpts?.['gjs-plugin-ckeditor'] && !config.pluginsOpts['grapesjs-plugin-ckeditor']) {
    config.pluginsOpts['grapesjs-plugin-ckeditor'] = config.pluginsOpts['gjs-plugin-ckeditor'];
}

window.initialComponents = <?= phpb_json($pageRenderer->render()) ?>;

// GrapesJS renders component scripts independently. Move layout-level external
// dependencies to the canvas configuration so they load serially (eg. jQuery,
// then Popper, then Bootstrap) before the canvas body is mounted.
const initialDocument = new DOMParser().parseFromString(window.initialComponents, 'text/html');
const layoutScripts = [];
initialDocument.querySelectorAll('script[src]').forEach(script => {
    const source = script.getAttribute('src');
    if (!source) return;

    const descriptor = {src: new URL(source, <?= phpb_json(phpb_full_url($page->getRoute())) ?>).href};
    ['type', 'integrity', 'crossorigin', 'referrerpolicy', 'nomodule'].forEach(attribute => {
        if (script.hasAttribute(attribute)) {
            descriptor[attribute] = script.getAttribute(attribute) || '';
        }
    });
    layoutScripts.push(descriptor);
    script.remove();
});
if (layoutScripts.length > 0) {
    config.canvas = config.canvas || {};
    config.canvas.scripts = [...(config.canvas.scripts || []), ...layoutScripts];
    window.initialComponents = '<!doctype html>\n' + initialDocument.documentElement.outerHTML;
}

window.dispatchEvent(new CustomEvent('vihzhuo:grapesjs:before-init', { detail: config }));

window.initialStyle = <?= phpb_json($pageBuilder->getPageStyleComponents($page)) ?>;
window.initialCss = <?= phpb_json($pageBuilder->getPageStyleCss($page)) ?>;
window.grapesJSTranslations = {
    [<?= phpb_json($currentLanguage) ?>]: {
        styleManager: {
            empty: <?= phpb_json(phpb_trans('pagebuilder.style-no-element-selected')) ?>
        },
        traitManager: {
            empty: <?= phpb_json(phpb_trans('pagebuilder.trait-no-element-selected')) ?>,
            label: <?= phpb_json(phpb_trans('pagebuilder.trait-settings')) ?>,
            traits: {
                options: {
                    target: {
                        false: <?= phpb_json(phpb_trans('pagebuilder.no')) ?>,
                        _blank: <?= phpb_json(phpb_trans('pagebuilder.yes')) ?>
                    }
                }
            }
        },
        assetManager: {
            addButton: <?= phpb_json(phpb_trans('pagebuilder.asset-manager.add-image')) ?>,
            inputPlh: 'http://path/to/the/image.jpg',
            modalTitle: <?= phpb_json(phpb_trans('pagebuilder.asset-manager.modal-title')) ?>,
            uploadTitle: <?= phpb_json(phpb_trans('pagebuilder.asset-manager.drop-files')) ?>
        }
    }
};

window.grapesJSLoaded = false;
window.editor = window.grapesjs.init(config);
window.editor.on('load', function(editor) {
    window.grapesJSLoaded = true;
    window.dispatchEvent(new CustomEvent('vihzhuo:grapesjs:ready', { detail: editor }));
});
window.editor.I18n.addMessages(window.grapesJSTranslations);

// load the default or earlier saved page css components
editor.setStyle(window.initialStyle);
</script>

<?php
require __DIR__ . '/grapesjs/asset-manager.php';
require __DIR__ . '/grapesjs/component-type-manager.php';
require __DIR__ . '/grapesjs/style-manager.php';
require __DIR__ . '/grapesjs/trait-manager.php';
?>

<button id="toggle-sidebar" class="btn">
    <i class="fa fa-bars"></i>
</button>
<div id="sidebar-header">
    <?php
    if (count(phpb_active_languages()) > 1) :
        ?>
    <div id="language-selector">
        <select class="selectpicker" data-width="fit">
        <?php
        foreach (phpb_active_languages() as $languageCode => $languageTranslation) :
            ?>
            <option value="<?= phpb_e($languageCode) ?>" <?= $languageCode === $currentLanguage ? 'selected' : '' ?>
                    data-content='<span class="flag-icon flag-icon-<?= phpb_e($languageCode) ?>"></span><span class="language-name ml-1"><?= phpb_e($languageTranslation) ?></span>'>
                >
            <?= phpb_e($languageTranslation) ?>
            </option>
            <?php
        endforeach;
        ?>
        </select>
    </div>
        <?php
    endif;
    ?>
    <style>
        <?php
        foreach (phpb_active_languages() as $languageCode => $languageTranslation) :
            ?>
        .flag-icon-<?= $languageCode ?> {
            background-image: url(<?= phpb_asset('pagebuilder/images/flags/' . $languageCode . '.svg') ?>);
        }
            <?php
        endforeach;
        ?>
    </style>

    <div id="sidebar-top-device">
        <a id="set-dekstop-view" class="btn set-view" data-view="Desktop">
            <i class="fa fa-desktop"></i>
        </a>
        <a id="set-tablet-view" class="btn set-view" data-view="Tablet">
            <i class="fa fa-tablet"></i>
        </a>
        <a id="set-mobile-view" class="btn set-view" data-view="Mobile">
            <i class="fa fa-mobile"></i>
        </a>
    </div>
</div>

<div id="sidebar-bottom-buttons">
    <button id="save-page" class="btn" data-url="<?= phpb_url('pagebuilder', ['action' => 'store', 'page' => $page->getId()]) ?>">
        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
        <i class="fa fa-save"></i>
        <?= phpb_trans('pagebuilder.save-page') ?>
    </button>

    <a id="view-page" href="<?= phpb_e(phpb_full_url($page->getRoute())) ?>" target="_blank" class="btn">
        <i class="fa fa-external-link"></i>
        <?= phpb_trans('pagebuilder.view-page') ?>
    </a>

    <a id="go-back" href="<?= phpb_e(phpb_full_url(phpb_config('pagebuilder.actions.back'))) ?>" class="btn">
        <i class="fa fa-arrow-circle-left"></i>
        <?= phpb_trans('pagebuilder.go-back') ?>
    </a>
</div>

<div id="block-search">
    <i class="fa fa-search"></i>
    <input type="text" class="form-control" placeholder="<?= phpb_trans('pagebuilder.filter-placeholder') ?>">
</div>

<style>
.cke_notifications_area {
    display: none !important;
}
</style>
