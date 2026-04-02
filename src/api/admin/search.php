<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../../config/helpers.php';

send_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Méthode non autorisée'], 405);
}

// Vérifie le token
$token = get_bearer_token();
if (!$token) {
    json_response(['error' => 'Token manquant'], 401);
}

$payload = jwt_verify($token);
if (!$payload) {
    json_response(['error' => 'Token invalide ou expiré'], 401);
}

// Vérifie que l'utilisateur est admin
if (!is_admin($payload)) {
    json_response(['error' => 'Accès refusé - admin requis'], 403);
}

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$role = isset($_GET['role']) ? trim($_GET['role']) : '';
$actif = isset($_GET['actif']) ? $_GET['actif'] : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(5, (int)$_GET['limit'])) : 20;

if ($search === '' && $role === '' && $actif === '') {
    json_response(['error' => 'Au moins un critère de recherche est requis'], 400);
}

$pdo = getDB();

// Construction de la requête de recherche
$where = [];
$params = [];

if ($search !== '') {
    $searchPattern = '%' . $search . '%';
    $where[] = '(nom LIKE ? OR prenom LIKE ? OR login LIKE ?)';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

if ($role !== '') {
    $where[] = 'role = ?';
    $params[] = $role;
}

if ($actif !== '') {
    $where[] = 'actif = ?';
    $params[] = $actif === 'true' ? 1 : 0;
}

$whereClause = implode(' AND ', $where);

// Compte le total
$stmt = $pdo->prepare('SELECT COUNT(*) as total FROM UTILISATEUR WHERE ' . $whereClause);
$stmt->execute($params);
$total = $stmt->fetch()['total'];

$offset = ($page - 1) * $limit;

// Récupère les résultats
$params_with_limit = array_merge($params, [$limit, $offset]);
$stmt = $pdo->prepare('
    SELECT id, nom, prenom, login, role, actif, date_derniere_connexion
    FROM UTILISATEUR
    WHERE ' . $whereClause . '
    ORDER BY nom, prenom ASC
    LIMIT ? OFFSET ?
');
$stmt->execute($params_with_limit);
$users = $stmt->fetchAll();

json_response([
    'data' => array_map(function($user) {
        return [
            'id'                      => (int) $user['id'],
            'nom'                     => $user['nom'],
            'prenom'                  => $user['prenom'],
            'login'                   => $user['login'],
            'role'                    => $user['role'],
            'actif'                   => (bool) $user['actif'],
            'date_derniere_connexion' => $user['date_derniere_connexion'],
        ];
    }, $users),
    'pagination' => [
        'page'       => $page,
        'limit'      => $limit,
        'total'      => $total,
        'pages'      => ceil($total / $limit),
    ],
], 200);
