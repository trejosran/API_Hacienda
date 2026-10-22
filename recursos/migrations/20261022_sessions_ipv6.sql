-- Session IP binding must preserve full IPv4 and IPv6 addresses.
-- Existing installations: run once against the API database before private-network login.
-- Widening preserves existing session data and does not change authentication checks.
ALTER TABLE `sessions`
  MODIFY COLUMN `ip` VARCHAR(45) COLLATE utf8mb4_unicode_ci NOT NULL;
