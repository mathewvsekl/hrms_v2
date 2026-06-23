-- Patch to upgrade the existing audit_logs table for comprehensive action logging

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `module` varchar(100) DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` varchar(100) DEFAULT NULL,
  `old_values` longtext DEFAULT NULL,
  `new_values` longtext DEFAULT NULL,
  `payload` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at_utc` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_module` (`module`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add new columns if the table already exists
ALTER TABLE `audit_logs` 
ADD COLUMN IF NOT EXISTS `role_id` int(11) DEFAULT NULL AFTER `user_id`,
ADD COLUMN IF NOT EXISTS `module` varchar(100) DEFAULT NULL AFTER `action`,
ADD COLUMN IF NOT EXISTS `payload` longtext DEFAULT NULL AFTER `new_values`;
