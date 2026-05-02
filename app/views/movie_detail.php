<?php
declare(strict_types=1);

$userName = (string) ($user['name'] ?? 'Usuario');
$firstName = trim(explode(' ', $userName)[0] ?? $userName);
$hasMovie = is_array($movie);
$title = $hasMovie ? (string) ($movie['title'] ?? 'Pelicula') : 'Pelicula no encontrada';
$posterUrl = $hasMovie ? public_asset_url_if_exists($movie['poster_path'] ?? null) : null;
$metaParts = [];

if ($hasMovie) {
    $metaParts = array_filter(
        [
            (string) ($movie['genre'] ?? ''),
            (string) ($movie['release_year'] ?? ''),
            (string) ($movie['classification'] ?? ''),
        ],
        static fn (string $value): bool => trim($value) !== ''
    );
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> - Reserva Salas Cine</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-screen movie-detail-screen">
    <header class="topbar cinema-topbar">
        <a class="brand cinema-brand" href="index.php?page=cartelera" aria-label="ES Cine cartelera">
            <span class="brand-person" aria-hidden="true"></span>
            <span class="brand-film"><span>ES</span> <em>Cine</em></span>
        </a>

        <nav class="topnav cinema-nav" aria-label="Navegacion principal">
            <a class="is-active" href="index.php?page=cartelera">Cartelera</a>
            <a href="index.php?page=cartelera" aria-disabled="true">Confiteria</a>
            <a href="index.php?page=cartelera" aria-disabled="true">¡Hazte socio!</a>
            <?php if (($user['role'] ?? '') === 'admin'): ?>
                <a href="index.php?page=admin">Admin</a>
            <?php endif; ?>
        </nav>

        <div class="user-menu">
            <button class="user-pill" type="button" aria-haspopup="true">
                <span class="user-avatar" aria-hidden="true"></span>
                <span>Hola, <?= e($firstName !== '' ? $firstName : 'Usuario') ?>!</span>
                <span class="user-caret" aria-hidden="true"></span>
            </button>
            <div class="user-dropdown">
                <span><?= e($user['email'] ?? '') ?></span>
                <a href="index.php?action=logout">Cerrar sesion</a>
            </div>
        </div>
    </header>

    <main class="movie-detail-shell" data-movie-detail>
        <?php if ($messages !== []): ?>
            <div class="page-messages cartelera-messages" aria-live="polite">
                <?php foreach ($messages as $message): ?>
                    <p class="notice notice-<?= e($message['type'] ?? 'info') ?>"><?= e($message['message'] ?? '') ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($movieLoadError): ?>
            <section class="cartelera-state movie-detail-state">
                <h1>No se pudo cargar la pelicula</h1>
                <p>Intenta nuevamente mas tarde.</p>
                <a class="movie-state-link" href="index.php?page=cartelera">Volver a cartelera</a>
            </section>
        <?php elseif ($movieNotFound || !$hasMovie): ?>
            <section class="cartelera-state movie-detail-state">
                <h1>Pelicula no encontrada</h1>
                <p>La pelicula solicitada no existe o no esta activa.</p>
                <a class="movie-state-link" href="index.php?page=cartelera">Volver a cartelera</a>
            </section>
        <?php else: ?>
            <div class="movie-detail-layout">
                <section class="movie-identity" aria-labelledby="movie-title">
                    <div class="movie-title-row">
                        <a class="movie-back" href="index.php?page=cartelera" aria-label="Volver a cartelera">
                            <span aria-hidden="true"></span>
                        </a>
                        <h1 id="movie-title"><?= e($title) ?></h1>
                    </div>

                    <div class="movie-copy-layout">
                        <div class="movie-detail-poster-frame">
                            <?php if ($posterUrl !== null): ?>
                                <img class="movie-detail-poster" src="<?= e($posterUrl) ?>" alt="Poster de <?= e($title) ?>">
                            <?php else: ?>
                                <div class="movie-poster-placeholder movie-detail-placeholder" role="img" aria-label="Poster no disponible para <?= e($title) ?>">
                                    <span>ES Cine</span>
                                    <strong><?= e($title) ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="movie-synopsis">
                            <h2>SINOPSIS</h2>
                            <p><?= e($movie['synopsis'] ?? '') ?></p>
                            <?php if ($metaParts !== []): ?>
                                <p class="movie-detail-meta"><?= e(implode(' - ', $metaParts)) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <section class="movie-schedule" aria-labelledby="movie-schedule-title">
                    <h2 id="movie-schedule-title">HORARIOS</h2>

                    <div class="schedule-box">
                        <?php if ($showtimeDays === []): ?>
                            <div class="schedule-empty">
                                <h3>Sin horarios activos</h3>
                                <p>Esta pelicula no tiene funciones disponibles por ahora.</p>
                            </div>
                        <?php else: ?>
                            <div class="movie-date-tabs" role="tablist" aria-label="Dias disponibles">
                                <?php foreach ($showtimeDays as $index => $day): ?>
                                    <?php
                                    $isActive = $index === 0;
                                    $tabId = 'movie-date-tab-' . $day['date_key'];
                                    $panelId = 'movie-date-panel-' . $day['date_key'];
                                    ?>
                                    <button
                                        id="<?= e($tabId) ?>"
                                        class="movie-date-tab <?= $isActive ? 'is-active' : '' ?>"
                                        type="button"
                                        role="tab"
                                        aria-selected="<?= $isActive ? 'true' : 'false' ?>"
                                        aria-controls="<?= e($panelId) ?>"
                                        data-movie-date-tab="<?= e($day['date_key']) ?>"
                                    >
                                        <span><?= e(mb_strtoupper((string) $day['day_label'])) ?></span>
                                        <em><?= e(mb_strtoupper((string) $day['date_label'])) ?></em>
                                    </button>
                                <?php endforeach; ?>
                            </div>

                            <?php foreach ($showtimeDays as $index => $day): ?>
                                <?php
                                $isActive = $index === 0;
                                $tabId = 'movie-date-tab-' . $day['date_key'];
                                $panelId = 'movie-date-panel-' . $day['date_key'];
                                ?>
                                <div
                                    id="<?= e($panelId) ?>"
                                    class="movie-date-panel <?= $isActive ? 'is-active' : '' ?>"
                                    role="tabpanel"
                                    aria-labelledby="<?= e($tabId) ?>"
                                    data-movie-date-panel="<?= e($day['date_key']) ?>"
                                    <?= $isActive ? '' : 'hidden' ?>
                                >
                                    <div class="showtime-grid">
                                        <?php foreach ($day['showtimes'] as $showtime): ?>
                                            <article class="showtime-card">
                                                <button class="showtime-chip" type="button" data-showtime-choice="<?= e($showtime['id']) ?>">
                                                    <?= e($showtime['time_label']) ?>
                                                </button>
                                                <p><?= e(trim($showtime['format_label'] . ' - ' . $showtime['language_label'], ' -')) ?></p>
                                                <?php if (trim((string) $showtime['room_name']) !== ''): ?>
                                                    <span><?= e($showtime['room_name']) ?></span>
                                                <?php endif; ?>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="ticket-area" aria-label="Seleccion visual de entradas">
                                <h3>SELECCIONA TUS ENTRADAS</h3>

                                <div class="ticket-actions">
                                    <div class="ticket-card is-selected" data-ticket-selector data-min="0" data-max="10">
                                        <div>
                                            <strong>BUTACA TRADICIONAL</strong>
                                            <span>$7.900</span>
                                            <small>*INCLUYE $500 DE CARGO POR SERVICIO</small>
                                        </div>
                                        <div class="ticket-stepper" aria-label="Cantidad butaca tradicional">
                                            <button type="button" data-ticket-action="decrease" aria-label="Restar butaca tradicional">-</button>
                                            <output data-ticket-count>2</output>
                                            <button type="button" data-ticket-action="increase" aria-label="Sumar butaca tradicional">+</button>
                                        </div>
                                    </div>

                                    <div class="ticket-card" data-ticket-selector data-min="0" data-max="10">
                                        <div>
                                            <strong>NINO O TERCERA EDAD</strong>
                                            <span>$6.700</span>
                                            <small>*INCLUYE $500 DE CARGO POR SERVICIO</small>
                                        </div>
                                        <div class="ticket-stepper" aria-label="Cantidad nino o tercera edad">
                                            <button type="button" data-ticket-action="decrease" aria-label="Restar nino o tercera edad">-</button>
                                            <output data-ticket-count>0</output>
                                            <button type="button" data-ticket-action="increase" aria-label="Sumar nino o tercera edad">+</button>
                                        </div>
                                    </div>

                                    <button class="movie-continue" type="button" aria-disabled="true" data-visual-continue>
                                        CONTINUAR
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        <?php endif; ?>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
