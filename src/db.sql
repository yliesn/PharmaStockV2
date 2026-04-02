--
-- Base de données : `ph-preprod`
--

-- --------------------------------------------------------

--
-- Structure de la table `APPROBATION`
--

CREATE TABLE `APPROBATION` (
  `id` int(11) NOT NULL,
  `supply_id` int(11) NOT NULL,
  `quantite` int(11) NOT NULL,
  `motif` text DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `date_validation` datetime DEFAULT NULL,
  `statut` enum('EN_ATTENTE','APPROUVEE','REFUSEE') DEFAULT 'EN_ATTENTE',
  `traite_par` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `FEATURE_TOGGLES`
--

CREATE TABLE `FEATURE_TOGGLES` (
  `id` int(11) NOT NULL,
  `feature_key` varchar(100) NOT NULL,
  `label` varchar(255) NOT NULL,
  `value` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `FEATURE_TOGGLES`
--

INSERT INTO `FEATURE_TOGGLES` (`id`, `feature_key`, `label`, `value`, `description`, `updated_at`) VALUES
(1, 'enable_barcode_scanner', 'Activer le scanner de codes-barres', 1, 'Permet l accès à la fonctionnalité de scan', '2025-12-26 23:13:29'),
(2, 'enable_bulk_import', 'Activer import en lot', 0, 'Permet l import en masse des fournitures', '2025-10-27 09:31:58'),
(4, 'enable_dark_mode', 'Mode sombre', 0, 'Active la fonctionnalité du mode sombre', '2025-12-21 13:14:59'),
(5, 'enable_approvals', 'Activer les approbations', 1, 'Active le système de demandes d approbation pour les sorties de stock', '2025-11-17 13:03:29'),
(6, 'enable_inventory', 'Activer linventaire', 1, 'Permet d activer ou désactiver la fonctionnalité d inventaire dans l application', '2025-11-17 12:39:30');


CREATE TABLE `FOURNITURE` (
  `id` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `conditionnement` varchar(255) DEFAULT NULL,
  `quantite_stock` int(11) NOT NULL DEFAULT 0,
  `seuil_alerte` int(11) DEFAULT NULL,
  `commande_en_cours` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Structure de la table `INVENTAIRE`
--

CREATE TABLE `INVENTAIRE` (
  `id` int(11) NOT NULL,
  `date_inventaire` datetime NOT NULL DEFAULT current_timestamp(),
  `utilisateur_id` int(11) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `corrigee` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `INVENTAIRE_LIGNE`
--

CREATE TABLE `INVENTAIRE_LIGNE` (
  `id` int(11) NOT NULL,
  `inventaire_id` int(11) NOT NULL,
  `fourniture_id` int(11) NOT NULL,
  `quantite_theorique` int(11) NOT NULL,
  `quantite_physique` int(11) NOT NULL,
  `ecart` int(11) GENERATED ALWAYS AS (`quantite_physique` - `quantite_theorique`) STORED,
  `commentaire` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `LOGIN_TOKEN`
--

CREATE TABLE `LOGIN_TOKEN` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expire_at` datetime NOT NULL,
  `used` tinyint(4) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Structure de la table `MOUVEMENT_STOCK`
--

CREATE TABLE `MOUVEMENT_STOCK` (
  `id` int(11) NOT NULL,
  `date_mouvement` date NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `type` enum('ENTREE','SORTIE') NOT NULL,
  `quantite` int(11) NOT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `id_fourniture` int(11) NOT NULL,
  `id_utilisateur` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Déclencheurs `MOUVEMENT_STOCK`
--
DELIMITER $$
CREATE TRIGGER `after_mouvement_stock_insert` AFTER INSERT ON `MOUVEMENT_STOCK` FOR EACH ROW BEGIN
    IF NEW.type = 'ENTREE' THEN
        UPDATE FOURNITURE 
        SET quantite_stock = quantite_stock + NEW.quantite 
        WHERE id = NEW.id_fourniture;
    ELSEIF NEW.type = 'SORTIE' THEN
        UPDATE FOURNITURE 
        SET quantite_stock = quantite_stock - NEW.quantite 
        WHERE id = NEW.id_fourniture;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `PEREMPTION`
--

CREATE TABLE `PEREMPTION` (
  `id` int(11) NOT NULL,
  `fourniture_id` int(11) NOT NULL,
  `numero_lot` varchar(100) NOT NULL,
  `date_peremption` date NOT NULL,
  `commentaire` text DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `UTILISATEUR`
--

CREATE TABLE `UTILISATEUR` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `login` varchar(50) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `date_derniere_connexion` date DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `APPROBATION`
--
ALTER TABLE `APPROBATION`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supply_id` (`supply_id`),
  ADD KEY `traite_par` (`traite_par`);

--
-- Index pour la table `FEATURE_TOGGLES`
--
ALTER TABLE `FEATURE_TOGGLES`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `feature_key` (`feature_key`);

--
-- Index pour la table `FOURNITURE`
--
ALTER TABLE `FOURNITURE`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_fourniture_reference` (`reference`),
  ADD KEY `idx_fourniture_reference` (`reference`);

--
-- Index pour la table `INVENTAIRE`
--
ALTER TABLE `INVENTAIRE`
  ADD PRIMARY KEY (`id`),
  ADD KEY `utilisateur_id` (`utilisateur_id`);

--
-- Index pour la table `INVENTAIRE_LIGNE`
--
ALTER TABLE `INVENTAIRE_LIGNE`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventaire_id` (`inventaire_id`),
  ADD KEY `fourniture_id` (`fourniture_id`);

--
-- Index pour la table `LOGIN_TOKEN`
--
ALTER TABLE `LOGIN_TOKEN`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `MOUVEMENT_STOCK`
--
ALTER TABLE `MOUVEMENT_STOCK`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_mouvement_fourniture` (`id_fourniture`),
  ADD KEY `fk_mouvement_utilisateur` (`id_utilisateur`),
  ADD KEY `idx_mouvement_date` (`date_mouvement`),
  ADD KEY `idx_mouvement_type` (`type`);

--
-- Index pour la table `PEREMPTION`
--
ALTER TABLE `PEREMPTION`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_fourniture_lot` (`fourniture_id`,`numero_lot`);

--
-- Index pour la table `UTILISATEUR`
--
ALTER TABLE `UTILISATEUR`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_utilisateur_login` (`login`);


--
-- Contraintes pour la table `APPROBATION`
--
ALTER TABLE `APPROBATION`
  ADD CONSTRAINT `APPROBATION_ibfk_1` FOREIGN KEY (`supply_id`) REFERENCES `FOURNITURE` (`id`),
  ADD CONSTRAINT `APPROBATION_ibfk_2` FOREIGN KEY (`traite_par`) REFERENCES `UTILISATEUR` (`id`);

--
-- Contraintes pour la table `INVENTAIRE`
--
ALTER TABLE `INVENTAIRE`
  ADD CONSTRAINT `INVENTAIRE_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `UTILISATEUR` (`id`);

--
-- Contraintes pour la table `INVENTAIRE_LIGNE`
--
ALTER TABLE `INVENTAIRE_LIGNE`
  ADD CONSTRAINT `INVENTAIRE_LIGNE_ibfk_1` FOREIGN KEY (`inventaire_id`) REFERENCES `INVENTAIRE` (`id`),
  ADD CONSTRAINT `INVENTAIRE_LIGNE_ibfk_2` FOREIGN KEY (`fourniture_id`) REFERENCES `FOURNITURE` (`id`);

--
-- Contraintes pour la table `LOGIN_TOKEN`
--
ALTER TABLE `LOGIN_TOKEN`
  ADD CONSTRAINT `LOGIN_TOKEN_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `UTILISATEUR` (`id`);

--
-- Contraintes pour la table `MOUVEMENT_STOCK`
--
ALTER TABLE `MOUVEMENT_STOCK`
  ADD CONSTRAINT `fk_mouvement_fourniture` FOREIGN KEY (`id_fourniture`) REFERENCES `FOURNITURE` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mouvement_utilisateur` FOREIGN KEY (`id_utilisateur`) REFERENCES `UTILISATEUR` (`id`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `PEREMPTION`
--
ALTER TABLE `PEREMPTION`
  ADD CONSTRAINT `fk_peremption_fourniture` FOREIGN KEY (`fourniture_id`) REFERENCES `FOURNITURE` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;
