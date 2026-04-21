# Fonctionnalité d'Inventaire - PharmaStock

## 📋 Vue d'ensemble

La fonctionnalité d'inventaire permet de réaliser des inventaires complets du stock en comparant les quantités théoriques (système) avec les quantités physiques (comptées).

## 🔄 Flux Principal

### 1. **Créer un Inventaire** (`/views/inventaires/create.html`)
- Formulaire simple avec :
  - Date (auto-remplie avec date actuelle)
  - Utilisateur (auto-rempli)
  - Commentaire optionnel
- Crée une entrée dans la table `INVENTAIRE`
- Crée automatiquement les lignes dans `INVENTAIRE_LIGNE` (une par produit)

### 2. **Saisir les Quantités Physiques** (`/views/inventaires/session.html`)
- Affiche les produits **un par un** avec des cartes
- Pour chaque produit :
  - Référence et désignation
  - Quantité théorique (readonly)
  - **Champ de saisie** pour quantité physique
  - Notes optionnelles
  - Calcul automatique de l'écart
- Navigation : Précédent / Suivant / Finir
- Indicateur de progression en barre + numéro
- **Sauvegarde auto** à la validation

### 3. **Vérifier le Résumé** (`/views/inventaires/summary.html`)
- Affiche les statistiques :
  - Total produits
  - Surplus total
  - Manques total
  - Nombre de produits avec écart
- Liste des produits avec écart :
  - Référence, désignation
  - Quantité théorique, physique
  - Écart calculé
  - Notes
- Boutons :
  - Modifier la saisie (retour)
  - Valider et appliquer les corrections

### 4. **Historique des Inventaires** (`/views/inventaires/history.html`)
- Liste tous les inventaires :
  - Date et utilisateur
  - Statut (En cours / Validé)
  - Statistiques (produits, écarts, surplus, manques)
- Clic sur un inventaire :
  - Si en cours → redirect vers `session.html`
  - Si validé → redirect vers `summary.html` (en lecture seule)

## 🗄️ Structure Base de Données

### Table `INVENTAIRE`
```sql
CREATE TABLE INVENTAIRE (
  id INT PRIMARY KEY AUTO_INCREMENT,
  date_inventaire DATETIME,
  utilisateur_id INT (FK UTILISATEUR),
  commentaire TEXT,
  corrigee TINYINT (0 = en cours, 1 = validé)
)
```

### Table `INVENTAIRE_LIGNE`
```sql
CREATE TABLE INVENTAIRE_LIGNE (
  id INT PRIMARY KEY AUTO_INCREMENT,
  inventaire_id INT (FK INVENTAIRE),
  fourniture_id INT (FK FOURNITURE),
  quantite_theorique INT,
  quantite_physique INT,
  ecart INT (GENERATED AS quantite_physique - quantite_theorique),
  commentaire TEXT
)
```

## 🔌 APIs Disponibles

### `POST /api/inventaires/create.php`
- **Params** : `commentaire` (optionnel)
- **Retour** : `inventaire_id`, `total_lines`
- **Action** : Crée l'inventaire + toutes les lignes

### `GET /api/inventaires/get-lines.php?id={inventaire_id}`
- **Params** : `id` (inventaire_id)
- **Retour** : `lines[]`, `inventaire`, `total`
- **Action** : Récupère toutes les lignes d'un inventaire

### `POST /api/inventaires/save-line.php`
- **Params** : `line_id`, `quantite_physique`, `commentaire`
- **Retour** : `line[]` (mise à jour avec écart calculé)
- **Action** : Sauvegarde une ligne

### `POST /api/inventaires/validate.php`
- **Params** : `inventaire_id`
- **Retour** : `inventaire`, `stats`
- **Action** : 
  1. Met à jour `FOURNITURE.quantite_stock` pour chaque produit
  2. Marque l'inventaire comme `corrigee = 1`
  3. Retourne les statistiques

### `GET /api/inventaires/list.php`
- **Params** : aucun
- **Retour** : `inventaires[]` (50 derniers avec stats)
- **Action** : Récupère l'historique des inventaires

## 🎨 Fonctionnalités UI

✅ **Cartes progressives** : Un produit = une card  
✅ **Barre de progression** : Visuelle et textuelle  
✅ **Calcul d'écart en temps réel** : Affichage immédiat  
✅ **Badges de couleur** :
  - Vert = Conforme (écart = 0)
  - Bleu = Surplus (écart > 0)
  - Rouge = Manque (écart < 0)

✅ **Navigation fluide** : Précédent, Suivant, Validation progressive  
✅ **Sauvegarde rapide** : Chaque produit est sauvegardé avant le suivant  
✅ **Résumé détaillé** : Vue synthétique avant validation finale  
✅ **Historique complet** : Tous les inventaires passés avec détails  

## 🚀 Utilisation

1. **Accéder** à `/views/inventaires/history.html`
2. **Cliquer** sur "Créer un Inventaire"
3. **Ajouter** un commentaire optionnel
4. **Saisir** les quantités une par une
5. **Vérifier** le résumé
6. **Valider** pour appliquer les corrections

## ⚙️ Configuration

La fonctionnalité est contrôlée par un **feature toggle** :
```sql
UPDATE FEATURE_TOGGLES 
SET value = 1 
WHERE feature_key = 'enable_inventory'
```

## 📝 Notes

- Les écarts sont calculés **automatiquement**
- Les corrections sont appliquées **après validation**
- L'inventaire peut être **modifié** tant qu'il n'est pas validé
- L'**historique** conserve tous les inventaires
