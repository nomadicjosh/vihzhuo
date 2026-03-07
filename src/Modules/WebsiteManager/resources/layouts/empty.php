<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vihzhuo</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="<?= phpb_asset('websitemanager/images/favicon-32x32.png') ?>">

    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #f8f9fa 0%, #eef2ff 100%);
            display: flex;
            align-items: center;
        }

        .hero-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(0,0,0,.08);
        }

        .brand-title {
            font-weight: 700;
            letter-spacing: .5px;
        }

        .intro-links a {
            margin: 0 .5rem;
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">

            <?php
            require __DIR__ . '/../views/' . $viewFile . '.php';
            ?>

        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
