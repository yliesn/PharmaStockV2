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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Méthode non autorisée'], 405);
}

$db = getDB();

try {
    // Récupérer les inventaires avec statistiques
    $query = "SELECT 
                i.id,
                i.date_inventaire,
                i.commentaire,
                i.corrigee,
                u.prenom,
                u.nom,
                COUNT(il.id) as total_lignes,
                SUM(CASE WHEN il.ecart != 0 THEN 1 ELSE 0 END) as lignes_ecart,
                SUM(CASE WHEN il.ecart > 0 THEN il.ecart ELSE 0 END) as surplus_total,
                SUM(CASE WHEN il.ecart < 0 THEN ABS(il.ecart) ELSE 0 END) as manques_total
              FROM INVENTAIRE i
              JOIN UTILISATEUR u ON i.utilisateur_id = u.id
              LEFT JOIN INVENTAIRE_LIGNE il ON i.id = il.inventaire_id
              GROUP BY i.id
              ORDER BY i.date_inventaire DESC
              LIMIT 50";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $inventaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'inventaires' => $inventaires,
        'total' => count($inventaires)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
