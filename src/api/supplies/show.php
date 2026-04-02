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

$supply_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$supply_id) {
    json_response(['error' => 'ID fourniture requis'], 400);
}

$pdo = getDB();

// Récupère la fourniture
$stmt = $pdo->prepare('
    SELECT id, reference, designation, conditionnement, quantite_stock, seuil_alerte, commande_en_cours
    FROM FOURNITURE
    WHERE id = ?
    LIMIT 1
');
$stmt->execute([$supply_id]);
$supply = $stmt->fetch();

if (!$supply) {
    json_response(['error' => 'Fourniture introuvable'], 404);
}

// Récupère également les peremptions associées
$stmt = $pdo->prepare('
    SELECT id, numero_lot, date_peremption, commentaire, actif
    FROM PEREMPTION
    WHERE fourniture_id = ? AND actif = 1
    ORDER BY date_peremption ASC
');
$stmt->execute([$supply_id]);
$peremptions = $stmt->fetchAll();

json_response([
    'data' => [
        'id'                => (int) $supply['id'],
        'reference'         => $supply['reference'],
        'designation'       => $supply['designation'],
        'conditionnement'   => $supply['conditionnement'],
        'quantite_stock'    => (int) $supply['quantite_stock'],
        'seuil_alerte'      => $supply['seuil_alerte'] ? (int) $supply['seuil_alerte'] : null,
        'commande_en_cours' => (bool) $supply['commande_en_cours'],
        'peremptions'       => array_map(function($p) {
            return [
                'id'               => (int) $p['id'],
                'numero_lot'       => $p['numero_lot'],
                'date_peremption'  => $p['date_peremption'],
                'commentaire'      => $p['commentaire'],
            ];
        }, $peremptions),
    ],
], 200);
