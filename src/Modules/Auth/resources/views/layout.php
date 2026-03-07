<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= phpb_trans('auth.title') ?></title>

    <link rel="stylesheet" href="<?= phpb_asset('pagebuilder/bootstrap-v4.3.1.min.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link href="<?= phpb_asset('auth/app.css') ?>" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container">
    <?php
    require  __DIR__ . '/' . $viewFile . '.php';
    ?>

    <footer class="my-5 pt-5 text-muted text-center text-small">
        <p class="mb-1">Powered by <a href="https://github.com/nomadicjosh/visio" target="_blank">Visio</a></p>
    </footer>
</div>

<script src="<?= phpb_asset('pagebuilder/jquery-3.4.1.min.js') ?>"></script>
<script src="<?= phpb_asset('pagebuilder/bootstrap-v4.3.1.min.js') ?>"></script>
</body>
</html>
