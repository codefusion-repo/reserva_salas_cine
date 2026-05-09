<?php
declare(strict_types=1);

require_once __DIR__ . '/ConcessionController.php';
require_once __DIR__ . '/ErrorController.php';
require_once __DIR__ . '/MembershipController.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/checkout_view.php';
require_once __DIR__ . '/../models/ConcessionProduct.php';
require_once __DIR__ . '/../models/Reservation.php';

function render_coming_soon_page(string $page): void
{
    auth_require_login();

    $pages = [
        'confiteria' => [
            'activeNav' => 'confiteria',
            'title' => 'Confiteria',
            'eyebrow' => 'Confiteria demo',
            'headline' => 'Confiteria',
            'lead' => 'Catálogo demo de combos para acompañar tu función con carrito simple en sesión.',
            'support' => 'La compra real no está disponible: el checkout es simulado y no crea pedidos.',
            'accent' => 'Carrito demo',
            'accentCopy' => 'El carrito guarda solo IDs y cantidades en sesión. Los precios se recalculan desde productos activos.',
            'catalog' => [],
            'showCatalog' => true,
            'catalogLoadError' => false,
            'catalogSetupRequired' => false,
            'items' => [
                ['icon' => '🍿', 'label' => 'Productos activos', 'copy' => 'El catálogo visible se lee desde concession_products.'],
                ['icon' => '🛒', 'label' => 'Carrito en sesión', 'copy' => 'Solo guarda product_id y cantidad por item.'],
                ['icon' => '💳', 'label' => 'Pago simulado', 'copy' => 'No existe pasarela, tarjeta ni pedido persistido.'],
                ['icon' => '🧾', 'label' => 'Sin pedidos', 'copy' => 'No se crean compras, stock ni ordenes de confiteria.'],
            ],
            'notes' => [
                'Compra real no disponible.',
                'No hay pago real.',
                'Checkout simulado sin pasarela.',
                'Sin stock, pedidos ni persistencia del carrito en base de datos.',
            ],
        ],
        'socios' => [
            'activeNav' => 'socios',
            'title' => 'Socios',
            'eyebrow' => 'MEMBRESÍA DEMO',
            'headline' => 'HAZTE SOCIO DEMO',
            'panelKicker' => 'Demo persistida',
            'panelHeadline' => 'Activa tu membresía demo',
            'lead' => 'Activa una membresía demo para probar el estado de socio en tu cuenta.',
            'support' => '',
            'accent' => 'Estado de cuenta',
            'accentCopy' => 'La membresía demo se guarda por usuario.',
            'featureIcon' => 'DEMO',
            'items' => [
                [
                    'icon' => '🏷️',
                    'label' => 'Beneficios simulados',
                    'copy' => 'Ventajas de socio simuladas sin aplicar descuentos reales.',
                    'status' => 'Simulado',
                ],
                [
                    'icon' => '🎟️',
                    'label' => 'Estado persistido',
                    'copy' => 'El estado de socio demo se conserva después de cerrar sesión.',
                    'status' => 'Cuenta',
                ],
                [
                    'icon' => '⭐',
                    'label' => 'Puntos ficticios',
                    'copy' => 'Referencia académica de beneficios, sin acumulación real.',
                    'status' => 'Simulado',
                ],
                [
                    'icon' => '🎂',
                    'label' => 'Sin pago real',
                    'copy' => 'No se solicita tarjeta, pasarela ni comprobante de pago.',
                    'status' => 'Demo',
                ],
                [
                    'icon' => '🍿',
                    'label' => 'Sin descuentos activos',
                    'copy' => 'La membresía no cambia precios de entradas, reservas ni confitería.',
                    'status' => 'Sin precios',
                ],
                [
                    'icon' => '🎁',
                    'label' => 'Proyecto académico',
                    'copy' => 'Beneficios simulados para mostrar el flujo de socio demo.',
                    'status' => 'Académico',
                ],
            ],
            'heroActions' => [
                ['type' => 'link', 'label' => 'Conoce beneficios', 'href' => '#socios-beneficios', 'class' => 'movie-state-link-secondary'],
            ],
            'benefitsLayout' => true,
            'benefitsSectionId' => 'socios-beneficios',
            'notes' => [
                'Membresía demo sin pago real.',
                'Beneficios simulados para el proyecto académico.',
                'No hay pago real, descuentos activos ni planes pagos reales.',
            ],
        ],
        'pago' => [
            'activeNav' => 'pago',
            'title' => 'Pago',
            'eyebrow' => 'Proximamente',
            'headline' => 'Pago simulado',
            'lead' => 'El flujo de pago queda reservado para una iteracion posterior.',
            'support' => 'Esta ruta solo muestra una pagina conceptual. No hay checkout funcional ni confirmacion de pago.',
            'accent' => 'Sin pago real',
            'accentCopy' => 'No existe pasarela, no se solicitan datos de tarjeta y no se almacena informacion bancaria.',
            'items' => [
                ['icon' => '💳', 'label' => 'Sin tarjeta', 'copy' => 'No hay campos para numero, CVV ni vencimiento.'],
                ['icon' => '🧾', 'label' => 'Resumen futuro', 'copy' => 'El comprobante de pago simulado sera otro alcance.'],
                ['icon' => '🚧', 'label' => 'Checkout pendiente', 'copy' => 'El flujo pending a confirmed no se implementa aqui.'],
                ['icon' => '🔒', 'label' => 'Sin pasarela', 'copy' => 'No se conecta ninguna API ni proveedor externo.'],
            ],
            'notes' => [
                'No hay pago real ni pasarela de pago.',
                'No se solicitan ni almacenan datos de tarjeta.',
                'No se modifica el flujo actual de reservas.',
            ],
        ],
    ];

    if (!isset($pages[$page])) {
        render_not_found_page(
            'Pagina no encontrada',
            'La ruta solicitada no existe o ya no esta disponible.'
        );
        return;
    }

    $user = current_user();
    $memberDemoState = member_demo_state_for_user($user);
    $memberDemoActive = (bool) ($memberDemoState['is_active'] ?? false);
    $comingSoon = $pages[$page];

    if ($page === 'socios') {
        $comingSoon['lead'] = $memberDemoActive
            ? 'Tu membresía demo está activa y persistida en tu cuenta.'
            : 'Activa una membresía demo para probar el estado de socio persistido.';
        $comingSoon['notes'] = [];
        $comingSoon['panelHeadline'] = $memberDemoActive
            ? 'Membresía demo activa'
            : 'Activa tu membresía demo';
        $comingSoon['memberDemo'] = [
            'isActive' => $memberDemoActive,
            'stateActiveLabel' => 'Socio Cine Demo activo',
            'stateInactiveLabel' => member_demo_status_label($memberDemoState),
            'stateActiveCopy' => 'Ya puedes ver tu estado de socio demo incluso después de cerrar sesión.',
            'stateInactiveCopy' => member_demo_status_copy($memberDemoState),
            'stateNotes' => [
                $memberDemoActive
                    ? 'Estado demo persistido. No aplica descuentos reales.'
                    : 'Demo académica sin pago real ni pasarela.',
            ],
            'activateAction' => checkout_url('membership'),
            'activateMethod' => 'get',
            'deactivateAction' => 'index.php?action=member_demo_deactivate',
            'activateLabel' => 'IR A CHECKOUT DEMO',
            'deactivateLabel' => 'DESACTIVAR MEMBRESÍA DEMO',
        ];
    }

    $cartSummary = [
        'items' => [],
        'total' => 0.0,
        'total_label' => reservation_format_money(0) . ' demo',
        'is_empty' => true,
        'pruned' => false,
    ];

    if ($page === 'confiteria') {
        try {
            if (!concession_products_table_exists()) {
                $comingSoon['catalogSetupRequired'] = true;
            } else {
                $activeProducts = concession_products_active_all();
                $comingSoon['catalog'] = array_map(
                    static fn (array $product): array => [
                        'id' => (int) ($product['id'] ?? 0),
                        'icon' => (string) ($product['icon'] ?? ''),
                        'label' => (string) ($product['badge'] ?? ''),
                        'name' => (string) ($product['name'] ?? ''),
                        'description' => (string) ($product['description'] ?? ''),
                        'price_amount' => (float) ($product['price_amount'] ?? 0),
                        'price' => reservation_format_money((float) ($product['price_amount'] ?? 0)) . ' demo',
                        'button' => 'Agregar',
                    ],
                    $activeProducts
                );
                $cartSummary = concession_cart_summary_from_products($activeProducts);

                if (($cartSummary['pruned'] ?? false) === true) {
                    flash_set('info', 'Se quitaron del carrito productos que ya no están activos.');
                }
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $comingSoon['catalog'] = [];
            $comingSoon['catalogLoadError'] = true;
        }
    }

    $messages = flash_get();

    require __DIR__ . '/../views/coming_soon.php';
}
