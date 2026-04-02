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

$supply_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$supply_id) {
    json_response(['error' => 'ID fourniture requis'], 400);
}

$pdo = getDB();

// Vérifie que la fourniture existe
$stmt = $pdo->prepare('SELECT id FROM FOURNITURE WHERE id = ? LIMIT 1');
$stmt->execute([$supply_id]);
if (!$stmt->fetch()) {
    json_response(['error' => 'Fourniture introuvable'], 404);
}

// === TRAITEMENT GET ===
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Récupère le paramètre limit (défaut: 5)
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
    if ($limit < 1) $limit = 5;
    
    // Récupère les mouvements de la fourniture (limités)
    $stmt = $pdo->prepare('
        SELECT 
            ms.id,
            ms.date_mouvement,
            ms.type,
            ms.quantite,
            ms.motif,
            ms.id_utilisateur,
            u.prenom,
            u.nom
        FROM MOUVEMENT_STOCK ms
        LEFT JOIN UTILISATEUR u ON ms.id_utilisateur = u.id
        WHERE ms.id_fourniture = ?
        ORDER BY ms.date_mouvement DESC
        LIMIT ?
    ');
    $stmt->bindValue(1, $supply_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $mouvements = $stmt->fetchAll();

    // Récupère les stats
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as total_mouvements,
            SUM(CASE WHEN type = "ENTREE" THEN quantite ELSE 0 END) as total_entrees,
            SUM(CASE WHEN type = "SORTIE" THEN quantite ELSE 0 END) as total_sorties,
            MAX(date_mouvement) as dernier_mouvement
        FROM MOUVEMENT_STOCK
        WHERE id_fourniture = ?
    ');
    $stmt->execute([$supply_id]);
    $stats = $stmt->fetch();

    $mouvements_formatted = array_map(function($m) {
        return [
            'id'             => (int) $m['id'],
            'date_mouvement' => $m['date_mouvement'],
            'type'           => $m['type'],
            'quantite'       => (int) $m['quantite'],
            'motif'          => $m['motif'],
            'utilisateur_id' => $m['id_utilisateur'] ? (int) $m['id_utilisateur'] : null,
            'utilisateur'    => $m['id_utilisateur'] ? sprintf('%s %s', $m['prenom'], $m['nom']) : 'Système',
        ];
    }, $mouvements);

    json_response([
        'data' => $mouvements_formatted,
        'stats' => [
            'total_mouvements' => (int) ($stats['total_mouvements'] ?? 0),
            'total_entrees'    => (int) ($stats['total_entrees'] ?? 0),
            'total_sorties'    => (int) ($stats['total_sorties'] ?? 0),
            'dernier_mouvement' => $stats['dernier_mouvement'] ?? null,
        ],
    ], 200);
}

// === TRAITEMENT POST ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Méthode non autorisée'], 405);
}

$body = json_decode(file_get_contents('php://input'), true);

// Valide les champs requis
$type = isset($body['type']) ? strtoupper(trim($body['type'])) : '';
$quantite = isset($body['quantite']) ? (int)$body['quantite'] : 0;
$motif = isset($body['motif']) ? trim($body['motif']) : '';

if (!$type) {
    json_response(['error' => 'Type de mouvement requis (ENTREE ou SORTIE)'], 400);
}

if (!in_array($type, ['ENTREE', 'SORTIE'])) {
    json_response(['error' => 'Type invalide. Doit être ENTREE ou SORTIE'], 400);
}

if ($quantite <= 0) {
    json_response(['error' => 'Quantité doit être > 0'], 400);
}

// Récupère la fourniture
$stmt = $pdo->prepare('SELECT quantite_stock FROM FOURNITURE WHERE id = ? LIMIT 1');
$stmt->execute([$supply_id]);
$supply = $stmt->fetch();

// Vérifie qu'on ne peut pas faire une sortie si pas assez de stock
if ($type === 'SORTIE' && $supply['quantite_stock'] < $quantite) {
    json_response(['error' => 'Quantité insuffisante en stock'], 400);
}

$date_mouvement = isset($body['date_mouvement']) ? trim($body['date_mouvement']) : date('Y-m-d');

// Valide la date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_mouvement)) {
    json_response(['error' => 'Format de date invalide (YYYY-MM-DD)'], 400);
}

try {
    // Enregistre le mouvement
    $stmt = $pdo->prepare('
        INSERT INTO MOUVEMENT_STOCK (date_mouvement, type, quantite, motif, id_fourniture, id_utilisateur)
        VALUES (?, ?, ?, ?, ?, ?)
    ');

    $stmt->execute([
        $date_mouvement,
        $type,
        $quantite,
        $motif ?: null,
        $supply_id,
        $payload['sub'], // ID de l'utilisateur depuis le token
    ]);

    $mouvement_id = $pdo->lastInsertId();

    // Récupère la fourniture mise à jour
    $stmt = $pdo->prepare('SELECT quantite_stock FROM FOURNITURE WHERE id = ? LIMIT 1');
    $stmt->execute([$supply_id]);
    $updated_supply = $stmt->fetch();

    json_response([
        'message' => 'Mouvement enregistré avec succès',
        'data' => [
            'id'               => (int) $mouvement_id,
            'type'             => $type,
            'quantite'         => $quantite,
            'motif'            => $motif ?: null,
            'date_mouvement'   => $date_mouvement,
            'fourniture_id'    => (int) $supply_id,
            'utilisateur_id'   => (int) $payload['sub'],
            'nouvelle_quantite' => (int) $updated_supply['quantite_stock'],
        ],
    ], 201);
} catch (PDOException $e) {
    json_response(['error' => 'Erreur lors de l\'enregistrement du mouvement'], 500);
}
