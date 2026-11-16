USE mini_network_monitor;

SET @auth_version_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'auth_version'
);
SET @auth_version_migration = IF(
  @auth_version_exists = 0,
  'ALTER TABLE users ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER password_hash',
  'SELECT 1'
);
PREPARE auth_version_statement FROM @auth_version_migration;
EXECUTE auth_version_statement;
DEALLOCATE PREPARE auth_version_statement;
