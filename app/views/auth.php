<?php
declare(strict_types=1);

$isRegister = $mode === 'register';
$formAction = $isRegister ? 'index.php?action=register' : 'index.php?action=login';
$buttonLabel = $isRegister ? 'REGISTRAR' : 'INGRESAR';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $isRegister ? 'Registro' : 'Login' ?> - Reserva Salas Cine</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-screen">
    <main class="auth-layout" aria-label="Autenticacion">
        <div class="auth-logo" aria-label="ES Cine">
            <div class="logo-person" aria-hidden="true"></div>
            <div class="logo-film"><span>ES</span> <em>Cine</em></div>
        </div>

        <section class="auth-card">
            <nav class="auth-tabs" aria-label="Opciones de autenticacion">
                <a class="auth-tab <?= $isRegister ? '' : 'is-active' ?>" href="index.php?page=login">INICIA SESION</a>
                <a class="auth-tab <?= $isRegister ? 'is-active' : '' ?>" href="index.php?page=register">REGISTRATE</a>
            </nav>

            <?php if ($messages !== [] || $errors !== []): ?>
                <div class="auth-messages" aria-live="polite">
                    <?php foreach ($messages as $message): ?>
                        <p class="notice notice-<?= e($message['type'] ?? 'info') ?>"><?= e($message['message'] ?? '') ?></p>
                    <?php endforeach; ?>
                    <?php foreach ($errors as $error): ?>
                        <p class="notice notice-error"><?= e($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" action="<?= e($formAction) ?>" method="post" novalidate>
                <?= csrf_token_field() ?>
                <?php if ($isRegister): ?>
                    <div class="form-field">
                        <label for="name">Nombre</label>
                        <input id="name" name="name" type="text" value="<?= e($old['name'] ?? '') ?>" autocomplete="name">
                    </div>
                <?php endif; ?>

                <div class="form-field">
                    <label for="email">Correo electronico</label>
                    <input id="email" name="email" type="email" value="<?= e($old['email'] ?? '') ?>" autocomplete="email">
                </div>

                <div class="form-field">
                    <label for="password">Contrasena</label>
                    <input id="password" name="password" type="password" autocomplete="<?= $isRegister ? 'new-password' : 'current-password' ?>">
                </div>

                <button class="auth-submit" type="submit"><?= e($buttonLabel) ?></button>
            </form>
        </section>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
