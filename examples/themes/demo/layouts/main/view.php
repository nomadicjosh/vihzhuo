<?php

declare(strict_types=1);

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= phpb_e($page->get('title')) ?></title>
    <link rel="stylesheet" href="<?= phpb_e(phpb_theme_asset('css/style.css')) ?>">
</head>
<body><?= $body ?></body>
</html>
