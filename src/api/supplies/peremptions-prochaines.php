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

$mois = isset($_GET['mois']) ? max(1, min(12, (int)$_GET['mois'])) : 2;

$pdo = getDB();

$stmt = $pdo->prepare('
    SELECT
        p.id,
        p.numero_lot,
        p.date_peremption,
        p.commentaire,
        f.id   AS fourniture_id,
        f.reference,
        f.designation,
        f.quantite_stock,
        DATEDIFF(p.date_peremption, CURDATE()) AS jours_restants
    FROM PEREMPTION p
    JOIN FOURNITURE f ON p.fourniture_id = f.id
    WHERE
        p.actif = 1
        AND p.date_peremption BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? MONTH)
        OR p.date_peremption <CURDATE()
    ORDER BY p.date_peremption ASC
');
$stmt->execute([$mois]);
$rows = $stmt->fetchAll();

json_response([
    'data' => array_map(function($r) {
        return [
            'id'             => (int) $r['id'],
            'numero_lot'     => $r['numero_lot'],
            'date_peremption'=> $r['date_peremption'],
            'commentaire'    => $r['commentaire'],
            'jours_restants' => (int) $r['jours_restants'],
            'fourniture'     => [
                'id'            => (int) $r['fourniture_id'],
                'reference'     => $r['reference'],
                'designation'   => $r['designation'],
                'quantite_stock'=> (int) $r['quantite_stock'],
            ],
        ];
    }, $rows),
    'meta' => [
        'mois'  => $mois,
        'total' => count($rows),
    ],
]);