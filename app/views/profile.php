<?php
declare(strict_types=1);

$profileName = trim((string) ($profileUser['name'] ?? ''));
$profileEmail = trim((string) ($profileUser['email'] ?? ''));
$activeReservations = is_array($reservationSummary) ? (int) ($reservationSummary['active_count'] ?? 0) : null;
$cancelledReservations = is_array($reservationSummary) ? (int) ($reservationSummary['cancelled_count'] ?? 0) : null;
$latestTicketId = is_array($latestTicket) ? (int) ($latestTicket['id'] ?? 0) : 0;
$latestTicketCode = $latestTicketId > 0 ? reservation_visual_code($latestTicketId) : '';
$latestTicketMovie = is_array($latestTicket) ? trim((string) ($latestTicket['movie_title'] ?? '')) : '';
$latestTicketDate = is_array($latestTicket) ? reservation_datetime_label($latestTicket['starts_at'] ?? '') : '';
$memberDemoStatusLabel = trim((string) ($memberDemoStatusLabel ?? ($memberDemoActive ? 'Activa' : 'Inactiva')));
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi perfil - Reserva Salas Cine</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-screen profile-screen">
    <?php
    $activeNav = 'profile';
    $user = $profileUser;
    require __DIR__ . '/partials/header.php';
    ?>

    <main class="profile-shell">
        <?php if ($messages !== []): ?>
            <div class="page-messages cartelera-messages" aria-live="polite">
                <?php foreach ($messages as $message): ?>
                    <p class="notice notice-<?= e($message['type'] ?? 'info') ?>"><?= e($message['message'] ?? '') ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="profile-heading">
            <a class="movie-back" href="index.php?page=cartelera" aria-label="Volver a cartelera">
                <span aria-hidden="true"></span>
            </a>
            <div>
                <p class="eyebrow">Cuenta</p>
                <h1>Mi perfil</h1>
            </div>
        </div>

        <section class="profile-identity" aria-labelledby="profile-name">
            <div class="profile-avatar-large" aria-hidden="true"></div>
            <div class="profile-identity-main">
                <p class="profile-kicker">Usuario autenticado</p>
                <h2 id="profile-name"><?= e($profileName !== '' ? $profileName : 'Usuario') ?></h2>
                <dl class="profile-account-details">
                    <div>
                        <dt>Email</dt>
                        <dd><?= e($profileEmail !== '' ? $profileEmail : 'No informado') ?></dd>
                    </div>
                    <div>
                        <dt>Rol</dt>
                        <dd><?= e($profileRoleLabel) ?></dd>
                    </div>
                </dl>
                <?php if ($profileUserLoadError): ?>
                    <p class="profile-safe-note">Algunos datos no se pudieron actualizar en este momento.</p>
                <?php endif; ?>
            </div>
            <div class="profile-member-state<?= $memberDemoActive ? ' is-active' : '' ?>">
                <span><?= e($memberDemoActive ? 'Socio Cine activo' : $memberDemoStatusLabel) ?></span>
                <a href="index.php?page=socios">Socios</a>
            </div>
        </section>

        <section class="profile-stats" aria-label="Resumen del perfil">
            <article class="profile-stat-card">
                <span class="profile-stat-icon" aria-hidden="true">🎟️</span>
                <div>
                    <h2>Reservas activas</h2>
                    <p><?= $reservationSummaryLoadError ? 'No disponible' : e((string) ($activeReservations ?? 0)) ?></p>
                </div>
            </article>

            <article class="profile-stat-card">
                <span class="profile-stat-icon" aria-hidden="true">🧾</span>
                <div>
                    <h2>Reservas canceladas</h2>
                    <p><?= $reservationSummaryLoadError ? 'No disponible' : e((string) ($cancelledReservations ?? 0)) ?></p>
                </div>
            </article>

            <article class="profile-stat-card">
                <span class="profile-stat-icon" aria-hidden="true">💳</span>
                <div>
                    <h2>Pagos</h2>
                    <p><?= $paymentSummaryLoadError ? 'No disponible' : e((string) ($paymentCount ?? 0)) ?></p>
                </div>
            </article>

            <article class="profile-stat-card">
                <span class="profile-stat-icon" aria-hidden="true">⭐</span>
                <div>
                    <h2>Membresia</h2>
                    <p><?= e($memberDemoStatusLabel) ?></p>
                </div>
            </article>
        </section>

        <section class="profile-links-section" aria-labelledby="profile-links-title">
            <div class="profile-section-heading">
                <p class="eyebrow">Accesos</p>
                <h2 id="profile-links-title">Secciones principales</h2>
            </div>

            <div class="profile-link-grid">
                <a class="profile-link-card" href="index.php?page=my_reservations">
                    <span aria-hidden="true">🎟️</span>
                    <strong>Mis reservas</strong>
                    <small>Ver reservas y cancelaciones</small>
                </a>
                <a class="profile-link-card" href="index.php?page=my_payments">
                    <span aria-hidden="true">💳</span>
                    <strong>Mis pagos</strong>
                    <small>Comprobantes y detalles</small>
                </a>
                <a class="profile-link-card" href="index.php?page=socios">
                    <span aria-hidden="true">⭐</span>
                    <strong>Socios</strong>
                    <small>Estado de membresia</small>
                </a>
                <a class="profile-link-card" href="index.php?page=cartelera">
                    <span aria-hidden="true">🎬</span>
                    <strong>Cartelera</strong>
                    <small>Funciones disponibles</small>
                </a>
                <?php if ($latestTicketId > 0): ?>
                    <a class="profile-link-card profile-link-card-wide" href="index.php?page=ticket&amp;reservation_id=<?= e($latestTicketId) ?>">
                        <span aria-hidden="true">🧾</span>
                        <strong>Ultimo ticket <?= e($latestTicketCode) ?></strong>
                        <small>
                            <?= e($latestTicketMovie !== '' ? $latestTicketMovie : 'Reserva') ?>
                            <?= $latestTicketDate !== '' ? ' - ' . e($latestTicketDate) : '' ?>
                        </small>
                    </a>
                <?php elseif ($latestTicketLoadError): ?>
                    <div class="profile-link-card profile-link-card-muted profile-link-card-wide">
                        <span aria-hidden="true">🧾</span>
                        <strong>Ultimo ticket</strong>
                        <small>No disponible en este momento</small>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
