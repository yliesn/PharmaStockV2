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

// Compte le total
$whereClause = '';
$params = [];
if ($search) {
    $whereClause = 'WHERE reference LIKE ? OR designation LIKE ?';
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm];
}

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM FOURNITURE $whereClause");
$stmt->execute($params);
$total = $stmt->fetch()['total'];

// Récupère les fournitures
$query = "
    SELECT id, reference, designation, conditionnement, quantite_stock, seuil_alerte, commande_en_cours
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
    'data' => array_map(function($supply) {
        return [
            'id'                => (int) $supply['id'],
            'reference'         => $supply['reference'],
            'designation'       => $supply['designation'],
            'conditionnement'   => $supply['conditionnement'],
            'quantite_stock'    => (int) $supply['quantite_stock'],
            'seuil_alerte'      => $supply['seuil_alerte'] ? (int) $supply['seuil_alerte'] : null,
            'commande_en_cours' => (bool) $supply['commande_en_cours'],
        ];
    }, $supplies),
    'pagination' => [
        'page'       => $page,
        'limit'      => $limit,
        'total'      => $total,
        'pages'      => ceil($total / $limit),
    ],
], 200);
