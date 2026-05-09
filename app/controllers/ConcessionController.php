<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/checkout_view.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/reservation_view.php';
require_once __DIR__ . '/../models/ConcessionProduct.php';
require_once __DIR__ . '/../models/Reservation.php';

const CONCESSIONS_CART_SESSION_KEY = 'concessions_cart';
const CONCESSIONS_LAST_CHECKOUT_SESSION_KEY = 'last_concessions_checkout';
const CONCESSIONS_CART_MAX_QUANTITY = 10;

function concession_cart_redirect(): void
{
    redirect_to('index.php?page=confiteria');
}

function concession_cart_quantity_from_request(mixed $value): ?int
{
    $quantity = positive_int_from_request($value);

    if ($quantity === null || $quantity > CONCESSIONS_CART_MAX_QUANTITY) {
        return null;
    }

    return $quantity;
}

function concession_cart_get(): array
{
    app_session_start();

    $storedCart = $_SESSION[CONCESSIONS_CART_SESSION_KEY] ?? [];
    $cart = [];
    $changed = !is_array($storedCart);

    if (is_array($storedCart)) {
        foreach ($storedCart as $productId => $quantity) {
            $normalizedProductId = positive_int_from_request($productId);
            $normalizedQuantity = positive_int_from_request($quantity);

            if ($normalizedProductId === null || $normalizedQuantity === null) {
                $changed = true;
                continue;
            }

            if ($normalizedQuantity > CONCESSIONS_CART_MAX_QUANTITY) {
                $normalizedQuantity = CONCESSIONS_CART_MAX_QUANTITY;
                $changed = true;
            }

            $cart[$normalizedProductId] = $normalizedQuantity;
        }
    }

    if ($changed) {
        concession_cart_save($cart);
    }

    return $cart;
}

function concession_cart_save(array $cart): void
{
    app_session_start();

    ksort($cart);
    $_SESSION[CONCESSIONS_CART_SESSION_KEY] = $cart;
}

function concession_cart_summary_from_products(array $products): array
{
    $productsById = [];

    foreach ($products as $product) {
        $productId = (int) ($product['id'] ?? 0);

        if ($productId > 0) {
            $productsById[$productId] = $product;
        }
    }

    $cart = concession_cart_get();
    $items = [];
    $total = 0.0;
    $pruned = false;

    foreach ($cart as $productId => $quantity) {
        if (!isset($productsById[$productId])) {
            unset($cart[$productId]);
            $pruned = true;
            continue;
        }

        $product = $productsById[$productId];
        $unitPrice = (float) ($product['price_amount'] ?? 0);
        $subtotal = $unitPrice * $quantity;
        $total += $subtotal;

        $items[] = [
            'product_id' => $productId,
            'name' => (string) ($product['name'] ?? ''),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_price_label' => reservation_format_money($unitPrice),
            'subtotal' => $subtotal,
            'subtotal_label' => reservation_format_money($subtotal),
        ];
    }

    if ($pruned) {
        concession_cart_save($cart);
    }

    return [
        'items' => $items,
        'total' => $total,
        'total_label' => reservation_format_money($total),
        'is_empty' => $items === [],
        'pruned' => $pruned,
    ];
}

function concession_checkout_last_receipt(): ?array
{
    app_session_start();

    $receipt = $_SESSION[CONCESSIONS_LAST_CHECKOUT_SESSION_KEY] ?? null;

    return is_array($receipt) ? $receipt : null;
}

