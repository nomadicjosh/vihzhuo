<?php

declare(strict_types=1);

use Vihzhuo\Extensions;

// Extension blocks may live anywhere in the application or in a Composer
// package. Register them before Vihzhuo creates/enumerates the active theme.
Extensions::addBlocks([
    'example-editable-card' => __DIR__ . '/blocks/editable-card',
    'example-page-banner' => __DIR__ . '/blocks/page-banner',
]);
