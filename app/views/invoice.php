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
$accountUser = is_array($invoiceUser ?? null) ? $invoiceUser : (is_array($user ?? null) ? $user : []);
$invoiceActiveNav = isset($invoiceActiveNav) ? (string) $invoiceActiveNav : 'my_payments';
$invoiceBackUrl = isset($invoiceBackUrl) ? (string) $invoiceBackUrl : 'index.php?page=payment_detail&payment_id=' . $paymentId;
$invoiceDownloadUrl = isset($invoiceDownloadUrl) ? (string) $invoiceDownloadUrl : 'index.php?action=invoice_download&payment_id=' . $paymentId;
$invoiceBackLabel = isset($invoiceBackLabel) ? (string) $invoiceBackLabel : 'Volver al detalle de pago';
$invoiceHeadingEyebrow = isset($invoiceHeadingEyebrow) ? (string) $invoiceHeadingEyebrow : 'Comprobante';
$accountLabel = trim((string) ($accountUser['name'] ?? ''));
$accountEmail = trim((string) ($accountUser['email'] ?? ''));
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

if ($accountLabel === '') {
    $accountLabel = 'Usuario';
}

if ($accountEmail === '') {
    $accountEmail = 'No informado';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comprobante <?= e($referenceCode !== '' ? $referenceCode : $paymentId) ?> - Reserva Salas Cine</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-screen invoice-screen">
    <?php
    $activeNav = $invoiceActiveNav;
    require __DIR__ . '/partials/header.php';
    ?>

    <main class="invoice-shell">
        <?php if ($messages !== []): ?>
            <div class="page-messages cartelera-messages no-print" aria-live="polite">
                <?php foreach ($messages as $message): ?>
                    <p class="notice notice-<?= e($message['type'] ?? 'info') ?>"><?= e($message['message'] ?? '') ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="invoice-heading no-print">
            <a class="movie-back" href="<?= e($invoiceBackUrl) ?>" aria-label="<?= e($invoiceBackLabel) ?>">
                <span aria-hidden="true"></span>
            </a>
            <div>
                <p class="eyebrow"><?= e($invoiceHeadingEyebrow) ?></p>
                <h1><?= e($referenceCode !== '' ? $referenceCode : 'Pago demo') ?></h1>
                <p>Vista imprimible y descarga simple en TXT.</p>
            </div>
        </div>

        <section class="invoice-panel" aria-labelledby="invoice-title">
            <div class="invoice-brand-row">
                <div class="ticket-brand">
                    <span class="ticket-brand-mark" aria-hidden="true"></span>
                    <div>
                        <span>ES Cine</span>
                        <strong>Comprobante simulado</strong>
                    </div>
                </div>
                <div class="ticket-code">
                    <span>Referencia</span>
                    <strong><?= e($referenceCode !== '' ? $referenceCode : 'Pago demo') ?></strong>
                </div>
            </div>

            <div class="invoice-warning">
                <strong>Comprobante simulado.</strong>
                <span>No válido como factura/boleta legal.</span>
                <span>No hubo cobro real.</span>
            </div>

            <div class="invoice-main">
                <div>
                    <p class="eyebrow"><?= e(payment_checkout_type_label($checkoutType)) ?></p>
                    <h2 id="invoice-title"><?= e($summaryLabel) ?></h2>
                </div>
                <span class="reservation-status-badge status-<?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
            </div>

            <dl class="invoice-details">
                <div>
                    <dt>Referencia</dt>
                    <dd><?= e($referenceCode !== '' ? $referenceCode : 'Sin referencia') ?></dd>
                </div>
                <div>
                    <dt>Fecha</dt>
                    <dd><?= e($paidAtLabel !== '' ? $paidAtLabel : 'Sin fecha') ?></dd>
                </div>
                <div>
                    <dt>Usuario</dt>
                    <dd><?= e($accountLabel) ?></dd>
                </div>
                <div>
                    <dt>Email</dt>
                    <dd><?= e($accountEmail) ?></dd>
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

            <section class="invoice-items" aria-labelledby="invoice-items-title">
                <h3 id="invoice-items-title">Items</h3>
                <?php if ($paymentItems === []): ?>
                    <p>Este pago no tiene items registrados.</p>
                <?php else: ?>
                    <div class="invoice-item-list">
                        <?php foreach ($paymentItems as $item): ?>
                            <article class="invoice-item-row">
                                <div>
                                    <span><?= e($item['quantity'] ?? 0) ?> x</span>
                                    <strong><?= e($item['item_label'] ?? 'Item') ?></strong>
                                </div>
                                <span><?= e(reservation_format_money((float) ($item['unit_amount'] ?? 0))) ?> unidad</span>
                                <strong><?= e(reservation_format_money((float) ($item['total_amount'] ?? 0))) ?></strong>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <dl class="invoice-totals">
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
        </section>

        <div class="ticket-actions invoice-actions no-print" aria-label="Acciones del comprobante">
            <button class="ticket-action ticket-action-primary" type="button" data-print-ticket>Imprimir comprobante</button>
            <a class="ticket-action ticket-action-secondary" href="<?= e($invoiceDownloadUrl) ?>">Descargar TXT</a>
            <a class="ticket-action ticket-action-secondary" href="<?= e($invoiceBackUrl) ?>">Volver al detalle</a>
        </div>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
