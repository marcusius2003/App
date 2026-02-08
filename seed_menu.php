<?php
// seed_menu.php
require_once __DIR__ . '/admin/config/db.php';

$database = new Database();
$pdo = $database->getConnection();

// ID del tenant de prueba (Asumimos 1 o buscamos "PRUEBA RESTAURANTE")
$stmt = $pdo->query("SELECT id FROM academies WHERE name LIKE '%Restaurant%' LIMIT 1");
$academyId = $stmt->fetchColumn() ?: 1;

echo "Seeding menu for Academy ID: $academyId\n";

// Clear existing items
$pdo->exec("DELETE FROM restaurant_menu_items WHERE academy_id = $academyId");

$menuData = [
    'ENTRANTES' => [
        'Aceitunas aliñadas' => 3.50,
        'Pan con alioli y tomate' => 2.50,
        'Patatas bravas' => 5.00,
        'Patatas alioli' => 5.00,
        'Croquetas caseras (jamón / pollo / bacalao)' => 8.00,
        'Calamares a la romana' => 9.50,
        'Sepia a la plancha' => 10.00,
        'Tiras de pollo crujiente con salsa BBQ' => 7.50,
        'Ensaladilla rusa' => 6.00,
        'Tabla mixta (quesos y embutidos)' => 14.00,
        'Tosta de jamón y tomate' => 4.50,
        'Tosta de queso de cabra con cebolla caramelizada' => 5.50
    ],
    'PLATOS PRIMEROS' => [
        'Ensalada mixta' => 7.00,
        'Ensalada César' => 8.50,
        'Gazpacho / Salmorejo' => 6.50,
        'Sopa del día' => 5.50,
        'Macarrones a la boloñesa' => 7.50,
        'Arroz del día' => 9.00,
        'Huevos rotos con jamón' => 8.50,
        'Verduras a la plancha' => 8.00
    ],
    'PLATOS SEGUNDOS' => [
        'Hamburguesa completa' => 11.00,
        'Bocadillo de calamares' => 6.50,
        'Bocadillo de lomo con queso' => 6.00,
        'Bocadillo de pechuga con alioli' => 6.00,
        'Chivito' => 7.00,
        'Entrecot a la plancha' => 16.00,
        'Secreto ibérico' => 14.00,
        'Pollo a la plancha' => 9.50,
        'Merluza a la plancha' => 12.00,
        'Calamares o sepia (ración)' => 11.00
    ],
    'POSTRES' => [
        'Tarta de queso' => 5.00,
        'Flan casero' => 4.00,
        'Arroz con leche' => 4.00,
        'Helados' => 3.50,
        'Brownie con helado' => 6.00,
        'Fruta del día' => 3.00,
        'Yogur natural con miel' => 3.50
    ],
    'CAFÉS E INFUSIONES' => [
        'Café solo' => 1.50,
        'Cortado' => 1.60,
        'Café con leche' => 1.70,
        'Capuccino' => 2.50,
        'Carajillo' => 2.50,
        'Descafeinado' => 1.60,
        'Té' => 1.50,
        'Manzanilla / Poleo menta' => 1.50,
        'Rooibos' => 1.50,
        'Chocolate caliente' => 2.20
    ]
];

$stmt = $pdo->prepare("INSERT INTO restaurant_menu_items (academy_id, category, name, price, is_available) VALUES (?, ?, ?, ?, 1)");

foreach ($menuData as $category => $items) {
    foreach ($items as $name => $price) {
        $stmt->execute([$academyId, $category, $name, $price]);
    }
}

echo "Menu populated successfully with " . array_sum(array_map('count', $menuData)) . " items.\n";
