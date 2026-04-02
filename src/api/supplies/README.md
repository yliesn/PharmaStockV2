# API Supplies (Fournitures)

Documentation complète des API pour la gestion des fournitures (supplies).

## Authentification

Tous les endpoints requièrent un token JWT dans le header Authorization:
```
Authorization: Bearer YOUR_JWT_TOKEN
```

## Endpoints

### 1. Lister les fournitures

**GET** `/api/supplies/list.php`

**Paramètres de query:**
- `page` (int): Numéro de page (défaut: 1)
- `limit` (int): Nombre de résultats par page (défaut: 20, max: 100)
- `search` (string): Rechercher par référence ou désignation

**Réponse (200):**
```json
{
  "data": [
    {
      "id": 1,
      "reference": "REF001",
      "designation": "Paracétamol 500mg",
      "conditionnement": "Boîte x 20",
      "quantite_stock": 150,
      "seuil_alerte": 50,
      "commande_en_cours": false
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 100,
    "pages": 5
  }
}
```

### 2. Détail d'une fourniture

**GET** `/api/supplies/show.php?id=1`

**Paramètres de query:**
- `id` (int): ID de la fourniture **[REQUIS]**

**Réponse (200):**
```json
{
  "data": {
    "id": 1,
    "reference": "REF001",
    "designation": "Paracétamol 500mg",
    "conditionnement": "Boîte x 20",
    "quantite_stock": 150,
    "seuil_alerte": 50,
    "commande_en_cours": false,
    "peremptions": [
      {
        "id": 5,
        "numero_lot": "LOT123456",
        "date_peremption": "2026-12-31",
        "commentaire": "Lot normal"
      }
    ]
  }
}
```

### 3. Créer une fourniture

**POST** `/api/supplies/create.php`

**Body (JSON):**
```json
{
  "reference": "REF001",
  "designation": "Paracétamol 500mg",
  "conditionnement": "Boîte x 20",
  "quantite_stock": 0,
  "seuil_alerte": 50,
  "commande_en_cours": false
}
```

**Champs:**
- `reference` (string) **[REQUIS]** - Doit être unique
- `designation` (string) **[REQUIS]**
- `conditionnement` (string) - Optionnel
- `quantite_stock` (int) - Défaut: 0
- `seuil_alerte` (int) - Optionnel
- `commande_en_cours` (boolean) - Défaut: false

**Réponse (201):**
```json
{
  "message": "Fourniture créée avec succès",
  "data": {
    "id": 1,
    "reference": "REF001",
    "designation": "Paracétamol 500mg",
    "conditionnement": "Boîte x 20",
    "quantite_stock": 0,
    "seuil_alerte": 50,
    "commande_en_cours": false
  }
}
```

### 4. Mettre à jour une fourniture

**PUT** `/api/supplies/update.php?id=1`

**Paramètres de query:**
- `id` (int): ID de la fourniture **[REQUIS]**

**Body (JSON):** (Tous les champs optionnels)
```json
{
  "reference": "REF001_NEW",
  "designation": "Paracétamol 500mg modifié",
  "conditionnement": "Boîte x 30",
  "quantite_stock": 100,
  "seuil_alerte": 40,
  "commande_en_cours": true
}
```

**Réponse (200):**
```json
{
  "message": "Fourniture mise à jour avec succès",
  "data": {
    "id": 1,
    "reference": "REF001_NEW",
    "designation": "Paracétamol 500mg modifié",
    "conditionnement": "Boîte x 30",
    "quantite_stock": 100,
    "seuil_alerte": 40,
    "commande_en_cours": true
  }
}
```

### 5. Supprimer une fourniture

**DELETE** `/api/supplies/delete.php?id=1`

**Paramètres de query:**
- `id` (int): ID de la fourniture **[REQUIS]**

**Permissions:** Admin uniquement

**Réponse (200):**
```json
{
  "message": "Fourniture supprimée avec succès",
  "reference": "REF001"
}
```

### 6. Enregistrer un mouvement de stock

**POST** `/api/supplies/mouvement.php?id=1`

**Paramètres de query:**
- `id` (int): ID de la fourniture **[REQUIS]**

**Body (JSON):**
```json
{
  "type": "ENTREE",
  "quantite": 50,
  "motif": "Achat auprès du fournisseur",
  "date_mouvement": "2026-04-01"
}
```

