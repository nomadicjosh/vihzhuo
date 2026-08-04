<script type="text/javascript">

let styleManager = editor.StyleManager;

window.VihzhuoGrapesJS.styleManager.installAdvancedSector(
    editor,
    '<?= phpb_trans('pagebuilder.style-manager.sectors.advanced') ?>'
);

<?php
foreach (phpb_trans('pagebuilder.style-manager.properties') as $sector => $sectorProperties) {
    foreach ($sectorProperties as $property => $data) {
        if (is_array($data)) {
            $counter = count($data['properties'] ?? []);
            for ($i = 0; $i < $counter; $i++) {
                $translation = $data['properties'][array_keys($data['properties'])[$i]];
                ?>
window.editor.StyleManager.getProperty('<?= phpb_e($sector) ?>', '<?= phpb_e($property) ?>').attributes.properties.models[<?= $i ?>].attributes.name = '<?= phpb_e($translation) ?>';
                <?php
            }
            ?>
window.editor.StyleManager.getProperty('<?= phpb_e($sector) ?>', '<?= phpb_e($property) ?>').set({ name: '<?= phpb_e($data['name']) ?>' });
            <?php
        } else {
            ?>
window.editor.StyleManager.getProperty('<?= phpb_e($sector) ?>', '<?= phpb_e($property) ?>').set({ name: '<?= phpb_e($data) ?>' });
            <?php
        }
    }
}
?>
</script>
