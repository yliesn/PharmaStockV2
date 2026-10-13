<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../../config/helpers.php';

send_cors();

// Vérifie le token
$token = get_bearer_token();
if (!$token) {
    json_response(['error' => 'Token manquant'], 401);
}

$payload = jwt_verify($token);
if (!$payload) {
    json_response(['error' => 'Token invalide ou expiré'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Méthode non autorisée'], 405);
}

$pdo = getDB();

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(5, (int)$_GET['limit'])) : 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
// statut : 'actifs' (défaut), 'inactifs' ou 'tous'
$statut = isset($_GET['statut']) ? trim($_GET['statut']) : 'actifs';
if (!in_array($statut, ['actifs', 'inactifs', 'tous'], true)) {
    json_response(['error' => 'Statut invalide (actifs, inactifs ou tous)'], 400);
}

// Compte le total
$where = [];
$params = [];
if ($statut !== 'tous') {
    $where[] = 'actif = ' . ($statut === 'actifs' ? 1 : 0);
}
if ($search) {
    $where[] = '(reference LIKE ? OR designation LIKE ?)';
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm];
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM FOURNITURE $whereClause");
$stmt->execute($params);
$total = $stmt->fetch()['total'];

// Récupère les fournitures
$query = "
    SELECT " . SUPPLY_COLUMNS . "
    FROM FOURNITURE
    $whereClause
    ORDER BY reference ASC
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($query);
$params[] = $limit;
$params[] = $offset;
$stmt->bindValue(count($params) - 1, $limit, PDO::PARAM_INT);
$stmt->bindValue(count($params), $offset, PDO::PARAM_INT);
$stmt->execute($params);
$supplies = $stmt->fetchAll();

json_response([
    'data' => array_map('format_supply', $supplies),
    'pagination' => [
        'page'       => $page,
        'limit'      => $limit,
        'total'      => $total,
        'pages'      => ceil($total / $limit),
    ],
], 200);
