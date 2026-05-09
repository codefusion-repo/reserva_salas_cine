<?php
declare(strict_types=1);

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis pagos - Reserva Salas Cine</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-screen payments-screen">
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
            <a class="movie-back" href="index.php?page=cartelera" aria-label="Volver a cartelera">
                <span aria-hidden="true"></span>
            </a>
            <div>
                <h1>Mis pagos</h1>
                <p><?= e($user['name'] ?? 'Usuario') ?></p>
            </div>
        </div>

        <?php if ($paymentLoadError): ?>
            <section class="cartelera-state movie-detail-state">
                <h2>No se pudieron cargar tus pagos</h2>
                <p>Intenta nuevamente mas tarde.</p>
                <div class="state-actions">
                    <a class="movie-state-link" href="index.php?page=my_payments">Intentar nuevamente</a>
                    <a class="movie-state-link movie-state-link-secondary" href="index.php?page=cartelera">Volver a cartelera</a>
                </div>
            </section>
        <?php elseif ($payments === []): ?>
            <section class="payments-empty">
                <h2>No tienes pagos registrados</h2>
                <a class="movie-state-link" href="index.php?page=cartelera">Volver a cartelera</a>
            </section>
        <?php else: ?>
            <section class="payment-list" aria-label="Pagos del usuario">
                <?php foreach ($payments as $payment): ?>
                    <?php
                    $paymentId = (int) ($payment['id'] ?? 0);
                    $status = (string) ($payment['status'] ?? '');
                    $statusClass = payment_status_css_class($status);
                    $statusLabel = payment_status_label($status);
                    $referenceCode = (string) ($payment['reference_code'] ?? '');
                    $reservationCode = payment_reservation_code($payment);
                    $paidAtLabel = payment_paid_at_label($payment);
                    $summaryLabel = payment_summary_label($payment);
                    ?>
                    <article class="payment-card">
                        <div class="payment-card-main">
                            <header class="payment-card-header">
                                <div>
                                    <span class="payment-reference"><?= e($referenceCode !== '' ? $referenceCode : 'Pago demo') ?></span>
                                    <h2><?= e($summaryLabel) ?></h2>
                                </div>
                                <span class="reservation-status-badge status-<?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
                            </header>

                            <dl class="payment-details">
                                <div>
                                    <dt>Referencia</dt>
                                    <dd><?= e($referenceCode !== '' ? $referenceCode : 'Sin referencia') ?></dd>
                                </div>
                                <div>
                                    <dt>Tipo</dt>
                                    <dd><?= e(payment_checkout_type_label((string) ($payment['checkout_type'] ?? ''))) ?></dd>
                                </div>
                                <div>
                                    <dt>Estado</dt>
                                    <dd><?= e($statusLabel) ?></dd>
                                </div>
                                <div>
                                    <dt>Fecha</dt>
                                    <dd><?= e($paidAtLabel !== '' ? $paidAtLabel : 'Sin fecha') ?></dd>
                                </div>
                                <div>
                                    <dt>Total</dt>
                                    <dd><?= e(reservation_format_money((float) ($payment['total_amount'] ?? 0))) ?></dd>
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
                            </dl>
                        </div>

                        <div class="payment-actions">
                            <a class="reservation-ticket-link" href="index.php?page=payment_detail&amp;payment_id=<?= e($paymentId) ?>">Ver detalle</a>
                            <a class="reservation-ticket-link payment-secondary-link" href="index.php?page=invoice&amp;payment_id=<?= e($paymentId) ?>">Comprobante</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