**Champs:**
- `type` (string) **[REQUIS]** - `ENTREE` ou `SORTIE`
- `quantite` (int) **[REQUIS]** - Doit être > 0
- `motif` (string) - Optionnel
- `date_mouvement` (string) - Format YYYY-MM-DD (défaut: aujourd'hui)

**Validations:**
- Pour une SORTIE: la quantité ne doit pas dépasser le stock actuel
- Quantité doit être positive

**Réponse (201):**
```json
{
  "message": "Mouvement enregistré avec succès",
  "data": {
    "id": 42,
    "type": "ENTREE",
    "quantite": 50,
    "motif": "Achat auprès du fournisseur",
    "date_mouvement": "2026-04-01",
    "fourniture_id": 1,
    "utilisateur_id": 5,
    "nouvelle_quantite": 200
  }
}
```

### 7. Lister les péremptions d'une fourniture

**GET** `/api/supplies/peremption.php?id=1`

**Paramètres de query:**
- `id` (int): ID de la fourniture **[REQUIS]**

**Réponse (200):**
```json
{
  "data": [
    {
      "id": 5,
      "numero_lot": "LOT123456",
      "date_peremption": "2026-12-31",
      "commentaire": "Lot normal",
      "actif": true
    }
  ]
}
```

### 8. Ajouter une péremption

**POST** `/api/supplies/peremption.php?id=1`

**Paramètres de query:**
- `id` (int): ID de la fourniture **[REQUIS]**

**Body (JSON):**
```json
{
  "numero_lot": "LOT123456",
  "date_peremption": "2026-12-31",
  "commentaire": "Lot normal"
}
```

**Champs:**
- `numero_lot` (string) **[REQUIS]** - Doit être unique par fourniture
- `date_peremption` (string) **[REQUIS]** - Format YYYY-MM-DD
- `commentaire` (string) - Optionnel

**Réponse (201):**
```json
{
  "message": "Péremption ajoutée avec succès",
  "data": {
    "id": 5,
    "fourniture_id": 1,
    "numero_lot": "LOT123456",
    "date_peremption": "2026-12-31",
    "commentaire": "Lot normal",
    "actif": true
  }
}
```

### 9. Supprimer une péremption

**DELETE** `/api/supplies/peremption.php?id=1&peremption_id=5`

**Paramètres de query:**
- `id` (int): ID de la fourniture **[REQUIS]**
- `peremption_id` (int): ID de la péremption **[REQUIS]**

**Réponse (200):**
```json
{
  "message": "Péremption supprimée avec succès"
}
```

## Codes d'erreur

| Code | Erreur | Description |
|------|--------|-------------|
| 400 | Bad Request | Paramètres manquants ou invalides |
| 401 | Unauthorized | Token manquant ou invalide |
| 403 | Forbidden | Accès refusé (admin requuis pour certaines actions) |
| 404 | Not Found | Ressource introuvable |
| 405 | Method Not Allowed | Méthode HTTP non supportée |
| 409 | Conflict | Ressource déjà existe (référence ou lot dupliqué) |
| 500 | Internal Server Error | Erreur serveur |

## Exemple d'utilisation complète

```bash
# 1. Login pour obtenir un token
curl -X POST http://localhost:8080/api/login.php \
  -H "Content-Type: application/json" \
  -d '{"login":"user@example.com","password":"password"}'

# Réponse contient: {"token":"eyJ..."}
TOKEN="eyJ..."

# 2. Créer une fourniture
curl -X POST http://localhost:8080/api/supplies/create.php \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "reference": "REF001",
    "designation": "Paracétamol 500mg",
    "quantite_stock": 100,
    "seuil_alerte": 20
  }'

# 3. Enregistrer une entrée de stock
curl -X POST http://localhost:8080/api/supplies/mouvement.php?id=1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "ENTREE",
    "quantite": 50,
    "motif": "Achat fournisseur"
  }'

# 4. Ajouter une péremption
curl -X POST http://localhost:8080/api/supplies/peremption.php?id=1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "numero_lot": "LOT123456",
    "date_peremption": "2026-12-31"
  }'

# 5. Lister les fournitures
curl -X GET "http://localhost:8080/api/supplies/list.php?page=1&limit=20" \
  -H "Authorization: Bearer $TOKEN"
```
