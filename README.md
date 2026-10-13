# PharmaStock

Application web de gestion de stock pour pharmacie : produits, entrées et sorties de stock, suivi des péremptions par lot, inventaires physiques et gestion des utilisateurs. Installable comme application mobile (PWA).

## Fonctionnalités

- **Tableau de bord** : ruptures, stocks bas, commandes en cours et péremptions des deux prochains mois.
- **Produits** : liste paginée avec recherche, fiche détaillée, création, modification.
- **Désactivation** : une fourniture inactive n'accepte plus de mouvement et n'entre plus dans les nouveaux inventaires. Rien n'est supprimé : son historique reste consultable et elle peut être réactivée.
- **Mouvements** : entrées et sorties avec motif, historique filtrable par période, type et produit.
- **Péremptions** : lots et dates de péremption par produit.
- **Codes-barres** : génération et scan des codes produits.
- **Inventaires** : saisie produit par produit des quantités comptées, calcul des écarts, validation qui corrige le stock. Les produits défilent selon l'« ordre d'inventaire » de leur fiche article. Ceux sans ordre passent en dernier, par référence. Voir [src/views/inventaires/README.md](src/views/inventaires/README.md).
- **Utilisateurs** : création, modification, activation et désactivation des comptes par un administrateur. Il n'y a pas d'inscription libre.

## Stack technique

| Couche | Technologie |
|---|---|
| Serveur | PHP 8.2 + Apache (image `php:8.2-apache`), sans framework |
| Base de données | MySQL / MariaDB via PDO |
| Front | HTML statique, JavaScript vanilla, Bootstrap 5.3, Font Awesome 6.4 (CDN) |
| Authentification | JWT HS256 (implémentation maison), valable 1 heure |

Il n'y a ni étape de build, ni dépendance Composer ou npm.

## Installation

### 1. Base de données

Importer le schéma dans une base MySQL/MariaDB :

```bash
mysql -u <user> -p <base> < src/db.sql
```

Le schéma contient un trigger, `after_mouvement_stock_insert`, qui met à jour `FOURNITURE.quantite_stock` à chaque mouvement. Il doit être importé avec les tables.

### 2. Variables d'environnement

Créer un fichier `.env` à la racine (non versionné) :

```dotenv
APP_DOMAIN=pharmastock.example.com
DB_DOMAIN=<hôte de la base>
DB_NAME=<nom de la base>
DB_USER=<utilisateur>
DB_PASS=<mot de passe>
JWT_SECRET=<chaîne aléatoire longue>
```

Le code PHP lit `APP_DB_HOST`, `APP_DB_NAME`, `APP_DB_USER`, `APP_DB_PASS` et `JWT_SECRET`. Le fichier `docker-compose.yml` fait la correspondance pour la base.

> ⚠️ `JWT_SECRET` doit aussi être transmis au conteneur, en ajoutant `JWT_SECRET: ${JWT_SECRET}` dans la section `environment` de `docker-compose.yml`. Sans cela, les jetons sont signés avec une clé vide.

### 3. Lancement

```bash
docker compose up -d --build
```

Le conteneur monte `src/` sur `/var/www/html`. Il rejoint deux réseaux Docker externes, qui doivent exister au préalable : `proxy` (reverse proxy avec `VIRTUAL_HOST` / `LETSENCRYPT_HOST`) et `dbnet` (accès à la base).

### 4. Premier administrateur

Aucun compte n'existe à l'import. Créer le premier administrateur directement en base, avec un mot de passe haché en bcrypt :

```bash
php -r 'echo password_hash("motdepasse", PASSWORD_BCRYPT), "\n";'
```

```sql
INSERT INTO UTILISATEUR (nom, prenom, login, mot_de_passe, role, actif)
VALUES ('Admin', 'Admin', 'admin', '<hash>', 'ADMIN', 1);
```

Les comptes suivants se créent depuis la page « Utilisateurs ».

## Rôles

| Rôle | Droits |
|---|---|
| `ADMIN` | Accès complet, gestion des utilisateurs, suppression de produits |
| `UTILISATEUR` | Gestion du stock, mouvements, inventaires |
| `VISITEUR` | Prévu pour la consultation seule. L'API ne restreint pas encore ce rôle : il a les mêmes droits que `UTILISATEUR`. |

Les rôles sont stockés en majuscules. L'API accepte n'importe quelle casse en entrée et la normalise.

## Structure du projet

```
src/
├── index.html              Page de connexion
├── db.sql                  Schéma de la base (tables + trigger)
├── manifest.json, service-worker.js   PWA
├── config/
│   ├── db.php              Connexion PDO
│   ├── jwt.php             Création et vérification des JWT
│   └── helpers.php         CORS, réponses JSON, rôles
├── api/                    Endpoints REST (un fichier par endpoint)
│   ├── login.php
│   ├── user/
│   ├── supplies/
│   ├── inventaires/
│   └── admin/
├── views/                  Pages de l'application
│   ├── dashboard.html, profile.html
│   ├── supplies/           list, view, entry, exit, history, generate-barcode
│   ├── inventaires/        history, create, session, summary
│   └── admin/users.html
└── assets/
    ├── css/style.css
    └── js/                 sidebar.js (menu commun), notifications.js
```

