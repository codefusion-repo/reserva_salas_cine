<?php
declare(strict_types=1);

$checkoutType = (string) ($checkout['type'] ?? '');
$activeNav = (string) ($checkout['active_nav'] ?? '');
$pageTitle = (string) ($checkout['title'] ?? 'Checkout simulado');
$heading = (string) ($checkout['heading'] ?? 'Checkout simulado');
$eyebrow = (string) ($checkout['eyebrow'] ?? 'Pago simulado academico');
$lead = (string) ($checkout['lead'] ?? '');
$summaryTitle = (string) ($checkout['summary_title'] ?? 'Resumen');
$totalLabel = (string) ($checkout['total_label'] ?? reservation_format_money(0) . ' demo');
$pricing = is_array($checkout['pricing'] ?? null) ? $checkout['pricing'] : [];
$subtotalLabel = (string) ($pricing['subtotal_label'] ?? reservation_format_money(0) . ' demo');
$discountLabel = (string) ($pricing['discount_label'] ?? reservation_format_money(0) . ' demo');
$totalFinalLabel = (string) ($pricing['total_label'] ?? $totalLabel);
$couponApplied = ($pricing['applied'] ?? false) === true;
$couponCode = (string) ($pricing['code'] ?? '');
$couponLabel = (string) ($pricing['label'] ?? '');
$couponPercentLabel = (string) ($pricing['percent_label'] ?? '');
$returnUrl = (string) ($checkout['return_url'] ?? 'index.php?page=cartelera');
$canConfirm = ($checkout['can_confirm'] ?? false) === true;
$confirmFields = is_array($checkout['confirm_fields'] ?? null) ? $checkout['confirm_fields'] : ['type' => $checkoutType];
$couponFields = is_array($checkout['coupon_fields'] ?? null) ? $checkout['coupon_fields'] : $confirmFields;
$reservation = is_array($checkout['reservation'] ?? null) ? $checkout['reservation'] : null;
$showtimeLabels = is_array($checkout['showtime_labels'] ?? null) ? $checkout['showtime_labels'] : ['datetime' => '', 'date' => '', 'time' => ''];
$cartSummary = is_array($checkout['cart_summary'] ?? null) ? $checkout['cart_summary'] : ['items' => [], 'total_label' => reservation_format_money(0) . ' demo'];
$cartItems = is_array($cartSummary['items'] ?? null) ? $cartSummary['items'] : [];
$cartLoadError = ($checkout['cart_load_error'] ?? false) === true;
$catalogSetupRequired = ($checkout['catalog_setup_required'] ?? false) === true;
$lastReceipt = is_array($checkout['last_receipt'] ?? null) ? $checkout['last_receipt'] : null;
$membershipPlan = is_array($checkout['membership_plan'] ?? null) ? $checkout['membership_plan'] : [];
$memberDemoActive = ($checkout['member_demo_active'] ?? false) === true;
$paymentStateLabel = $canConfirm ? 'Listo para confirmar' : 'No disponible';
$confirmButtonLabel = match ($checkoutType) {
    'reservation' => 'Confirmar reserva',
    'concessions' => 'Confirmar confiteria',
    'membership' => 'Activar socio demo',
    default => 'Confirmar',
};
$paymentHelp = match ($checkoutType) {
    'reservation' => 'La reserva pasara de pendiente a confirmada al completar este paso.',
    'concessions' => 'Se generara un comprobante demo en sesion y el carrito quedara vacio.',
    'membership' => 'La membresia demo quedara activa solo en esta sesion.',
    default => 'Confirma el checkout simulado para continuar.',
};
$reservationSeatLabels = [];

if ($reservation !== null) {
    foreach (($reservation['seats'] ?? []) as $seat) {
        $reservationSeatLabels[] = reservation_seat_key((string) ($seat['seat_row'] ?? ''), (int) ($seat['seat_number'] ?? 0));
    }
}

$reservationSeatSummary = $reservationSeatLabels !== [] ? implode(', ', $reservationSeatLabels) : 'Sin butacas';
$reservationStatus = $reservation !== null ? (string) ($reservation['status'] ?? '') : '';
$reservationCode = $reservation !== null ? reservation_visual_code((int) ($reservation['id'] ?? 0)) : '';
$reservationStatusClass = $reservationStatus !== '' ? reservation_status_css_class($reservationStatus) : 'unknown';
$reservationStatusLabel = $reservationStatus !== '' ? reservation_status_label($reservationStatus) : 'Sin estado';
$reservationRoomLabel = $reservation !== null
    ? trim((string) ($reservation['room_name'] ?? '') . ' - ' . (string) ($reservation['room_location'] ?? ''), ' -')
    : '';
$reservationFormatLabel = $reservation !== null
    ? trim((string) ($reservation['format_label'] ?? '') . ' - ' . (string) ($reservation['language_label'] ?? ''), ' -')
    : '';
