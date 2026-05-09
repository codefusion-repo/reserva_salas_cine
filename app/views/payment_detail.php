<?php
declare(strict_types=1);

$paymentId = (int) ($payment['id'] ?? 0);
$referenceCode = (string) ($payment['reference_code'] ?? '');
$checkoutType = (string) ($payment['checkout_type'] ?? '');
$status = (string) ($payment['status'] ?? '');
$statusClass = payment_status_css_class($status);
$statusLabel = payment_status_label($status);
$paidAtLabel = payment_paid_at_label($payment);
$reservationCode = payment_reservation_code($payment);
$summaryLabel = payment_summary_label($payment);
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
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago <?= e($referenceCode !== '' ? $referenceCode : $paymentId) ?> - Reserva Salas Cine</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-screen payments-screen payment-detail-screen">
    <?php
    $activeNav = 'my_payments';
    require __DIR__ . '/partials/header.php';
    ?>

    <main class="payments-shell">
        <?php if ($messages !== []): ?>
            <div class="page-messages cartelera-messages" aria-live="polite">
                <?php foreach ($messages as $message): ?>
                    <p class="notice notice-<?= e($message['type'] ?? 'info') ?>"><?= e($message['message'] ?? '') ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="payments-heading">
            <a class="movie-back" href="index.php?page=my_payments" aria-label="Volver a Mis pagos">
                <span aria-hidden="true"></span>
            </a>
            <div>
                <p class="eyebrow">Detalle de pago</p>
                <h1><?= e($referenceCode !== '' ? $referenceCode : 'Pago demo') ?></h1>
                <p><?= e($summaryLabel) ?></p>
            </div>
        </div>

        <div class="payment-detail-layout">
            <section class="payment-detail-panel" aria-labelledby="payment-detail-title">
                <div class="payment-section-heading">
                    <p class="eyebrow">Pago simulado</p>
                    <h2 id="payment-detail-title">Datos del pago</h2>
                </div>

                <dl class="payment-details payment-details-wide">
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
                        <dt>Fecha</dt>
                        <dd><?= e($paidAtLabel !== '' ? $paidAtLabel : 'Sin fecha') ?></dd>
                    </div>
                    <div>
                        <dt>Metodo</dt>
                        <dd><?= e(payment_method_label((string) ($payment['payment_method'] ?? ''))) ?></dd>
                    </div>
                    <?php if ($reservationCode !== ''): ?>
                        <div>
                            <dt>Reserva</dt>
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

            <aside class="payment-total-panel" aria-labelledby="payment-total-title">
                <div class="payment-section-heading">
                    <p class="eyebrow">Resumen</p>
                    <h2 id="payment-total-title">Monto demo</h2>
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
                        <dt>Total demo</dt>
                        <dd><?= e(reservation_format_money((float) ($payment['total_amount'] ?? 0))) ?></dd>
                    </div>
                </dl>

                <div class="payment-warning">
                    <strong>Comprobante simulado.</strong>
                    <span>No válido como factura/boleta legal. No hubo cobro real.</span>
                </div>

                <div class="payment-side-actions">
                    <a class="checkout-confirm-button" href="index.php?page=invoice&amp;payment_id=<?= e($paymentId) ?>">Ver comprobante</a>
                    <a class="checkout-secondary-link" href="index.php?action=invoice_download&amp;payment_id=<?= e($paymentId) ?>">Descargar TXT</a>
                </div>
            </aside>
        </div>

        <section class="payment-items-panel" aria-labelledby="payment-items-title">
            <div class="payment-section-heading">
                <p class="eyebrow">Items</p>
                <h2 id="payment-items-title">Detalle cobrado demo</h2>
            </div>

            <?php if ($paymentItems === []): ?>
                <p class="payment-muted">Este pago no tiene items registrados.</p>
            <?php else: ?>
                <div class="payment-item-list">
                    <?php foreach ($paymentItems as $item): ?>
                        <article class="payment-item-row">
                            <div>
                                <h3><?= e($item['item_label'] ?? 'Item') ?></h3>
                                <p><?= e(payment_checkout_type_label((string) ($payment['checkout_type'] ?? ''))) ?></p>
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
            <a class="ticket-action ticket-action-secondary" href="index.php?page=my_payments">Volver a Mis pagos</a>
            <?php if ($reservationCode !== ''): ?>
                <a class="ticket-action ticket-action-secondary" href="index.php?page=ticket&amp;reservation_id=<?= e((int) ($payment['reservation_id'] ?? 0)) ?>">Ver ticket</a>
            <?php endif; ?>
        </div>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
