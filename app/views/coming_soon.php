<?php
declare(strict_types=1);

$pageTitle = (string) ($comingSoon['title'] ?? 'Proximamente');
$activeNav = (string) ($comingSoon['activeNav'] ?? '');
$items = is_array($comingSoon['items'] ?? null) ? $comingSoon['items'] : [];
$notes = is_array($comingSoon['notes'] ?? null) ? $comingSoon['notes'] : [];
$heroActions = is_array($comingSoon['heroActions'] ?? null) ? $comingSoon['heroActions'] : [];
$leadText = trim((string) ($comingSoon['lead'] ?? ''));
$supportText = trim((string) ($comingSoon['support'] ?? ''));
$panelKicker = trim((string) ($comingSoon['panelKicker'] ?? 'Próximamente...'));
$panelHeadline = trim((string) ($comingSoon['panelHeadline'] ?? 'Solo en cines...'));
$featureIcon = trim((string) ($comingSoon['featureIcon'] ?? '🚧'));
$useBenefitsLayout = ($comingSoon['benefitsLayout'] ?? false) === true;
$benefitsSectionId = trim((string) ($comingSoon['benefitsSectionId'] ?? ''));
$sectionClass = $useBenefitsLayout ? 'coming-soon-grid coming-soon-benefits-grid' : 'coming-soon-grid';
$catalogItems = is_array($comingSoon['catalog'] ?? null) ? $comingSoon['catalog'] : [];
$showCatalog = ($comingSoon['showCatalog'] ?? false) === true || $catalogItems !== [];
$catalogLoadError = ($comingSoon['catalogLoadError'] ?? false) === true;
$catalogSetupRequired = ($comingSoon['catalogSetupRequired'] ?? false) === true;
$cartSummary = is_array($cartSummary ?? null) ? $cartSummary : ['items' => [], 'total_label' => '$0 demo', 'is_empty' => true];
$cartItems = is_array($cartSummary['items'] ?? null) ? $cartSummary['items'] : [];
$memberDemo = is_array($comingSoon['memberDemo'] ?? null) ? $comingSoon['memberDemo'] : [];
$memberDemoIsActive = (bool) ($memberDemo['isActive'] ?? false);
$memberDemoStateActive = trim((string) ($memberDemo['stateActiveLabel'] ?? 'Socio Cine Demo activo'));
$memberDemoStateInactive = trim((string) ($memberDemo['stateInactiveLabel'] ?? 'Sin membresía demo'));
$memberDemoStateCopy = trim((string) (
    $memberDemoIsActive
        ? ($memberDemo['stateActiveCopy'] ?? 'Tu sesión tiene membresía demo activa.')
        : ($memberDemo['stateInactiveCopy'] ?? 'Activa la membresía demo para ver el estado de socio en esta sesión.')
));
$memberDemoStateNotes = is_array($memberDemo['stateNotes'] ?? null) ? array_values(array_filter($memberDemo['stateNotes'], static fn (mixed $note): bool => trim((string) $note) !== '')) : [];
$memberDemoActivateAction = trim((string) ($memberDemo['activateAction'] ?? 'index.php?action=member_demo_activate'));
$memberDemoDeactivateAction = trim((string) ($memberDemo['deactivateAction'] ?? 'index.php?action=member_demo_deactivate'));
$memberDemoActivateLabel = trim((string) ($memberDemo['activateLabel'] ?? 'Activar membresía demo'));
$memberDemoDeactivateLabel = trim((string) ($memberDemo['deactivateLabel'] ?? 'Desactivar membresía demo'));
$isMemberDemoPage = $memberDemo !== [];
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
                <?php if ($panelKicker !== ''): ?>
                    <p class="coming-soon-kicker" aria-hidden="true"><?= e($panelKicker) ?></p>
                <?php endif; ?>
                <h2 id="coming-soon-title"><?= e($panelHeadline !== '' ? $panelHeadline : ($comingSoon['headline'] ?? $pageTitle)) ?></h2>
                <?php if ($leadText !== ''): ?>
                    <p><?= e($leadText) ?></p>
                <?php endif; ?>
                <?php if ($supportText !== ''): ?>
                    <p><?= e($supportText) ?></p>
                <?php endif; ?>

                <?php if ($notes !== []): ?>
                    <ul class="coming-soon-notes">
                        <?php foreach ($notes as $note): ?>
                            <li><?= e($note) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="coming-soon-actions">
                    <?php if ($isMemberDemoPage): ?>
                        <div class="member-demo-state<?= $memberDemoIsActive ? ' is-active' : '' ?>">
                            <p class="member-demo-state-title"><?= e($memberDemoIsActive ? $memberDemoStateActive : $memberDemoStateInactive) ?></p>
                            <p class="member-demo-state-copy"><?= e($memberDemoStateCopy) ?></p>
                            <?php foreach ($memberDemoStateNotes as $memberDemoStateNote): ?>
                                <p class="member-demo-state-copy"><?= e((string) $memberDemoStateNote) ?></p>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($memberDemoIsActive): ?>
                            <form action="<?= e($memberDemoDeactivateAction) ?>" method="post" class="member-demo-form">
                                <?= csrf_token_field() ?>
                                <button class="member-demo-action member-demo-action-secondary" type="submit">
                                    <?= e($memberDemoDeactivateLabel) ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <form action="<?= e($memberDemoActivateAction) ?>" method="post" class="member-demo-form">
                                <?= csrf_token_field() ?>
                                <button class="member-demo-action member-demo-action-primary" type="submit">
                                    <?= e($memberDemoActivateLabel) ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($heroActions !== []): ?>
                        <?php foreach ($heroActions as $action): ?>
                            <?php
                            $isLink = (string) ($action['type'] ?? 'link') === 'link';
                            $label = trim((string) ($action['label'] ?? ''));
                            $buttonClass = trim((string) ($action['class'] ?? ''));
                            $isSecondary = str_contains($buttonClass, 'movie-state-link-secondary');
                            $isDisabled = ($action['disabled'] ?? true) === true;
                            ?>
                            <?php if ($isLink): ?>
                                <?php $href = trim((string) ($action['href'] ?? '#')); ?>
                                <a class="movie-state-link<?= $isSecondary ? ' movie-state-link-secondary' : '' ?>" href="<?= e($href === '' ? '#' : $href) ?>">
                                    <?= e($label !== '' ? $label : 'Acción') ?>
                                </a>
                            <?php else: ?>
                                <button class="movie-state-link coming-soon-cta-button<?= $isSecondary ? ' movie-state-link-secondary' : '' ?>" type="button"<?= $isDisabled ? ' disabled' : '' ?> aria-disabled="<?= $isDisabled ? 'true' : 'false' ?>">
                                    <?= e($label !== '' ? $label : 'Acción') ?>
                                </button>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <a class="movie-state-link" href="index.php?page=cartelera">Volver a cartelera</a>
                        <a class="movie-state-link movie-state-link-secondary" href="index.php?page=my_reservations">Mis reservas</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="coming-soon-feature">
                <div class="coming-soon-badge" aria-hidden="true"><?= e($featureIcon !== '' ? $featureIcon : '🚧') ?></div>
                <span><?= e($comingSoon['accent'] ?? 'Proximamente') ?></span>
                <p><?= e($comingSoon['accentCopy'] ?? '') ?></p>
            </div>
        </section>

        <?php if ($showCatalog): ?>
            <section class="confiteria-catalog" aria-labelledby="confiteria-catalog-title">
                <div class="confiteria-catalog-heading">
                    <div>
                        <p class="eyebrow">Catálogo demo</p>
                        <h2 id="confiteria-catalog-title">Combos de confitería</h2>
                    </div>
                    <p>Compra real no disponible. No hay pago real y el checkout se implementará después.</p>
                </div>

                <div class="confiteria-shop-layout">
                    <div class="confiteria-shop-products">
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
                                            <form class="confiteria-card-form" action="index.php?action=concession_add" method="post">
                                                <?= csrf_token_field() ?>
                                                <input type="hidden" name="product_id" value="<?= e((int) ($catalogItem['id'] ?? 0)) ?>">
                                                <input type="hidden" name="quantity" value="1">
                                                <button class="confiteria-card-button" type="submit">
                                                    <?= e($catalogItem['button'] ?? 'Agregar') ?>
                                                </button>
                                            </form>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <aside class="confiteria-cart-panel" aria-labelledby="confiteria-cart-title">
                        <div class="confiteria-cart-heading">
                            <p class="eyebrow">Carrito en sesión</p>
                            <h3 id="confiteria-cart-title">Resumen demo</h3>
                        </div>

                        <?php if ($cartItems === []): ?>
                            <p class="confiteria-cart-empty">Agrega productos activos para ver cantidades, subtotales y total demo.</p>
                        <?php else: ?>
                            <div class="confiteria-cart-items">
                                <?php foreach ($cartItems as $cartItem): ?>
                                    <?php
                                    $cartProductId = (int) ($cartItem['product_id'] ?? 0);
                                    $cartQuantity = (int) ($cartItem['quantity'] ?? 1);
                                    ?>
                                    <article class="confiteria-cart-item">
                                        <div class="confiteria-cart-item-main">
                                            <h4><?= e($cartItem['name'] ?? '') ?></h4>
                                            <p><?= e($cartItem['unit_price_label'] ?? '') ?> unidad</p>
                                            <strong><?= e($cartItem['subtotal_label'] ?? '') ?></strong>
                                        </div>

                                        <div class="confiteria-cart-controls" aria-label="Controles de cantidad">
                                            <form action="index.php?action=concession_update" method="post">
                                                <?= csrf_token_field() ?>
                                                <input type="hidden" name="product_id" value="<?= e($cartProductId) ?>">
                                                <input type="hidden" name="quantity" value="<?= e(max(1, $cartQuantity - 1)) ?>">
                                                <button class="confiteria-cart-qty-button" type="submit"<?= $cartQuantity <= 1 ? ' disabled' : '' ?>>-</button>
                                            </form>

                                            <form class="confiteria-cart-quantity-form" action="index.php?action=concession_update" method="post">
                                                <?= csrf_token_field() ?>
                                                <input type="hidden" name="product_id" value="<?= e($cartProductId) ?>">
                                                <label>
                                                    <span>Cantidad</span>
                                                    <input type="number" name="quantity" min="1" max="10" value="<?= e($cartQuantity) ?>">
                                                </label>
                                                <button type="submit">Actualizar</button>
                                            </form>

                                            <form action="index.php?action=concession_update" method="post">
                                                <?= csrf_token_field() ?>
                                                <input type="hidden" name="product_id" value="<?= e($cartProductId) ?>">
                                                <input type="hidden" name="quantity" value="<?= e(min(10, $cartQuantity + 1)) ?>">
                                                <button class="confiteria-cart-qty-button" type="submit"<?= $cartQuantity >= 10 ? ' disabled' : '' ?>>+</button>
                                            </form>
                                        </div>

                                        <form action="index.php?action=concession_remove" method="post">
                                            <?= csrf_token_field() ?>
                                            <input type="hidden" name="product_id" value="<?= e($cartProductId) ?>">
                                            <button class="confiteria-cart-remove" type="submit">Quitar</button>
                                        </form>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="confiteria-cart-total">
                            <span>Total demo</span>
                            <strong><?= e($cartSummary['total_label'] ?? '$0 demo') ?></strong>
                        </div>

                        <div class="confiteria-cart-actions">
                            <button class="confiteria-checkout-disabled" type="button" disabled>Checkout próximamente</button>
                            <?php if ($cartItems !== []): ?>
                                <form action="index.php?action=concession_clear" method="post">
                                    <?= csrf_token_field() ?>
                                    <button class="confiteria-cart-clear" type="submit">Vaciar carrito</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <p class="confiteria-cart-note">Compra real no disponible. No hay pago real ni pedidos.</p>
                    </aside>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($items !== []): ?>
            <section<?= $benefitsSectionId !== '' ? ' id="' . e($benefitsSectionId) . '"' : '' ?> class="<?= e($sectionClass) ?>" aria-label="Beneficios">
                <?php foreach ($items as $item): ?>
                    <?php $status = trim((string) ($item['status'] ?? '')); ?>
                    <article class="coming-soon-card">
                        <?php if ($status !== ''): ?>
                            <span class="coming-soon-card-status"><?= e($status) ?></span>
                        <?php endif; ?>
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