$membershipBenefits = is_array($membershipPlan['benefits'] ?? null) ? $membershipPlan['benefits'] : [];
$receiptItems = $lastReceipt !== null && is_array($lastReceipt['items'] ?? null) ? $lastReceipt['items'] : [];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - Reserva Salas Cine</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-screen checkout-screen">
    <?php require __DIR__ . '/partials/header.php'; ?>

    <main class="checkout-shell">
        <?php if ($messages !== []): ?>
            <div class="page-messages cartelera-messages" aria-live="polite">
                <?php foreach ($messages as $message): ?>
                    <p class="notice notice-<?= e($message['type'] ?? 'info') ?>"><?= e($message['message'] ?? '') ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="checkout-heading">
            <a class="movie-back" href="<?= e($returnUrl) ?>" aria-label="Volver">
                <span aria-hidden="true"></span>
            </a>
            <div>
                <p class="eyebrow"><?= e($eyebrow) ?></p>
                <h1><?= e($heading) ?></h1>
                <?php if ($lead !== ''): ?>
                    <p><?= e($lead) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="checkout-layout">
            <section class="checkout-summary-panel" aria-labelledby="checkout-summary-title">
                <div class="checkout-section-heading">
                    <p class="eyebrow">Resumen</p>
                    <h2 id="checkout-summary-title"><?= e($summaryTitle) ?></h2>
                </div>

                <?php if ($checkoutType === 'reservation' && $reservation !== null): ?>
                    <dl class="checkout-details">
                        <div>
                            <dt>Codigo</dt>
                            <dd><?= e($reservationCode) ?></dd>
                        </div>
                        <div>
                            <dt>Pelicula</dt>
                            <dd><?= e($reservation['movie_title'] ?? 'Pelicula') ?></dd>
                        </div>
                        <div>
                            <dt>Funcion</dt>
                            <dd><?= e($showtimeLabels['datetime'] !== '' ? $showtimeLabels['datetime'] : 'Sin horario') ?></dd>
                        </div>
                        <div>
                            <dt>Sala</dt>
                            <dd><?= e($reservationRoomLabel !== '' ? $reservationRoomLabel : 'Sin sala') ?></dd>
                        </div>
                        <div>
                            <dt>Formato</dt>
                            <dd><?= e($reservationFormatLabel !== '' ? $reservationFormatLabel : 'Sin formato') ?></dd>
                        </div>
                        <div>
                            <dt>Butacas</dt>
                            <dd><?= e($reservationSeatSummary) ?></dd>
                        </div>
                        <div>
                            <dt>Entradas</dt>
                            <dd><?= e(count($reservationSeatLabels)) ?></dd>
                        </div>
                        <div>
                            <dt>Estado</dt>
                            <dd><span class="reservation-status-badge status-<?= e($reservationStatusClass) ?>"><?= e($reservationStatusLabel) ?></span></dd>
                        </div>
                    </dl>
                <?php elseif ($checkoutType === 'concessions'): ?>
                    <?php if ($cartLoadError): ?>
                        <div class="checkout-state">
                            <h3>No se pudo cargar el carrito</h3>
                            <p>Intenta nuevamente mas tarde.</p>
                        </div>
                    <?php elseif ($catalogSetupRequired): ?>
                        <div class="checkout-state">
                            <h3>Catalogo no disponible</h3>
                            <p>Los productos demo de confiteria no estan instalados en esta base de datos.</p>
                        </div>
                    <?php elseif ($cartItems === []): ?>
                        <div class="checkout-state">
                            <h3>Carrito vacio</h3>
                            <p>Agrega combos activos desde Confiteria para abrir un checkout con total demo.</p>
                        </div>
                    <?php else: ?>
                        <div class="checkout-item-list">
                            <?php foreach ($cartItems as $cartItem): ?>
                                <article class="checkout-item">
                                    <div>
                                        <h3><?= e($cartItem['name'] ?? '') ?></h3>
                                        <p><?= e($cartItem['unit_price_label'] ?? '') ?> unidad</p>
                                    </div>
                                    <div>
                                        <span>Cant. <?= e($cartItem['quantity'] ?? 0) ?></span>
                                        <strong><?= e($cartItem['subtotal_label'] ?? '') ?></strong>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($lastReceipt !== null): ?>
                        <section class="checkout-receipt" aria-labelledby="checkout-receipt-title">
                            <p class="eyebrow">Ultimo checkout</p>
                            <h3 id="checkout-receipt-title"><?= e($lastReceipt['code'] ?? 'Comprobante demo') ?></h3>
                            <p>Comprobante demo guardado solo en esta sesion.</p>
                            <?php if ($receiptItems !== []): ?>
                                <ul>
                                    <?php foreach ($receiptItems as $receiptItem): ?>
                                        <li>
                                            <?= e($receiptItem['quantity'] ?? 0) ?> x <?= e($receiptItem['name'] ?? '') ?>
                                            <strong><?= e($receiptItem['subtotal_label'] ?? '') ?></strong>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <strong><?= e($lastReceipt['total_label'] ?? reservation_format_money(0) . ' demo') ?></strong>
                        </section>
                    <?php endif; ?>
                <?php elseif ($checkoutType === 'membership'): ?>
                    <div class="checkout-membership-plan">
                        <h3><?= e($membershipPlan['name'] ?? CHECKOUT_MEMBERSHIP_PLAN_LABEL) ?></h3>
                        <p>Activacion demo para probar el estado de socio durante la sesion actual.</p>
                        <?php if ($memberDemoActive): ?>
                            <span class="reservation-status-badge status-confirmed">Activo</span>
                        <?php else: ?>
                            <span class="reservation-status-badge status-pending">Pendiente</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($membershipBenefits !== []): ?>
                        <ul class="checkout-benefits">
                            <?php foreach ($membershipBenefits as $benefit): ?>
                                <li><?= e($benefit) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>

                <dl class="checkout-amount-list" aria-label="Totales del checkout">
                    <div>
                        <dt>Subtotal</dt>
                        <dd><?= e($subtotalLabel) ?></dd>
                    </div>
                    <div>
                        <dt>Descuento demo</dt>
                        <dd><?= e($discountLabel) ?></dd>
                    </div>
                    <div>
                        <dt>Total final</dt>
                        <dd><?= e($totalFinalLabel) ?></dd>
                    </div>
                </dl>
            </section>

            <aside class="checkout-payment-panel" aria-labelledby="checkout-payment-title">
                <div class="checkout-section-heading">
                    <p class="eyebrow">Metodo</p>
                    <h2 id="checkout-payment-title">Pago simulado academico</h2>
                </div>

                <dl class="checkout-payment-details">
                    <div>
                        <dt>Estado</dt>
                        <dd><?= e($paymentStateLabel) ?></dd>
                    </div>
                    <div>
                        <dt>Modo</dt>
                        <dd>Simulacion local sin pasarela</dd>
                    </div>
                    <div>
                        <dt>Datos sensibles</dt>
                        <dd>No se solicitan ni almacenan datos de pago.</dd>
                    </div>
                </dl>

                <section class="checkout-coupon-panel" aria-labelledby="checkout-coupon-title">
                    <div class="checkout-coupon-heading">
                        <p class="eyebrow">Cupon demo</p>
                        <h3 id="checkout-coupon-title">Descuento</h3>
                    </div>

                    <?php if ($couponApplied): ?>
                        <div class="checkout-coupon-applied">
                            <div>
                                <span>Cupon aplicado</span>
                                <strong><?= e($couponCode) ?></strong>
                                <p><?= e(trim($couponLabel . ' ' . $couponPercentLabel)) ?></p>
                            </div>
                            <form class="checkout-coupon-remove-form" action="index.php?action=coupon_remove" method="post">
                                <?= csrf_token_field() ?>
                                <?php foreach ($couponFields as $fieldName => $fieldValue): ?>
                                    <input type="hidden" name="<?= e($fieldName) ?>" value="<?= e($fieldValue) ?>">
                                <?php endforeach; ?>
                                <button type="submit">Quitar</button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <form class="checkout-coupon-form" action="index.php?action=coupon_apply" method="post">
                        <?= csrf_token_field() ?>
                        <?php foreach ($couponFields as $fieldName => $fieldValue): ?>
                            <input type="hidden" name="<?= e($fieldName) ?>" value="<?= e($fieldValue) ?>">
                        <?php endforeach; ?>
                        <label for="coupon-code">Codigo de cupon</label>
                        <div>
                            <input id="coupon-code" name="coupon_code" type="text" maxlength="24" autocomplete="off" placeholder="CINE10">
                            <button type="submit">Aplicar</button>
                        </div>
                    </form>
                </section>

                <p class="checkout-payment-note"><?= e($paymentHelp) ?></p>

                <form class="checkout-confirm-form" action="index.php?action=checkout_confirm" method="post">
                    <?= csrf_token_field() ?>
                    <?php foreach ($confirmFields as $fieldName => $fieldValue): ?>
                        <input type="hidden" name="<?= e($fieldName) ?>" value="<?= e($fieldValue) ?>">
                    <?php endforeach; ?>
                    <button class="checkout-confirm-button" type="submit"<?= $canConfirm ? '' : ' disabled' ?>>
                        <?= e($confirmButtonLabel) ?>
                    </button>
                </form>

                <a class="checkout-secondary-link" href="<?= e($returnUrl) ?>">Volver</a>
            </aside>
        </div>
    </main>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
