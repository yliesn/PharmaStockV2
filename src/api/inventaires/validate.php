<?php  
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';
require_once __DIR__ . '/../../config/helpers.php';

// Vérifie le token
$token = get_bearer_token();
if (!$token) {
    json_response(['error' => 'Token manquant'], 401);
}

$payload = jwt_verify($token);
if (!$payload) {
    json_response(['error' => 'Token invalide ou expiré'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Méthode non autorisée'], 405);
}

$db = getDB();

// Récupérer les données POST
$data = json_decode(file_get_contents('php://input'), true);
$inventaire_id = $data['inventaire_id'] ?? null;

if (!$inventaire_id) {
    json_response(['error' => 'ID inventaire manquant'], 400);
}

// ⚠️ idéalement : récupérer depuis le JWT et non le POST
$user_id = $data['user_id'];

try {

    $db->beginTransaction();

    // 🔒 Vérifier si déjà corrigé
    $checkStmt = $db->prepare("SELECT corrigee FROM INVENTAIRE WHERE id = ?");
    $checkStmt->execute([$inventaire_id]);
    $inventaireCheck = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$inventaireCheck) {
        throw new Exception("Inventaire introuvable");
    }

    if ((int)$inventaireCheck['corrigee'] === 1) {
        $db->rollBack();
        json_response([
            'error' => 'Inventaire déjà corrigé'
        ], 409); // Conflict
    }

    // Récupérer les lignes de l'inventaire
    $query = "SELECT il.id, il.fourniture_id, il.quantite_theorique, il.quantite_physique, il.ecart
              FROM INVENTAIRE_LIGNE il
              WHERE il.inventaire_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$inventaire_id]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Préparer insertion mouvement
    $movementStmt = $db->prepare("
        INSERT INTO MOUVEMENT_STOCK 
        (date_mouvement, type, quantite, motif, id_fourniture, id_utilisateur)
        VALUES (CURDATE(), ?, ?, ?, ?, ?)
    ");

    // Parcours des lignes
    foreach ($lines as $line) {

        $ecart = (int)$line['ecart'];

        if ($ecart === 0) {
            continue;
        }

        $type = $ecart > 0 ? 'ENTREE' : 'SORTIE';
        $quantite = abs($ecart);

        $motif = "Inventaire #{$inventaire_id} (théo: {$line['quantite_theorique']} / phys: {$line['quantite_physique']})";

        $movementStmt->execute([
            $type,
            $quantite,
            $motif,
            $line['fourniture_id'],
            $user_id
        ]);
    }

    // Marquer l'inventaire comme corrigé
    $invStmt = $db->prepare("UPDATE INVENTAIRE SET corrigee = 1 WHERE id = ?");
    $invStmt->execute([$inventaire_id]);

    // Récupérer l'inventaire mis à jour
    $invSelectStmt = $db->prepare("SELECT * FROM INVENTAIRE WHERE id = ?");
    $invSelectStmt->execute([$inventaire_id]);
    $inventaire = $invSelectStmt->fetch(PDO::FETCH_ASSOC);

    // Calculer les statistiques d'écarts
    $statsQuery = "SELECT 
                    COUNT(*) as total_lignes,
                    SUM(CASE WHEN ecart > 0 THEN ecart ELSE 0 END) as surplus,
                    SUM(CASE WHEN ecart < 0 THEN ABS(ecart) ELSE 0 END) as manques,
                    SUM(CASE WHEN ecart != 0 THEN 1 ELSE 0 END) as lignes_ecart
                   FROM INVENTAIRE_LIGNE
                   WHERE inventaire_id = ?";
    
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute([$inventaire_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    $db->commit();

    json_response([
        'success' => true,
        'inventaire' => $inventaire,
        'stats' => $stats
    ], 200);

} catch (Exception $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    json_response([
        'error' => 'Erreur serveur',
        'details' => $e->getMessage()
    ], 500);
}
?>