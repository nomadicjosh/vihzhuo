<?php
$settingClass = phpb_static('setting');
$languageTranslations = phpb_trans('languages');
$languageTranslations = is_array($languageTranslations) ? $languageTranslations : [];
?>

<form method="post" action="<?= phpb_url('website_manager', ['route' => 'settings', 'action' => 'update', 'tab' => 'settings']) ?>">

    <div class="main-spacing">
        <?php
        if (phpb_flash('message')):
        ?>
        <div class="alert alert-<?= phpb_flash('message-type') ?>">
            <?= phpb_flash('message') ?>
        </div>
        <?php
        endif;
        ?>

        <div class="form-group required">
            <label for="languages">
                <?= phpb_trans('website-manager.website-languages') ?>
            </label>
            <select class="form-control" id="languages" name="languages[]" title="<?= phpb_trans('website-manager.languages-selector-placeholder') ?>" required multiple>
                <?php
                foreach ($languageTranslations as $locale => $localeText):
                    if (!is_string($locale) || !is_string($localeText)) {
                        continue;
                    }
                    $selected = $settingClass !== null && $settingClass::has('languages', $locale);
                ?>
                <option value="<?= phpb_e($locale) ?>" <?= $selected ? 'selected' : '' ?>><?= phpb_e($localeText) ?></option>
                <?php
                endforeach;
                ?>
            </select>
        </div>

        <hr class="mb-3">

        <button class="btn btn-primary btn-sm">
            <?= phpb_trans('website-manager.save-settings'); ?>
        </button>
    </div>

</form>