function concession_checkout_save_receipt(array $cartSummary, ?string $referenceCode = null, array $pricing = []): array
{
    app_session_start();

    $items = [];

    foreach (($cartSummary['items'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $items[] = [
            'name' => (string) ($item['name'] ?? ''),
            'quantity' => (int) ($item['quantity'] ?? 0),
            'unit_price_label' => (string) ($item['unit_price_label'] ?? ''),
            'subtotal_label' => (string) ($item['subtotal_label'] ?? ''),
        ];
    }

    $receipt = [
        'code' => $referenceCode !== null && $referenceCode !== ''
            ? $referenceCode
            : 'CONF-' . date('Ymd-His') . '-' . random_int(100, 999),
        'created_at' => date('Y-m-d H:i:s'),
        'items' => $items,
        'subtotal_label' => (string) ($pricing['subtotal_label'] ?? $cartSummary['total_label'] ?? checkout_demo_money_label(0)),
        'discount_label' => (string) ($pricing['discount_label'] ?? checkout_demo_money_label(0)),
        'total_label' => (string) ($pricing['total_label'] ?? $cartSummary['total_label'] ?? checkout_demo_money_label(0)),
    ];

    $_SESSION[CONCESSIONS_LAST_CHECKOUT_SESSION_KEY] = $receipt;

    return $receipt;
}


function handle_concession_add(): void
{
    auth_require_login();
    csrf_require_valid_post();

    $productId = positive_int_from_request($_POST['product_id'] ?? null);
    $quantity = concession_cart_quantity_from_request($_POST['quantity'] ?? 1);

    if ($productId === null || $quantity === null) {
        flash_set('error', 'Selecciona un producto y cantidad validos.');
        concession_cart_redirect();
    }

    try {
        $product = concession_product_find_active_by_id((int) $productId);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo validar el producto en este momento.');
        concession_cart_redirect();
    }

    if ($product === null) {
        flash_set('error', 'El producto seleccionado no existe o no esta activo.');
        concession_cart_redirect();
    }

    $cart = concession_cart_get();
    $currentQuantity = (int) ($cart[(int) $productId] ?? 0);
    $newQuantity = $currentQuantity + (int) $quantity;

    if ($newQuantity > CONCESSIONS_CART_MAX_QUANTITY) {
        $newQuantity = CONCESSIONS_CART_MAX_QUANTITY;
        flash_set('info', 'La cantidad maxima por producto es ' . CONCESSIONS_CART_MAX_QUANTITY . '.');
    } else {
        flash_set('success', 'Producto agregado al carrito.');
    }

    $cart[(int) $productId] = $newQuantity;
    concession_cart_save($cart);
    concession_cart_redirect();
}

function handle_concession_update(): void
{
    auth_require_login();
    csrf_require_valid_post();

    $productId = positive_int_from_request($_POST['product_id'] ?? null);
    $quantity = concession_cart_quantity_from_request($_POST['quantity'] ?? null);

    if ($productId === null || $quantity === null) {
        flash_set('error', 'Selecciona un producto y cantidad validos.');
        concession_cart_redirect();
    }

    $cart = concession_cart_get();

    try {
        $product = concession_product_find_active_by_id((int) $productId);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo validar el producto en este momento.');
        concession_cart_redirect();
    }

    if ($product === null) {
        unset($cart[(int) $productId]);
        concession_cart_save($cart);
        flash_set('error', 'El producto ya no esta activo y fue quitado del carrito.');
        concession_cart_redirect();
    }

    $cart[(int) $productId] = (int) $quantity;
    concession_cart_save($cart);
    flash_set('success', 'Cantidad actualizada.');
    concession_cart_redirect();
}

function handle_concession_remove(): void
{
    auth_require_login();
    csrf_require_valid_post();

    $productId = positive_int_from_request($_POST['product_id'] ?? null);

    if ($productId === null) {
        flash_set('error', 'Selecciona un producto valido para quitar.');
        concession_cart_redirect();
    }

    $cart = concession_cart_get();
    unset($cart[(int) $productId]);
    concession_cart_save($cart);
    flash_set('success', 'Producto quitado del carrito.');
    concession_cart_redirect();
}

function handle_concession_clear(): void
{
    auth_require_login();
    csrf_require_valid_post();
    concession_cart_save([]);
    checkout_coupon_session_remove('concessions');
    flash_set('success', 'Carrito vaciado.');
    concession_cart_redirect();
}
