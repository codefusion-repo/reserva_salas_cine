<?php
declare(strict_types=1);

$headerUser = is_array($user ?? null) ? $user : null;
$activeNav = isset($activeNav) ? (string) $activeNav : '';
$isAuthenticated = $headerUser !== null;
$isAdmin = $isAuthenticated && ($headerUser['role'] ?? '') === 'admin';
$headerName = $isAuthenticated ? (string) ($headerUser['name'] ?? 'Usuario') : 'Usuario';
$headerFirstName = trim(explode(' ', $headerName)[0] ?? $headerName);

$navItems = [
    [
        'key' => 'cartelera',
        'label' => 'Cartelera',
        'href' => 'index.php?page=cartelera',
    ],
];

if ($isAuthenticated) {
    $navItems[] = [
        'key' => 'my_reservations',
        'label' => 'Mis reservas',
        'href' => 'index.php?page=my_reservations',
    ];
    $navItems[] = [
        'key' => 'confiteria',
        'label' => 'Confiteria',
        'disabled' => true,
    ];
    $navItems[] = [
        'key' => 'socios',
        'label' => 'Hazte socio',
        'disabled' => true,
    ];

    if ($isAdmin) {
        $navItems[] = [
            'key' => 'admin',
            'label' => 'Admin',
            'href' => 'index.php?page=admin',
        ];
    }
} else {
    $navItems[] = [
        'key' => 'login',
        'label' => 'Iniciar sesion',
        'href' => 'index.php?page=login',
    ];
}
?>
<header class="topbar cinema-topbar">
    <a class="brand cinema-brand" href="index.php?page=cartelera" aria-label="ES Cine cartelera">
        <span class="brand-person" aria-hidden="true"></span>
        <span class="brand-film"><span>ES</span> <em>Cine</em></span>
    </a>

    <nav class="topnav cinema-nav" aria-label="Navegacion principal">
        <?php foreach ($navItems as $item): ?>
            <?php
            $isActive = $activeNav !== '' && $activeNav === $item['key'];
            $isDisabled = ($item['disabled'] ?? false) === true;
            $navClass = trim(($isActive ? 'is-active' : '') . ($isDisabled ? ' nav-placeholder' : ''));
            ?>
            <?php if ($isDisabled): ?>
                <span class="<?= e($navClass) ?>" aria-disabled="true"><?= e($item['label']) ?></span>
            <?php else: ?>
                <a class="<?= e($navClass) ?>" href="<?= e($item['href']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                    <?= e($item['label']) ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <?php if ($isAuthenticated): ?>
        <div class="user-menu">
            <button class="user-pill" type="button" aria-haspopup="true">
                <span class="user-avatar" aria-hidden="true"></span>
                <span>Hola, <?= e($headerFirstName !== '' ? $headerFirstName : 'Usuario') ?>!</span>
                <span class="user-caret" aria-hidden="true"></span>
            </button>
            <div class="user-dropdown">
                <span><?= e($headerUser['email'] ?? '') ?></span>
                <a href="index.php?page=my_reservations">Mis reservas</a>
                <a href="index.php?action=logout">Cerrar sesion</a>
            </div>
        </div>
    <?php endif; ?>
</header>
