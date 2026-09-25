<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($name) ?></title>
    <style>
        body{font:16px/1.6 system-ui,sans-serif;max-width:760px;margin:10vh auto;padding:0 24px;color:#171717}
        code{background:#f3f3f3;padding:.15rem .4rem;border-radius:5px}h1{font-size:2.4rem;margin-bottom:.2rem}
        .muted{color:#666}a{color:inherit}
    </style>
</head>
<body>
    <h1><?= e($name) ?></h1>
    <p class="muted">v<?= e($version) ?> · PHP <?= e(PHP_VERSION) ?></p>
    <p>Your SedoPHP application is running.</p>
    <p>Edit <code>routes/web.php</code>, <code>app/Controllers</code> and <code>app/Views</code> to start building.</p>
</body>
</html>
