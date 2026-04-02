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

$pdo = getDB();

$supply_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$supply_id) {
    json_response(['error' => 'ID fourniture requis'], 400);
}

// Vérifie que la fourniture existe
$stmt = $pdo->prepare('SELECT id FROM FOURNITURE WHERE id = ? LIMIT 1');
$stmt->execute([$supply_id]);
if (!$stmt->fetch()) {
    json_response(['error' => 'Fourniture introuvable'], 404);
}

// GET - Lister les peremptions d'une fourniture
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('
        SELECT id, numero_lot, date_peremption, commentaire, actif
        FROM PEREMPTION
        WHERE fourniture_id = ?
        ORDER BY date_peremption ASC
    ');
    $stmt->execute([$supply_id]);
    $peremptions = $stmt->fetchAll();

    json_response([
        'data' => array_map(function($p) {
            return [
                'id'               => (int) $p['id'],
                'numero_lot'       => $p['numero_lot'],
                'date_peremption'  => $p['date_peremption'],
                'commentaire'      => $p['commentaire'],
                'actif'            => (bool) $p['actif'],
            ];
        }, $peremptions),
    ], 200);
}

// POST - Ajouter une peremption
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    $numero_lot = isset($body['numero_lot']) ? trim($body['numero_lot']) : '';
    $date_peremption = isset($body['date_peremption']) ? trim($body['date_peremption']) : '';
    $commentaire = isset($body['commentaire']) ? trim($body['commentaire']) : null;

    if (!$numero_lot) {
        json_response(['error' => 'Numéro de lot requis'], 400);
    }

    if (!$date_peremption || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_peremption)) {
        json_response(['error' => 'Date de péremption invalide (YYYY-MM-DD)'], 400);
    }

    // Vérifie que le lot n'existe pas déjà
    $stmt = $pdo->prepare('
        SELECT id FROM PEREMPTION
        WHERE fourniture_id = ? AND numero_lot = ?
        LIMIT 1
    ');
    $stmt->execute([$supply_id, $numero_lot]);
    if ($stmt->fetch()) {
        json_response(['error' => 'Ce lot existe déjà pour cette fourniture'], 409);
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO PEREMPTION (fourniture_id, numero_lot, date_peremption, commentaire, actif)
            VALUES (?, ?, ?, ?, 1)
        ');

        $stmt->execute([
            $supply_id,
            $numero_lot,
            $date_peremption,
            $commentaire,
        ]);

        $id = $pdo->lastInsertId();

        json_response([
            'message' => 'Péremption ajoutée avec succès',
            'data' => [
                'id'               => (int) $id,
                'fourniture_id'    => (int) $supply_id,
                'numero_lot'       => $numero_lot,
                'date_peremption'  => $date_peremption,
                'commentaire'      => $commentaire,
                'actif'            => true,
            ],
        ], 201);
    } catch (PDOException $e) {
        json_response(['error' => 'Erreur lors de l\'ajout de la péremption'], 500);
    }
}

// DELETE - Supprimer une peremption
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $peremption_id = isset($_GET['peremption_id']) ? (int)$_GET['peremption_id'] : null;
    if (!$peremption_id) {
        json_response(['error' => 'ID péremption requis'], 400);
    }

    $stmt = $pdo->prepare('SELECT id FROM PEREMPTION WHERE id = ? AND fourniture_id = ? LIMIT 1');
    $stmt->execute([$peremption_id, $supply_id]);
    if (!$stmt->fetch()) {
        json_response(['error' => 'Péremption introuvable'], 404);
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM PEREMPTION WHERE id = ?');
        $stmt->execute([$peremption_id]);

        json_response([
            'message' => 'Péremption supprimée avec succès',
        ], 200);
    } catch (PDOException $e) {
        json_response(['error' => 'Erreur lors de la suppression de la péremption'], 500);
    }
}

json_response(['error' => 'Méthode non autorisée'], 405);
