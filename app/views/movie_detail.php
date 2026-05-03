<?php
declare(strict_types=1);

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
    <?php
    $activeNav = 'cartelera';
    require __DIR__ . '/partials/header.php';
    ?>

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
                                <a class="movie-state-link" href="index.php?page=cartelera">Volver a cartelera</a>
                            </div>
                        <?php else: ?>
                            <form class="movie-reservation-form" method="get" action="index.php" data-showtime-form>
                                <input type="hidden" name="page" value="seats">
                                <input type="hidden" name="showtime_id" value="" data-selected-showtime>
                                <input type="hidden" name="tickets" value="2" data-ticket-total>

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

                                        <button class="movie-continue" type="submit" disabled aria-disabled="true" data-visual-continue>
                                            CONTINUAR
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        <?php endif; ?>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
