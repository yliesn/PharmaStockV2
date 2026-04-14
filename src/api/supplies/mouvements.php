<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../../config/helpers.php';

send_cors();

$token = get_bearer_token();
if (!$token) json_response(['error' => 'Token manquant'], 401);

$payload = jwt_verify($token);
if (!$payload) json_response(['error' => 'Token invalide ou expiré'], 401);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Méthode non autorisée'], 405);
}

$pdo = getDB();

$page       = isset($_GET['page'])   ? max(1, (int)$_GET['page'])               : 1;
$limit      = isset($_GET['limit'])  ? min(100, max(5, (int)$_GET['limit']))     : 20;
$offset     = ($page - 1) * $limit;
$type       = isset($_GET['type'])   ? strtoupper(trim($_GET['type']))           : '';
$search     = isset($_GET['search']) ? trim($_GET['search'])                     : '';
$date_debut = isset($_GET['date_debut']) ? trim($_GET['date_debut'])             : '';
$date_fin   = isset($_GET['date_fin'])   ? trim($_GET['date_fin'])               : '';

$where  = [];
$params = [];

if ($type === 'ENTREE' || $type === 'SORTIE') {
    $where[]  = 'ms.type = ?';
    $params[] = $type;
}

if ($search !== '') {
    $where[]  = '(f.designation LIKE ? OR f.reference LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?)';
    $s        = '%' . $search . '%';
    $params   = array_merge($params, [$s, $s, $s, $s]);
}

if ($date_debut !== '') {
    $where[]  = 'ms.date_mouvement >= ?';
    $params[] = $date_debut;
}

if ($date_fin !== '') {
    $where[]  = 'ms.date_mouvement <= ?';
    $params[] = $date_fin;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM MOUVEMENT_STOCK ms
    JOIN FOURNITURE f  ON ms.id_fourniture  = f.id
    JOIN UTILISATEUR u ON ms.id_utilisateur = u.id
    $whereSQL
");
$countStmt->execute($params);
$total = (int) $countStmt->fetch()['total'];

$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare("
    SELECT
        ms.id,
        ms.date_mouvement,
        ms.date_creation,
        ms.type,
        ms.quantite,
        ms.motif,
        f.id          AS fourniture_id,
        f.reference,
        f.designation,
        u.id          AS utilisateur_id,
        u.nom,
        u.prenom
    FROM MOUVEMENT_STOCK ms
    JOIN FOURNITURE f  ON ms.id_fourniture  = f.id
    JOIN UTILISATEUR u ON ms.id_utilisateur = u.id
    $whereSQL
    ORDER BY ms.date_mouvement DESC, ms.id DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

json_response([
    'data' => array_map(function($r) {
        return [
            'id'             => (int) $r['id'],
            'date_mouvement' => $r['date_mouvement'],
            'date_creation'  => $r['date_creation'],
            'type'           => $r['type'],
            'quantite'       => (int) $r['quantite'],
            'motif'          => $r['motif'],
            'fourniture' => [
                'id'          => (int) $r['fourniture_id'],
                'reference'   => $r['reference'],
                'designation' => $r['designation'],
            ],
            'utilisateur' => [
                'id'     => (int) $r['utilisateur_id'],
                'nom'    => $r['nom'],
                'prenom' => $r['prenom'],
            ],
        ];
    }, $rows),
    'pagination' => [
        'page'  => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => (int) ceil($total / $limit),
    ],
]);