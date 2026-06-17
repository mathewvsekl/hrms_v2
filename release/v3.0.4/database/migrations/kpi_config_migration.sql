-- KPI Configuration Table
-- Requirement: Allow HR/Managers to pre-define KRAs/KPIs before a cycle starts

CREATE TABLE IF NOT EXISTS `employee_kpi_configs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `kpi_name` VARCHAR(255) NOT NULL COMMENT 'KRA / Objective Name',
    `target_description` TEXT NULL COMMENT 'Detailed target or goal description',
    `weightage` DECIMAL(5, 2) DEFAULT 0.00 COMMENT 'Optional weightage percentage',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit Log for KPI Changes (Optional but high-quality)
CREATE TABLE IF NOT EXISTS `kpi_config_audit` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `kpi_config_id` INT NOT NULL,
    `changed_by_id` INT NOT NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`kpi_config_id`) REFERENCES `employee_kpi_configs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`changed_by_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
