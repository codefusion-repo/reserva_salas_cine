<?php
declare(strict_types=1);

$movieFilters = $movieFilters ?? [
    'q' => '',
    'genre' => '',
    'classification' => '',
];
$movieFilterOptions = $movieFilterOptions ?? [
    'genres' => [],
    'classifications' => [],
];
$hasActiveMovieFilters = $hasActiveMovieFilters ?? false;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cartelera - Reserva Salas Cine</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-screen cartelera-screen">
    <?php
    $activeNav = 'cartelera';
    require __DIR__ . '/partials/header.php';
    ?>

    <main class="cartelera-shell">
        <?php if ($messages !== []): ?>
            <div class="page-messages cartelera-messages" aria-live="polite">
                <?php foreach ($messages as $message): ?>
                    <p class="notice notice-<?= e($message['type'] ?? 'info') ?>"><?= e($message['message'] ?? '') ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h1 class="cartelera-title">Cartelera</h1>

        <div class="cartelera-layout">
            <aside class="filter-panel" aria-label="Filtros de cartelera">
                <form class="filter-form" method="get" action="index.php" data-filter-form>
                    <input type="hidden" name="page" value="cartelera">

                    <div class="filter-heading">
                        <span class="filter-icon" aria-hidden="true">
                            <span></span>
                            <span></span>
                            <span></span>
                        </span>
                        <span>Filtrar por:</span>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label" for="movie-filter-q">Titulo</label>
                        <input
                            class="filter-search"
                            id="movie-filter-q"
                            type="search"
                            name="q"
                            value="<?= e($movieFilters['q'] ?? '') ?>"
                            placeholder="Buscar pelicula"
                        >
                    </div>

                    <div class="filter-group">
                        <div class="filter-toggle">
                            <span>Genero</span>
                            <span aria-hidden="true">-</span>
                        </div>

                        <?php if (($movieFilterOptions['genres'] ?? []) !== []): ?>
                            <ul class="filter-option-list">
                                <li>
                                    <label>
                                        <input type="radio" name="genre" value=""<?= ($movieFilters['genre'] ?? '') === '' ? ' checked' : '' ?>>
                                        <span>Todos</span>
                                    </label>
                                </li>
                                <?php foreach ($movieFilterOptions['genres'] as $genre): ?>
                                    <li>
                                        <label>
                                            <input type="radio" name="genre" value="<?= e($genre) ?>"<?= ($movieFilters['genre'] ?? '') === $genre ? ' checked' : '' ?>>
                                            <span><?= e(mb_strtoupper($genre)) ?></span>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="filter-empty">Sin generos</p>
                        <?php endif; ?>
                    </div>

                    <div class="filter-group filter-group-last">
                        <div class="filter-toggle">
                            <span>Clasificacion</span>
                            <span aria-hidden="true">-</span>
                        </div>

                        <?php if (($movieFilterOptions['classifications'] ?? []) !== []): ?>
                            <ul class="filter-option-list">
                                <li>
                                    <label>
                                        <input type="radio" name="classification" value=""<?= ($movieFilters['classification'] ?? '') === '' ? ' checked' : '' ?>>
                                        <span>Todas</span>
                                    </label>
                                </li>
                                <?php foreach ($movieFilterOptions['classifications'] as $classification): ?>
                                    <li>
                                        <label>
                                            <input type="radio" name="classification" value="<?= e($classification) ?>"<?= ($movieFilters['classification'] ?? '') === $classification ? ' checked' : '' ?>>
                                            <span><?= e($classification) ?></span>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="filter-empty">Sin clasificaciones</p>
                        <?php endif; ?>
                    </div>

                    <div class="filter-actions">
                        <a class="filter-clear" href="index.php?page=cartelera">Limpiar filtros</a>
                    </div>
                </form>
            </aside>

            <section class="movie-board" aria-label="Peliculas activas">
                <?php if ($movieLoadError): ?>
                    <div class="cartelera-state">
                        <h2>No se pudo cargar la cartelera</h2>
                        <p>Intenta nuevamente mas tarde.</p>
                        <a class="movie-state-link" href="index.php?page=cartelera">Intentar nuevamente</a>
                    </div>
                <?php elseif ($movies === [] && $hasActiveMovieFilters): ?>
                    <div class="cartelera-state">
                        <h2>No hay resultados</h2>
                        <p>No encontramos peliculas activas que coincidan con los filtros seleccionados.</p>
                        <a class="movie-state-link" href="index.php?page=cartelera">Limpiar filtros</a>
                    </div>
                <?php elseif ($movies === []): ?>
                    <div class="cartelera-state">
                        <h2>No hay peliculas activas</h2>
                        <p>La cartelera se mostrara cuando existan peliculas disponibles.</p>
                    </div>
                <?php else: ?>
                    <div class="movie-grid">
                        <?php foreach ($movies as $movie): ?>
                            <?php
                            $title = (string) ($movie['title'] ?? 'Pelicula');
                            $posterUrl = public_asset_url_if_exists($movie['poster_path'] ?? null);
                            $meta = sprintf(
                                '%s - %s - %s',
                                (string) ($movie['genre'] ?? ''),
                                (string) ($movie['release_year'] ?? ''),
                                (string) ($movie['classification'] ?? '')
                            );
                            ?>
                            <a class="movie-card" href="index.php?page=movie&amp;id=<?= e($movie['id'] ?? '') ?>">
                                <div class="movie-poster-frame">
                                    <?php if ($posterUrl !== null): ?>
                                        <img class="movie-poster" src="<?= e($posterUrl) ?>" alt="Poster de <?= e($title) ?>">
                                    <?php else: ?>
                                        <div class="movie-poster-placeholder" role="img" aria-label="Poster no disponible para <?= e($title) ?>">
                                            <span>ES Cine</span>
                                            <strong><?= e($title) ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="movie-info">
                                    <h2><?= e($title) ?></h2>
                                    <p><?= e($meta) ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
