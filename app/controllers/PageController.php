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
            'eyebrow' => 'CONFITERIA',
            'headline' => 'Combos para tu funcion',
            'panelKicker' => '',
            'panelHeadline' => 'Elige algo para la pelicula',
            'lead' => 'Elige cabritas, bebidas y dulces para acompañar tu pelicula.',
            'support' => 'Agrega tus favoritos, revisa el carrito y continua cuando este listo.',
            'accent' => 'Tu carrito',
            'accentCopy' => 'Controla cantidades y total antes de continuar.',
            'featureIcon' => '🍿',
            'catalog' => [],
            'showCatalog' => true,
            'catalogLoadError' => false,
            'catalogSetupRequired' => false,
            'items' => [
                ['icon' => '🍿', 'label' => 'Combos listos', 'copy' => 'Cabritas, bebidas y dulces para sumar a tu funcion.'],
                ['icon' => '🛒', 'label' => 'Carrito simple', 'copy' => 'Ajusta cantidades antes de pasar al pago.'],
                ['icon' => '🏷️', 'label' => 'Cupones', 'copy' => 'Aplica un codigo valido cuando corresponda.'],
                ['icon' => '🧾', 'label' => 'Comprobante', 'copy' => 'Al confirmar, veras el resumen de tu compra.'],
            ],
            'notes' => [],
        ],
        'socios' => [
            'activeNav' => 'socios',
            'title' => 'Socios',
            'eyebrow' => 'SOCIOS',
            'headline' => 'HAZTE SOCIO',
            'panelKicker' => 'Membresia de prueba',
            'panelHeadline' => 'Activa tu membresia',
            'lead' => 'Activa tu membresia para ver tu estado de socio en la cuenta.',
            'support' => '',
            'accent' => 'Estado de cuenta',
            'accentCopy' => 'Tu estado se conserva entre visitas.',
            'featureIcon' => 'SOCIO',
            'items' => [
                [
                    'icon' => '🏷️',
                    'label' => 'Beneficios de socio',
                    'copy' => 'Ventajas de prueba para mostrar el flujo de membresia.',
                    'status' => 'Prueba',
                ],
                [
                    'icon' => '🎟️',
                    'label' => 'Estado de cuenta',
                    'copy' => 'Tu cuenta muestra si la membresia esta activa.',
                    'status' => 'Cuenta',
                ],
                [
                    'icon' => '⭐',
                    'label' => 'Beneficios referenciales',
                    'copy' => 'No acumula puntos ni descuentos reales.',
                    'status' => 'Prueba',
                ],
                [
                    'icon' => '🎂',
                    'label' => 'Sin cobro real',
                    'copy' => 'La activacion no solicita datos bancarios.',
                    'status' => 'Seguro',
                ],
                [
                    'icon' => '🍿',
                    'label' => 'Sin descuentos activos',
                    'copy' => 'La membresia no cambia precios de entradas, reservas ni confiteria.',
                    'status' => 'Sin precios',
                ],
                [
                    'icon' => '🎁',
                    'label' => 'Vista de prueba',
                    'copy' => 'Sirve para revisar el flujo sin compromisos reales.',
                    'status' => 'Prueba',
                ],
            ],
            'heroActions' => [
                ['type' => 'link', 'label' => 'Conoce beneficios', 'href' => '#socios-beneficios', 'class' => 'movie-state-link-secondary'],
            ],
            'benefitsLayout' => true,
            'benefitsSectionId' => 'socios-beneficios',
            'notes' => [
                'Membresia de prueba, sin cobro real.',
                'No aplica descuentos ni planes reales.',
            ],
        ],
        'pago' => [
            'activeNav' => 'pago',
            'title' => 'Pago',
            'eyebrow' => 'PAGO',
            'headline' => 'Pago de prueba',
            'panelKicker' => '',
            'panelHeadline' => 'Sin cobro real',
            'lead' => 'Revisa como se confirman compras sin cobro real.',
            'support' => 'Para reservas, confiteria y socios, usa el paso de confirmacion de cada flujo.',
            'accent' => 'Protegido',
            'accentCopy' => 'No se solicitan datos bancarios.',
            'featureIcon' => '✓',
            'items' => [
                ['icon' => '💳', 'label' => 'Sin tarjeta', 'copy' => 'No hay campos para numero, CVV ni vencimiento.'],
                ['icon' => '🧾', 'label' => 'Comprobantes', 'copy' => 'Los comprobantes indican cuando no hubo cobro real.'],
                ['icon' => '✅', 'label' => 'Confirmacion', 'copy' => 'Cada flujo confirma su propio resumen.'],
                ['icon' => '🔒', 'label' => 'Datos protegidos', 'copy' => 'No se conecta ningun proveedor externo.'],
            ],
            'notes' => [
                'No hay cobro real.',
                'No se solicitan ni almacenan datos de tarjeta.',
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
            ? 'Tu membresia esta activa.'
            : 'Activa tu membresia para ver tu estado de socio.';
        $comingSoon['notes'] = [];
        $comingSoon['panelHeadline'] = $memberDemoActive
            ? 'Membresia activa'
            : 'Activa tu membresia';
        $comingSoon['memberDemo'] = [
            'isActive' => $memberDemoActive,
            'stateActiveLabel' => 'Socio Cine activo',
            'stateInactiveLabel' => member_demo_status_label($memberDemoState),
            'stateActiveCopy' => 'Tu cuenta ya muestra el estado de socio.',
            'stateInactiveCopy' => member_demo_status_copy($memberDemoState),
            'stateNotes' => [
                $memberDemoActive
                    ? 'No aplica descuentos reales.'
                    : 'Pago simulado, sin cobro real.',
            ],
            'activateAction' => checkout_url('membership'),
            'activateMethod' => 'get',
            'deactivateAction' => 'index.php?action=member_demo_deactivate',
            'activateLabel' => 'ACTIVAR MEMBRESIA',
            'deactivateLabel' => 'DESACTIVAR MEMBRESIA',
        ];
    }

    $cartSummary = [
        'items' => [],
        'total' => 0.0,
        'total_label' => reservation_format_money(0),
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
                        'price' => reservation_format_money((float) ($product['price_amount'] ?? 0)),
                        'button' => 'Agregar',
                    ],
                    $activeProducts
                );
                $cartSummary = concession_cart_summary_from_products($activeProducts);

                if (($cartSummary['pruned'] ?? false) === true) {
                    flash_set('info', 'Actualizamos tu carrito porque algunos productos ya no estan disponibles.');
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