## API

Toutes les routes, sauf `login.php`, exigent l'en-tête `Authorization: Bearer <jwt>`. Les corps de requête et les réponses sont en JSON. Les erreurs ont la forme `{"error": "message"}`.

### Authentification et profil

| Méthode | Route | Description |
|---|---|---|
| POST | `/api/login.php` | Connexion. Corps : `login`, `password`. Retourne `token` et `user`. |
| GET | `/api/user/me.php` | Profil de l'utilisateur connecté |
| PUT | `/api/user/update-profile.php` | Modifie `nom`, `prenom` |
| PUT | `/api/user/change-password.php` | Corps : `ancien_mot_de_passe`, `nouveau_mot_de_passe` |
| — | `/api/user/register.php` | Désactivé (403) : les comptes sont créés par un admin |

### Produits et stock

| Méthode | Route | Description |
|---|---|---|
| GET | `/api/supplies/list.php` | Liste paginée. Query : `page`, `limit`, `search`, `statut` (`actifs` par défaut, `inactifs`, `tous`) |
| GET | `/api/supplies/show.php?id=` | Détail d'un produit avec ses péremptions |
| POST | `/api/supplies/create.php` | Création. La référence `PHxxx` est générée automatiquement |
| PUT | `/api/supplies/update.php?id=` | Modification partielle, dont `actif` pour désactiver ou réactiver et `ordre` (entier ≥ 0 ou `null`) pour l'ordre d'inventaire |
| DELETE | `/api/supplies/delete.php?id=` | Suppression définitive avec tout l'historique (admin). Non utilisée par l'interface : préférer la désactivation |
| GET | `/api/supplies/mouvement.php?id=` | Derniers mouvements d'un produit et statistiques |
| POST | `/api/supplies/mouvement.php?id=` | Nouveau mouvement. Corps : `type` (`ENTREE`/`SORTIE`), `quantite`, `motif`, `date_mouvement` |
| GET | `/api/supplies/mouvements.php` | Historique global. Query : `page`, `limit`, `search`, `type`, `date_debut`, `date_fin` |
| GET, POST, DELETE | `/api/supplies/peremption.php?id=` | Lots d'un produit. Suppression avec `&peremption_id=` |
| GET | `/api/supplies/peremptions-prochaines.php?mois=2` | Lots qui périment dans les N prochains mois |

Détails et exemples : [src/api/supplies/README.md](src/api/supplies/README.md).

Un mouvement sur une fourniture inactive est refusé (409). Une sortie supérieure au stock disponible est refusée. Un mouvement dont le motif contient « commande » et « reçu » clôt la commande en cours du produit.

### Inventaires

| Méthode | Route | Description |
|---|---|---|
| POST | `/api/inventaires/create.php` | Crée un inventaire et une ligne par produit |
| GET | `/api/inventaires/list.php` | 50 derniers inventaires avec statistiques |
| GET | `/api/inventaires/get-lines.php?id=` | Lignes d'un inventaire |
| POST | `/api/inventaires/save-line.php` | Corps : `line_id`, `quantite_physique`, `commentaire` |
| POST | `/api/inventaires/validate.php` | Corps : `inventaire_id`. Applique les écarts au stock |

### Administration (rôle `ADMIN`)

| Méthode | Route | Description |
|---|---|---|
| GET | `/api/admin/users.php` | Liste. Query : `page`, `limit`, `search`, `role`, `actif` |
| POST | `/api/admin/users.php` | Création : `nom`, `prenom`, `login`, `mot_de_passe`, `role` |
| PUT | `/api/admin/users.php?id=` | Modification partielle, dont `role` et `actif` |
| DELETE | `/api/admin/users.php?id=` | Suppression |
| POST | `/api/admin/create-user.php` | Création (ancienne route, équivalente au POST ci-dessus) |
| PATCH | `/api/admin/role.php?id=` | Changement de rôle. Corps : `role` |
| PATCH | `/api/admin/toggle-status.php?id=` | Corps : `action` (`activate`/`deactivate`) |
| GET | `/api/admin/search.php` | Recherche. Query : `q`, `role`, `actif`, `page`, `limit` |

Un administrateur ne peut ni changer son propre rôle, ni se désactiver, ni supprimer son propre compte.

### Exemple

```bash
TOKEN=$(curl -s -X POST https://<domaine>/api/login.php \
  -H "Content-Type: application/json" \
  -d '{"login":"admin","password":"motdepasse"}' | jq -r .token)

curl -s "https://<domaine>/api/supplies/list.php?page=1&limit=20" \
  -H "Authorization: Bearer $TOKEN"
```

## Côté client

- Après connexion, le jeton est stocké dans `localStorage.jwt` et l'utilisateur dans `localStorage.user`.
- Chaque page vérifie la présence du jeton et renvoie vers `index.html` s'il manque ou s'il est refusé par l'API.
- Le menu latéral est généré par `src/assets/js/sidebar.js` à partir d'une seule liste d'entrées. Chaque page contient seulement `<aside class="sidebar" id="sidebar"></aside>` suivi du script.
- L'entrée « Utilisateurs » n'est affichée qu'aux administrateurs. Ce masquage est visuel : l'API refuse de toute façon les routes admin aux autres rôles.
