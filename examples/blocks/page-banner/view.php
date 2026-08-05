<?php

declare(strict_types=1);

/** @var \Vihzhuo\Examples\Blocks\PageBanner\Model $block */
$showContext = $block->setting('show_context') === '1';
?>
<div class="example-page-banner alert alert-<?= $block->setting('tone') ?>" role="region">
    <p class="small text-uppercase mb-1"><?= $block->setting('eyebrow') ?></p>
    <h2><?= $block->setting('heading') ?></h2>
    <p><?= $block->setting('message') ?></p>

    <?php if ($showContext): ?>
        <p class="small mb-2">
            Page: <?= phpb_e($block->pageName()) ?>
            <span aria-hidden="true">&middot;</span>
            <?= phpb_e($block->renderingContext()) ?>
        </p>
    <?php endif; ?>

    <a class="alert-link" href="<?= $block->linkUrl() ?>">
        <?= $block->setting('link_label') ?>
    </a>
</div>
