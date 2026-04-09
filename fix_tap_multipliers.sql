-- Script de vérification et de correction des limites de taps pour les boosters
-- Exécuter ce script dans votre base de données pour corriger le problème des utilisateurs avec booster dragon

-- 1. Vérifier les données actuelles
SELECT * FROM tap_multipliers ORDER BY coefficient DESC;

-- 2. Insérer les données manquantes pour les boosters haute performance
-- Si les enregistrements n'existent pas, les insérer
INSERT IGNORE INTO tap_multipliers (coefficient, required_taps, created_at, updated_at) VALUES
(1, 5000, NOW(), NOW()),
(2, 10000, NOW(), NOW()),
(5, 20000, NOW(), NOW()),
(10, 25000, NOW(), NOW()),
(20, 30000, NOW(), NOW()),
(50, 35000, NOW(), NOW()),
(100, 38000, NOW(), NOW()),
(200, 39000, NOW(), NOW()),
(500, 39500, NOW(), NOW()),
(1000, 39800, NOW(), NOW()),
(2000, 39900, NOW(), NOW()),
(5000, 39950, NOW(), NOW()),
(10000, 39980, NOW(), NOW()),
(20000, 39990, NOW(), NOW()),
(40000, 40000, NOW(), NOW());

-- 3. Vérifier après insertion
SELECT * FROM tap_multipliers ORDER BY coefficient DESC;

-- 4. Nettoyer le cache pour tous les utilisateurs (optionnel)
-- Si vous utilisez Redis ou un autre système de cache, vous pouvez nettoyer tous les caches de taps
-- FLUSHDB;  -- Décommentez cette ligne si vous utilisez Redis et voulez tout nettoyer

-- 5. Vérifier les utilisateurs actuellement bloqués avec un booster actif
SELECT
    u.id,
    u.name,
    u.email,
    ub.coefficient as booster_coefficient,
    u.tapped_out_at,
    t.required_taps
FROM users u
LEFT JOIN user_boosters ub ON u.id = ub.user_id AND ub.expires_at > NOW()
LEFT JOIN tap_multipliers t ON ub.coefficient = t.coefficient
WHERE u.tapped_out_at = CURDATE()
AND ub.coefficient > 1
ORDER BY ub.coefficient DESC;