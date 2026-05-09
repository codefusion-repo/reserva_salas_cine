<?php
declare(strict_types=1);

$pageTitle = (string) ($comingSoon['title'] ?? 'Proximamente');
$activeNav = (string) ($comingSoon['activeNav'] ?? '');
$items = is_array($comingSoon['items'] ?? null) ? $comingSoon['items'] : [];
$notes = is_array($comingSoon['notes'] ?? null) ? $comingSoon['notes'] : [];
$catalogItems = is_array($comingSoon['catalog'] ?? null) ? $comingSoon['catalog'] : [];
$showCatalog = ($comingSoon['showCatalog'] ?? false) === true || $catalogItems !== [];
$catalogLoadError = ($comingSoon['catalogLoadError'] ?? false) === true;
$catalogSetupRequired = ($comingSoon['catalogSetupRequired'] ?? false) === true;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - Reserva Salas Cine</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-screen coming-soon-screen">
    <?php require __DIR__ . '/partials/header.php'; ?>

    <main class="coming-soon-shell">
        <?php if ($messages !== []): ?>
            <div class="page-messages cartelera-messages" aria-live="polite">
                <?php foreach ($messages as $message): ?>
                    <p class="notice notice-<?= e($message['type'] ?? 'info') ?>"><?= e($message['message'] ?? '') ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="coming-soon-heading">
            <a class="movie-back" href="index.php?page=cartelera" aria-label="Volver a cartelera">
                <span aria-hidden="true"></span>
            </a>
            <div>
                <p class="eyebrow"><?= e($comingSoon['eyebrow'] ?? 'Proximamente') ?></p>
                <h1><?= e($comingSoon['headline'] ?? $pageTitle) ?></h1>
            </div>
        </div>

        <section class="coming-soon-panel" aria-labelledby="coming-soon-title">
            <div class="coming-soon-copy">
                <p class="coming-soon-kicker" aria-hidden="true">Próximamente...</p>
                <h2 id="coming-soon-title">Solo en cines...</h2>
                <p><?= e($comingSoon['lead'] ?? '') ?></p>
                <p><?= e($comingSoon['support'] ?? '') ?></p>

                <?php if ($notes !== []): ?>
                    <ul class="coming-soon-notes">
                        <?php foreach ($notes as $note): ?>
                            <li><?= e($note) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="coming-soon-actions">
                    <a class="movie-state-link" href="index.php?page=cartelera">Volver a cartelera</a>
                    <a class="movie-state-link movie-state-link-secondary" href="index.php?page=my_reservations">Mis reservas</a>
                </div>
            </div>

            <div class="coming-soon-feature">
                <div class="coming-soon-badge" aria-hidden="true">🚧</div>
                <span><?= e($comingSoon['accent'] ?? 'Proximamente') ?></span>
                <p><?= e($comingSoon['accentCopy'] ?? '') ?></p>
            </div>
        </section>

        <?php if ($showCatalog): ?>
            <section class="confiteria-catalog" aria-labelledby="confiteria-catalog-title">
                <div class="confiteria-catalog-heading">
                    <div>
                        <p class="eyebrow">Catálogo demo / visual</p>
                        <h2 id="confiteria-catalog-title">Combos de confitería</h2>
                    </div>
                    <p>Compra funcional aún no disponible. No hay carrito funcional ni pago real.</p>
                </div>

                <?php if ($catalogSetupRequired): ?>
                    <div class="confiteria-catalog-state">
                        <h3>Catálogo en preparación</h3>
                        <p>Los productos demo aún no están instalados en esta base de datos.</p>
                    </div>
                <?php elseif ($catalogLoadError): ?>
                    <div class="confiteria-catalog-state">
                        <h3>No se pudo cargar el catálogo</h3>
                        <p>Intenta nuevamente mas tarde.</p>
                    </div>
                <?php elseif ($catalogItems === []): ?>
                    <div class="confiteria-catalog-state">
                        <h3>Sin productos activos</h3>
                        <p>El catálogo demo no tiene productos disponibles en este momento.</p>
                    </div>
                <?php else: ?>
                    <div class="confiteria-catalog-grid">
                        <?php foreach ($catalogItems as $catalogItem): ?>
                            <?php $catalogIcon = trim((string) ($catalogItem['icon'] ?? '')); ?>
                            <article class="confiteria-card">
                                <div class="confiteria-card-visual">
                                    <?php if (($catalogItem['label'] ?? '') !== ''): ?>
                                        <span class="confiteria-card-label"><?= e($catalogItem['label']) ?></span>
                                    <?php endif; ?>
                                    <span class="confiteria-card-icon" aria-hidden="true"><?= e($catalogIcon !== '' ? $catalogIcon : '🍿') ?></span>
                                </div>
                                <div class="confiteria-card-copy">
                                    <h3><?= e($catalogItem['name'] ?? '') ?></h3>
                                    <p><?= e($catalogItem['description'] ?? '') ?></p>
                                    <strong><?= e($catalogItem['price'] ?? '') ?></strong>
                                    <button class="confiteria-card-button" type="button" disabled>
                                        <?= e($catalogItem['button'] ?? 'Próximamente') ?>
                                    </button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($items !== []): ?>
            <section class="coming-soon-grid" aria-label="Contenido futuro">
                <?php foreach ($items as $item): ?>
                    <article class="coming-soon-card">
                        <span class="coming-soon-card-icon" aria-hidden="true"><?= e($item['icon'] ?? '•') ?></span>
                        <h2><?= e($item['label'] ?? '') ?></h2>
                        <p><?= e($item['copy'] ?? '') ?></p>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
