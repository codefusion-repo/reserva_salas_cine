<?php
declare(strict_types=1);

$adminPaymentFilters = is_array($adminPaymentFilters ?? null) ? $adminPaymentFilters : [];
$adminPaymentSummary = is_array($adminPaymentSummary ?? null) ? $adminPaymentSummary : [];
$hasAdminPaymentFilters = (string) ($adminPaymentFilters['checkout_type'] ?? '') !== ''
    || (string) ($adminPaymentFilters['status'] ?? '') !== ''
    || (string) ($adminPaymentFilters['q'] ?? '') !== ''
    || (string) ($adminPaymentFilters['date_from'] ?? '') !== ''
    || (string) ($adminPaymentFilters['date_to'] ?? '') !== '';
$latestPaymentDate = reservation_datetime_label($adminPaymentSummary['latest_date'] ?? '');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin pagos - Reserva Salas Cine</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-screen admin-screen">
    <?php
    $activeNav = 'admin';
    require __DIR__ . '/partials/header.php';
    ?>

    <main class="admin-shell">
        <?php if ($messages !== []): ?>
            <div class="page-messages cartelera-messages" aria-live="polite">
                <?php foreach ($messages as $message): ?>
                    <p class="notice notice-<?= e($message['type'] ?? 'info') ?>"><?= e($message['message'] ?? '') ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="admin-heading">
            <p class="eyebrow">Rol administrador</p>
            <h1>Pagos simulados</h1>
            <p>Supervision de pagos academicos sin cobro real</p>
        </section>

        <nav class="admin-subnav" aria-label="Secciones de administracion">
            <a class="admin-subnav-link" href="index.php?page=admin">Resumen</a>
            <a class="admin-subnav-link" href="index.php?page=admin&amp;admin_section=rooms#admin-rooms">Salas</a>
            <a class="admin-subnav-link" href="index.php?page=admin&amp;admin_section=movies#admin-movies">Peliculas</a>
            <a class="admin-subnav-link" href="index.php?page=admin&amp;admin_section=showtimes#admin-showtimes">Funciones</a>
            <a class="admin-subnav-link" href="index.php?page=admin&amp;admin_section=concessions#admin-concessions">Confiteria</a>
            <a class="admin-subnav-link is-active" href="index.php?page=admin_payments" aria-current="page">Pagos</a>
            <a class="admin-subnav-link" href="index.php?page=admin&amp;admin_section=reservations#admin-reservations">Reservas</a>
        </nav>

        <?php if ($adminPaymentLoadError): ?>
            <section class="cartelera-state movie-detail-state">
                <h2>No se pudo cargar la lista de pagos</h2>
                <p>Intenta nuevamente mas tarde.</p>
                <div class="state-actions">
                    <a class="movie-state-link" href="index.php?page=admin_payments">Intentar nuevamente</a>
                    <a class="movie-state-link movie-state-link-secondary" href="index.php?page=admin">Volver al panel</a>
                </div>
            </section>
        <?php else: ?>
            <section class="admin-summary admin-payment-summary" aria-label="Resumen de pagos">
                <article>
                    <span><?= e($adminPaymentSummary['count'] ?? 0) ?></span>
                    <p>Pagos listados</p>
                    <strong><?= e($hasAdminPaymentFilters ? 'Con filtros activos' : 'Sin filtros') ?></strong>
                </article>
                <article>
                    <span class="admin-summary-value-text"><?= e(reservation_format_money((float) ($adminPaymentSummary['total_amount'] ?? 0))) ?></span>
                    <p>Total demo</p>
                    <strong>Suma de pagos visibles</strong>
                </article>
                <article>
                    <span class="admin-summary-value-text"><?= e($latestPaymentDate !== '' ? $latestPaymentDate : 'Sin fecha') ?></span>
                    <p>Ultimo pago</p>
                    <strong>Pagado o creado</strong>
                </article>
                <article>
                    <span class="admin-summary-value-text">Solo lectura</span>
                    <p>Gestion permitida</p>
                    <strong>Sin editar, eliminar ni reembolsar</strong>
                </article>
            </section>

            <section id="admin-payments" class="admin-section" aria-labelledby="admin-payments-title">
                <div class="admin-section-heading">
                    <div>
                        <p class="eyebrow">Pagos</p>
                        <h2 id="admin-payments-title">Listado de pagos</h2>
                    </div>
                </div>

                <form class="admin-form admin-payment-filter" method="get" action="index.php#admin-payments" data-filter-form>
                    <input type="hidden" name="page" value="admin_payments">
                    <label>
                        <span>Tipo</span>
                        <select name="checkout_type">
                            <option value="" <?= (string) ($adminPaymentFilters['checkout_type'] ?? '') === '' ? 'selected' : '' ?>>Todos</option>
                            <?php foreach (PAYMENT_ALLOWED_CHECKOUT_TYPES as $checkoutTypeOption): ?>
                                <option
                                    value="<?= e($checkoutTypeOption) ?>"
                                    <?= (string) ($adminPaymentFilters['checkout_type'] ?? '') === $checkoutTypeOption ? 'selected' : '' ?>
                                >
                                    <?= e(payment_checkout_type_label($checkoutTypeOption)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Estado</span>
                        <select name="status">
                            <option value="" <?= (string) ($adminPaymentFilters['status'] ?? '') === '' ? 'selected' : '' ?>>Todos</option>
                            <option
                                value="<?= e(PAYMENT_STATUS_SIMULATED_PAID) ?>"
                                <?= (string) ($adminPaymentFilters['status'] ?? '') === PAYMENT_STATUS_SIMULATED_PAID ? 'selected' : '' ?>
                            >
                                <?= e(payment_status_label(PAYMENT_STATUS_SIMULATED_PAID)) ?>
                            </option>
                        </select>
                    </label>
                    <label>
                        <span>Fecha desde</span>
                        <input type="date" name="date_from" value="<?= e((string) ($adminPaymentFilters['date_from'] ?? '')) ?>">
                    </label>
                    <label>
                        <span>Fecha hasta</span>
                        <input type="date" name="date_to" value="<?= e((string) ($adminPaymentFilters['date_to'] ?? '')) ?>">
                    </label>
                    <label>
                        <span>Busqueda</span>
                        <input
                            type="search"
                            name="q"
                            value="<?= e((string) ($adminPaymentFilters['q'] ?? '')) ?>"
                            maxlength="100"
                            placeholder="Usuario, email, referencia o ID"
                        >
                    </label>
                    <button type="submit">Filtrar</button>
                    <div class="admin-filter-actions">
                        <a class="admin-filter-reset" href="index.php?page=admin_payments#admin-payments">Limpiar</a>
                    </div>
                </form>

                <?php if ($adminPayments === []): ?>
                    <div class="admin-empty">
                        <h3><?= $hasAdminPaymentFilters ? 'No hay pagos que coincidan con los filtros.' : 'Sin pagos simulados' ?></h3>
                    </div>
                <?php else: ?>
                    <div class="admin-list admin-payment-list" role="list">
                        <div class="admin-list-head admin-payment-row" aria-hidden="true">
                            <span>ID</span>
                            <span>Referencia</span>
                            <span>Usuario</span>
                            <span>Email</span>
                            <span>Tipo</span>
                            <span>Estado</span>
                            <span>Total</span>
                            <span>Metodo</span>
                            <span>Pagado</span>
                            <span>Creado</span>
                            <span>Acciones</span>
                        </div>

                        <?php foreach ($adminPayments as $payment): ?>
                            <?php
                            $paymentId = (int) ($payment['id'] ?? 0);
                            $status = (string) ($payment['status'] ?? '');
                            $statusClass = payment_status_css_class($status);
                            $statusLabel = payment_status_label($status);
                            $referenceCode = (string) ($payment['reference_code'] ?? '');
                            ?>
                            <div class="admin-row admin-payment-row" role="listitem">
                                <span><?= e($paymentId) ?></span>
                                <span><?= e($referenceCode !== '' ? $referenceCode : 'Sin referencia') ?></span>
                                <span><?= e($payment['user_name'] ?? 'Sin usuario') ?></span>
                                <span><?= e($payment['user_email'] ?? 'Sin email') ?></span>
                                <span><?= e(payment_checkout_type_label((string) ($payment['checkout_type'] ?? ''))) ?></span>
                                <span class="admin-status-badge status-<?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
                                <span><?= e(reservation_format_money((float) ($payment['total_amount'] ?? 0))) ?></span>
                                <span><?= e(payment_method_label((string) ($payment['payment_method'] ?? ''))) ?></span>
                                <span><?= e(reservation_datetime_label($payment['paid_at'] ?? '')) ?></span>
                                <span><?= e(reservation_datetime_label($payment['created_at'] ?? '')) ?></span>
                                <div class="admin-actions admin-payment-actions">
                                    <a class="admin-action-link" href="index.php?page=admin_payment_detail&amp;payment_id=<?= e($paymentId) ?>">Detalle</a>
                                    <a class="admin-action-link admin-secondary" href="index.php?page=admin_invoice&amp;payment_id=<?= e($paymentId) ?>">Comprobante</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
