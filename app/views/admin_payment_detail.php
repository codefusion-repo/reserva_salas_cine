<?php
declare(strict_types=1);

$paymentId = (int) ($payment['id'] ?? 0);
$referenceCode = (string) ($payment['reference_code'] ?? '');
$checkoutType = (string) ($payment['checkout_type'] ?? '');
$status = (string) ($payment['status'] ?? '');
$statusClass = payment_status_css_class($status);
$statusLabel = payment_status_label($status);
$paidAtLabel = reservation_datetime_label($payment['paid_at'] ?? '');
$createdAtLabel = reservation_datetime_label($payment['created_at'] ?? '');
$reservationCode = payment_reservation_code($payment);
$summaryLabel = payment_summary_label($payment);
$ownerName = trim((string) ($payment['user_name'] ?? ''));
$ownerEmail = trim((string) ($payment['user_email'] ?? ''));
$movieTitle = trim((string) ($payment['movie_title'] ?? ''));
$roomLabel = trim(
    (string) ($payment['room_name'] ?? '') . ' - ' . (string) ($payment['room_location'] ?? ''),
    ' -'
);
$formatLabel = trim(
    (string) ($payment['format_label'] ?? '') . ' - ' . (string) ($payment['language_label'] ?? ''),
    ' -'
);
$showtimeLabel = reservation_datetime_label($payment['starts_at'] ?? '');

if ($ownerName === '') {
    $ownerName = 'Usuario';
}

if ($ownerEmail === '') {
    $ownerEmail = 'No informado';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin pago <?= e($referenceCode !== '' ? $referenceCode : $paymentId) ?> - Reserva Salas Cine</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-screen admin-screen payments-screen payment-detail-screen">
    <?php
    $activeNav = 'admin';
    require __DIR__ . '/partials/header.php';
    ?>

    <main class="payments-shell admin-payment-detail-shell">
        <?php if ($messages !== []): ?>
            <div class="page-messages cartelera-messages" aria-live="polite">
                <?php foreach ($messages as $message): ?>
                    <p class="notice notice-<?= e($message['type'] ?? 'info') ?>"><?= e($message['message'] ?? '') ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="payments-heading">
            <a class="movie-back" href="index.php?page=admin_payments" aria-label="Volver a pagos admin">
                <span aria-hidden="true"></span>
            </a>
            <div>
                <p class="eyebrow">Detalle admin de pago</p>
                <h1><?= e($referenceCode !== '' ? $referenceCode : 'Pago') ?></h1>
                <p><?= e($summaryLabel) ?></p>
            </div>
        </div>

        <div class="payment-detail-layout">
            <section class="payment-detail-panel" aria-labelledby="admin-payment-detail-title">
                <div class="payment-section-heading">
                    <p class="eyebrow">Pago</p>
                    <h2 id="admin-payment-detail-title">Datos del pago</h2>
                </div>

                <dl class="payment-details payment-details-wide">
                    <div>
                        <dt>N° pago</dt>
                        <dd><?= e($paymentId) ?></dd>
                    </div>
                    <div>
                        <dt>Referencia</dt>
                        <dd><?= e($referenceCode !== '' ? $referenceCode : 'Sin referencia') ?></dd>
                    </div>
                    <div>
                        <dt>Tipo</dt>
                        <dd><?= e(payment_checkout_type_label($checkoutType)) ?></dd>
                    </div>
                    <div>
                        <dt>Estado</dt>
                        <dd><span class="reservation-status-badge status-<?= e($statusClass) ?>"><?= e($statusLabel) ?></span></dd>
                    </div>
                    <div>
                        <dt>Metodo</dt>
                        <dd><?= e(payment_method_label((string) ($payment['payment_method'] ?? ''))) ?></dd>
                    </div>
                    <div>
                        <dt>Usuario</dt>
                        <dd><?= e($ownerName) ?></dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd><?= e($ownerEmail) ?></dd>
                    </div>
                    <div>
                        <dt>N° usuario</dt>
                        <dd><?= e($payment['user_id'] ?? '') ?></dd>
                    </div>
                    <div>
                        <dt>Pagado</dt>
                        <dd><?= e($paidAtLabel !== '' ? $paidAtLabel : 'Sin fecha') ?></dd>
                    </div>
                    <div>
                        <dt>Creado</dt>
                        <dd><?= e($createdAtLabel !== '' ? $createdAtLabel : 'Sin fecha') ?></dd>
                    </div>
                    <?php if ((int) ($payment['reservation_id'] ?? 0) > 0): ?>
                        <div>
                            <dt>N° reserva</dt>
                            <dd><?= e((int) ($payment['reservation_id'] ?? 0)) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($reservationCode !== ''): ?>
                        <div>
                            <dt>Codigo reserva</dt>
                            <dd><?= e($reservationCode) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($movieTitle !== ''): ?>
                        <div>
                            <dt>Pelicula</dt>
                            <dd><?= e($movieTitle) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($showtimeLabel !== ''): ?>
                        <div>
                            <dt>Funcion</dt>
                            <dd><?= e($showtimeLabel) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($roomLabel !== ''): ?>
                        <div>
                            <dt>Sala</dt>
                            <dd><?= e($roomLabel) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($formatLabel !== ''): ?>
                        <div>
                            <dt>Formato</dt>
                            <dd><?= e($formatLabel) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </section>

            <aside class="payment-total-panel" aria-labelledby="admin-payment-total-title">
                <div class="payment-section-heading">
                    <p class="eyebrow">Resumen</p>
                    <h2 id="admin-payment-total-title">Monto</h2>
                </div>

                <dl class="payment-total-list">
                    <div>
                        <dt>Subtotal</dt>
                        <dd><?= e(reservation_format_money((float) ($payment['subtotal_amount'] ?? 0))) ?></dd>
                    </div>
                    <div>
                        <dt>Descuento</dt>
                        <dd><?= e(reservation_format_money((float) ($payment['discount_amount'] ?? 0))) ?></dd>
                    </div>
                    <div>
                        <dt>Total</dt>
                        <dd><?= e(reservation_format_money((float) ($payment['total_amount'] ?? 0))) ?></dd>
                    </div>
                </dl>

                <div class="payment-warning">
                    <strong>Vista solo lectura.</strong>
                    <span>No hay edicion, eliminacion, reembolso ni cambio de estado desde esta seccion.</span>
                </div>

                <div class="payment-side-actions">
                    <a class="checkout-confirm-button" href="index.php?page=admin_invoice&amp;payment_id=<?= e($paymentId) ?>">Ver comprobante</a>
                    <a class="checkout-secondary-link" href="index.php?action=admin_invoice_download&amp;payment_id=<?= e($paymentId) ?>">Descargar TXT</a>
                </div>
            </aside>
        </div>

        <section class="payment-items-panel" aria-labelledby="admin-payment-items-title">
            <div class="payment-section-heading">
                <p class="eyebrow">Items</p>
                <h2 id="admin-payment-items-title">Lineas del pago</h2>
            </div>

            <?php if ($paymentItems === []): ?>
                <p class="payment-muted">Este pago no tiene items registrados.</p>
            <?php else: ?>
                <div class="payment-item-list">
                    <?php foreach ($paymentItems as $item): ?>
                        <article class="payment-item-row">
                            <div>
                                <h3><?= e($item['item_label'] ?? 'Item') ?></h3>
                                <p><?= e((string) ($item['item_type'] ?? 'item')) ?></p>
                            </div>
                            <dl>
                                <div>
                                    <dt>Cantidad</dt>
                                    <dd><?= e($item['quantity'] ?? 0) ?></dd>
                                </div>
                                <div>
                                    <dt>Unitario</dt>
                                    <dd><?= e(reservation_format_money((float) ($item['unit_amount'] ?? 0))) ?></dd>
                                </div>
                                <div>
                                    <dt>Total</dt>
                                    <dd><?= e(reservation_format_money((float) ($item['total_amount'] ?? 0))) ?></dd>
                                </div>
                            </dl>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <div class="payment-page-actions">
            <a class="ticket-action ticket-action-secondary" href="index.php?page=admin_payments">Volver a pagos</a>
            <a class="ticket-action ticket-action-secondary" href="index.php?page=admin">Volver al panel admin</a>
        </div>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
