-- HRMS V2.0.0 Complete Database Schema
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `appraisal_approvals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appraisal_id` int NOT NULL,
  `approver_id` int NOT NULL,
  `status` enum('pending','approved','returned') DEFAULT 'pending',
  `comment` text,
  `step_order` int DEFAULT '0',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `appraisal_id` (`appraisal_id`),
  KEY `approver_id` (`approver_id`),
  CONSTRAINT `appraisal_approvals_ibfk_1` FOREIGN KEY (`appraisal_id`) REFERENCES `employee_appraisals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appraisal_approvals_ibfk_2` FOREIGN KEY (`approver_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `appraisal_comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appraisal_id` int NOT NULL,
  `section` varchar(50) NOT NULL COMMENT 'e.g., Section_C_Challenges, Section_D_Recommendation',
  `author_id` int NOT NULL COMMENT 'Employee who wrote this comment (can be self, manager, or HR)',
  `comment_text` text NOT NULL,
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `appraisal_id` (`appraisal_id`),
  KEY `author_id` (`author_id`),
  CONSTRAINT `appraisal_comments_ibfk_1` FOREIGN KEY (`appraisal_id`) REFERENCES `employee_appraisals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appraisal_comments_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `appraisal_cycles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `frequency` varchar(50) DEFAULT 'Annual',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('draft','active','closed') DEFAULT 'draft',
  `selected_offices` json DEFAULT NULL,
  `employee_deadline` date DEFAULT NULL,
  `manager_deadline` date DEFAULT NULL,
  `hr_deadline` date DEFAULT NULL,
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `appraisal_ratings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appraisal_id` int NOT NULL,
  `kra_name` varchar(255) DEFAULT NULL COMMENT 'For dynamic KPIs in Section B',
  `achievements` text COMMENT 'Employee provided achievements',
  `question_id` int DEFAULT NULL COMMENT 'Maps to template_questions for predefined soft skills',
  `employee_rating` decimal(4,2) DEFAULT NULL,
  `manager_rating` decimal(4,2) DEFAULT NULL,
  `hr_adjusted_rating` decimal(4,2) DEFAULT NULL,
  `manager_comment` text,
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `appraisal_id` (`appraisal_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `appraisal_ratings_ibfk_1` FOREIGN KEY (`appraisal_id`) REFERENCES `employee_appraisals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appraisal_ratings_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `template_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `appraisal_system_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `category` varchar(50) DEFAULT 'general',
  `updated_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `appraisal_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` text,
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `asset_allocations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `asset_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `allocated_by_id` int DEFAULT NULL COMMENT 'User who performed the allocation',
  `allocation_date` date NOT NULL,
  `expected_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `status` enum('active','returned','overdue','lost') DEFAULT 'active',
  `remarks` text,
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`),
  KEY `employee_id` (`employee_id`),
  KEY `allocated_by_id` (`allocated_by_id`),
  CONSTRAINT `asset_allocations_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_allocations_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_allocations_ibfk_3` FOREIGN KEY (`allocated_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `assets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(150) NOT NULL COMMENT 'e.g., MacBook Pro 14',
  `category` enum('laptop','mobile','hardware','software','furniture','other') DEFAULT 'other',
  `serial_number` varchar(100) DEFAULT NULL,
  `model_number` varchar(100) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(15,2) DEFAULT NULL,
  `currency_code` varchar(3) DEFAULT 'KES',
  `status` enum('available','allocated','damaged','lost','retired') DEFAULT 'available',
  `remarks` text,
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial_number` (`serial_number`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `assets_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `attendance_audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `attendance_log_id` int NOT NULL,
  `changed_by_id` int NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `change_reason` text,
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `attendance_log_id` (`attendance_log_id`),
  KEY `changed_by_id` (`changed_by_id`),
  CONSTRAINT `attendance_audit_logs_ibfk_1` FOREIGN KEY (`attendance_log_id`) REFERENCES `attendance_logs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_audit_logs_ibfk_2` FOREIGN KEY (`changed_by_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `attendance_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `attendance_date` date NOT NULL COMMENT 'Master anchor for daily reporting',
  `check_in_utc` timestamp NULL DEFAULT NULL COMMENT 'Optional based on office.is_time_tracking_enabled',
  `check_out_utc` timestamp NULL DEFAULT NULL,
  `source` varchar(50) DEFAULT 'web' COMMENT 'Source of the log: web, manual, system_auto, etc',
  `status` varchar(50) DEFAULT 'present' COMMENT 'Master anchor for daily reporting',
  `leave_type_id` int DEFAULT NULL,
  `approval_status` varchar(50) DEFAULT 'approved' COMMENT 'Status for approval workflow',
  `submitted_by_id` int DEFAULT NULL,
  `approved_by_id` int DEFAULT NULL,
  `remarks` text,
  `metadata` json DEFAULT NULL COMMENT 'IP or Geolocation tracing data',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_default_applied` tinyint(1) DEFAULT '0',
  `is_manually_modified` tinyint(1) DEFAULT '0',
  `actor_type` enum('system','user') DEFAULT 'user',
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `submitted_by_id` (`submitted_by_id`),
  KEY `approved_by_id` (`approved_by_id`),
  KEY `fk_attendance_leave_type` (`leave_type_id`),
  CONSTRAINT `attendance_logs_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_logs_ibfk_2` FOREIGN KEY (`submitted_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_logs_ibfk_3` FOREIGN KEY (`approved_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_attendance_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `attendance_policies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `shift_start` time DEFAULT '08:00:00',
  `shift_end` time DEFAULT '17:00:00',
  `grace_period_mins` int DEFAULT '15',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `office_id` (`company_id`),
  CONSTRAINT `attendance_policies_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `companies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `country_id` int NOT NULL,
  `legal_entity_id` int DEFAULT NULL COMMENT 'Link to the legal entity for tax/pension compliance',
  `name` varchar(100) NOT NULL,
  `address` text,
  `contact_phone` varchar(30) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `timezone` varchar(50) NOT NULL,
  `attendance_mode` enum('time_based','status_based','standard','flexible','strict') DEFAULT 'standard',
  `is_time_tracking_enabled` tinyint(1) DEFAULT '0' COMMENT 'Super Admin toggle for strict clock-in/out vs macro daily status',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  PRIMARY KEY (`id`),
  KEY `country_id` (`country_id`),
  KEY `legal_entity_id` (`legal_entity_id`),
  CONSTRAINT `companies_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `companies_ibfk_2` FOREIGN KEY (`legal_entity_id`) REFERENCES `legal_entities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1003 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `company_custom_fields` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `field_key` varchar(50) NOT NULL COMMENT 'JSON key reference for custom_data',
  `field_name` varchar(100) NOT NULL,
  `field_type` enum('text','number','date','boolean','dropdown','json_array') NOT NULL,
  `field_options` json DEFAULT NULL COMMENT 'JSON array of options for dropdowns',
  `display_order` int DEFAULT '0',
  `is_required` tinyint(1) DEFAULT '0',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_comp_field` (`company_id`,`field_name`),
  CONSTRAINT `company_custom_fields_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `company_leave_policies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `leave_type_id` int NOT NULL,
  `default_days_per_year` decimal(5,2) NOT NULL,
  `carry_forward_allowed` tinyint(1) DEFAULT '0',
  `is_calendar_days` tinyint(1) DEFAULT '0' COMMENT 'If FALSE, ignores weekends during leave calculation',
  `weekend_definition` json DEFAULT NULL COMMENT 'Array like ["Saturday", "Sunday"] to localize logic per office',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `office_id` (`company_id`),
  KEY `leave_type_id` (`leave_type_id`),
  CONSTRAINT `company_leave_policies_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `company_leave_policies_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `countries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `iso_code` varchar(3) NOT NULL,
  `currency_code` varchar(3) NOT NULL,
  `default_timezone` varchar(50) NOT NULL,
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `department_kpi_requirements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `department_id` int NOT NULL,
  `min_kpis` int DEFAULT '3',
  `updated_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `department_kpi_requirements_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1' COMMENT 'Soft delete for historical integrity',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `designations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `department_id` int NOT NULL,
  `title` varchar(150) NOT NULL,
  `level` int DEFAULT '0' COMMENT 'Integer ranking for hierarchy tracking',
  `is_active` tinyint(1) DEFAULT '1' COMMENT 'Soft delete for historical integrity',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `designations_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `employee_appraisals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `manager_id` int DEFAULT NULL COMMENT 'Snapshot of reporting manager at creation time',
  `cycle_id` int NOT NULL,
  `template_id` int NOT NULL,
  `status` enum('draft','manager_review','hr_review','finalized') DEFAULT 'draft',
  `eligible_for_increment` tinyint(1) DEFAULT NULL COMMENT 'Determined by HR in Section E',
  `eligible_for_bonus` tinyint(1) DEFAULT NULL COMMENT 'Determined by HR in Section E',
  `final_rating` decimal(4,2) DEFAULT NULL,
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `manager_id` (`manager_id`),
  KEY `cycle_id` (`cycle_id`),
  KEY `template_id` (`template_id`),
  CONSTRAINT `employee_appraisals_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `employee_appraisals_ibfk_2` FOREIGN KEY (`manager_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_appraisals_ibfk_3` FOREIGN KEY (`cycle_id`) REFERENCES `appraisal_cycles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_appraisals_ibfk_4` FOREIGN KEY (`template_id`) REFERENCES `appraisal_templates` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `employee_companies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `company_id` int NOT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `deactivated_at_utc` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_emp_comp` (`employee_id`,`company_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `employee_companies_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_companies_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `employee_contracts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `contract_type` enum('permanent','fixed_term','probation') NOT NULL DEFAULT 'permanent',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL COMMENT 'NULL for permanent contracts',
  `probation_end_date` date DEFAULT NULL,
  `notes` text,
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `employee_contracts_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `employee_documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `document_name` varchar(150) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `document_type` enum('id_proof','contract','certificate','payslip','other') DEFAULT 'other',
  `uploaded_by_id` int DEFAULT NULL,
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `uploaded_by_id` (`uploaded_by_id`),
  CONSTRAINT `employee_documents_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_documents_ibfk_2` FOREIGN KEY (`uploaded_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `employee_kpi_configs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `kpi_name` varchar(255) NOT NULL COMMENT 'KRA / Objective Name',
  `target_description` text COMMENT 'Detailed target or goal description',
  `weightage` decimal(5,2) DEFAULT '0.00' COMMENT 'Optional weightage percentage',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `employee_kpi_configs_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `employees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_code` varchar(50) NOT NULL,
  `department_id` int DEFAULT NULL COMMENT 'Structural linkage',
  `designation_id` int DEFAULT NULL COMMENT 'Job title linkage',
  `reporting_manager_id` int DEFAULT NULL COMMENT 'Hierarchical superior, must be checked for circular dependencies',
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `tin_number` varchar(50) DEFAULT NULL COMMENT 'Tax Identification Number',
  `nssf_number` varchar(50) DEFAULT NULL COMMENT 'National Social Security Fund ID',
  `bank_account_no` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `profile_image_path` varchar(255) DEFAULT NULL,
  `employment_type` enum('full_time','part_time','contractor') NOT NULL DEFAULT 'full_time',
  `status` enum('active','inactive','onboarding','offboarding','pending_approval') DEFAULT 'active',
  `hire_date` date NOT NULL,
  `custom_data` json DEFAULT NULL COMMENT 'JSON object storing office-specific custom field values',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_code` (`employee_code`),
  UNIQUE KEY `email` (`email`),
  KEY `department_id` (`department_id`),
  KEY `designation_id` (`designation_id`),
  KEY `reporting_manager_id` (`reporting_manager_id`),
  CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_ibfk_3` FOREIGN KEY (`designation_id`) REFERENCES `designations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_ibfk_4` FOREIGN KEY (`reporting_manager_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1004 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `exchange_rates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `from_currency` varchar(3) NOT NULL,
  `to_currency` varchar(3) NOT NULL,
  `rate` decimal(15,6) NOT NULL,
  `effective_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rate_pair_date` (`from_currency`,`to_currency`,`effective_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `global_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `category` varchar(50) DEFAULT 'general',
  `updated_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `holidays` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `holiday_date` date NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_recurring` tinyint(1) DEFAULT '0' COMMENT 'If TRUE, occurs every year on this date',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `office_id` (`company_id`),
  CONSTRAINT `holidays_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `kpi_config_audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kpi_config_id` int NOT NULL,
  `changed_by_id` int NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `kpi_config_id` (`kpi_config_id`),
  KEY `changed_by_id` (`changed_by_id`),
  CONSTRAINT `kpi_config_audit_ibfk_1` FOREIGN KEY (`kpi_config_id`) REFERENCES `employee_kpi_configs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kpi_config_audit_ibfk_2` FOREIGN KEY (`changed_by_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `leave_balances` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `leave_type_id` int NOT NULL,
  `year` int NOT NULL,
  `allocated_days` decimal(5,2) NOT NULL,
  `used_days` decimal(5,2) DEFAULT '0.00',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `emp_leave_year` (`employee_id`,`leave_type_id`,`year`),
  KEY `leave_type_id` (`leave_type_id`),
  CONSTRAINT `leave_balances_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_balances_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `leave_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `leave_type_id` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` decimal(5,2) NOT NULL COMMENT 'Calculated post weekend exclusion if applicable',
  `status` enum('pending','approved','rejected','cancel_requested','cancelled') DEFAULT 'pending',
  `approved_by_id` int DEFAULT NULL COMMENT 'Manager who approved the request',
  `request_group_id` varchar(50) DEFAULT NULL,
  `manager_comment` text,
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `leave_type_id` (`leave_type_id`),
  KEY `approved_by_id` (`approved_by_id`),
  KEY `idx_request_group_id` (`request_group_id`),
  CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_requests_ibfk_3` FOREIGN KEY (`approved_by_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `leave_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT 'e.g., Annual, Sick, Maternity',
  `code` varchar(20) NOT NULL,
  `is_paid` tinyint(1) DEFAULT '1',
  `gender_restriction` enum('male','female','none') DEFAULT 'none',
  `color_code` varchar(7) DEFAULT '#6b7280',
  `is_at_work` tinyint(1) DEFAULT '0',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `legal_entities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `country_id` int NOT NULL,
  `name` varchar(150) NOT NULL,
  `registration_number` varchar(100) DEFAULT NULL,
  `tax_formula_config` json DEFAULT NULL COMMENT 'JSON structure defining the tax rules/formulas',
  `pension_formula_config` json DEFAULT NULL COMMENT 'JSON structure defining the pension rules/formulas',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  PRIMARY KEY (`id`),
  KEY `country_id` (`country_id`),
  CONSTRAINT `legal_entities_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'e.g., leave_request, appraisal_update, system_alert',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `data` json DEFAULT NULL COMMENT 'Store related IDs like { "leave_id": 123, "link": "/leave/123" }',
  `is_read` tinyint(1) DEFAULT '0',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at_utc` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_unread` (`user_id`,`is_read`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `office_attendance_configs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `config_date` date NOT NULL,
  `status` enum('present','absent','half_day','late','on_leave','public_holiday','weekend','training','on_site','work_from_home') NOT NULL,
  `remarks` text,
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_office_date` (`company_id`,`config_date`),
  CONSTRAINT `office_attendance_configs_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `office_attendance_status_definitions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `status_key` varchar(50) NOT NULL,
  `status_label` varchar(100) NOT NULL,
  `color_code` varchar(20) DEFAULT '#3b82f6',
  `is_default` tinyint(1) DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`,`status_key`),
  CONSTRAINT `office_attendance_status_definitions_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `office_weekly_schedules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `status` varchar(50) NOT NULL,
  `remarks` text,
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_day` (`company_id`,`day_of_week`),
  CONSTRAINT `office_weekly_schedules_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `payroll_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `payroll_run_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `currency_code` varchar(3) NOT NULL,
  `exchange_rate_to_base` decimal(10,4) DEFAULT '1.0000',
  `gross_amount` decimal(15,2) NOT NULL,
  `net_amount` decimal(15,2) NOT NULL,
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  PRIMARY KEY (`id`),
  KEY `payroll_run_id` (`payroll_run_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `payroll_records_ibfk_1` FOREIGN KEY (`payroll_run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_records_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `payroll_runs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `month` int NOT NULL,
  `year` int NOT NULL,
  `status` enum('draft','processing','completed','paid') DEFAULT 'draft',
  `processed_at_utc` timestamp NULL DEFAULT NULL COMMENT 'UTC Normalized',
  PRIMARY KEY (`id`),
  KEY `office_id` (`company_id`),
  CONSTRAINT `payroll_runs_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `public_holidays` (
  `id` int NOT NULL AUTO_INCREMENT,
  `country_id` int NOT NULL,
  `name` varchar(150) NOT NULL COMMENT 'e.g., Diwali, Independence Day, Eid Al Fitr',
  `holiday_date` date NOT NULL,
  `year` int NOT NULL,
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `country_id` (`country_id`),
  CONSTRAINT `public_holidays_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `role_permissions` (
  `role_id` int NOT NULL,
  `permission_id` int NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `salary_structures` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `base_salary` decimal(15,2) NOT NULL,
  `currency_code` varchar(3) NOT NULL,
  `effective_date` date NOT NULL,
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `salary_structures_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `template_questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL,
  `section` enum('B_KPI','B_SOFT_SKILL','C_SUMMARY','D_MANAGER','E_HR') NOT NULL,
  `question_text` text NOT NULL,
  `is_mandatory` tinyint(1) DEFAULT '1',
  `display_order` int DEFAULT '0',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`),
  CONSTRAINT `template_questions_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `appraisal_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `user_otps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `otp_code` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `is_used` tinyint(1) DEFAULT '0',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_otps_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `user_roles` (
  `user_id` int NOT NULL,
  `role_id` int NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int DEFAULT NULL COMMENT 'Maps the login credential to an active employee profile. Can be null for system-only Super Admins.',
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `api_token` varchar(255) DEFAULT NULL,
  `last_login_utc` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `api_token` (`api_token`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1004 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

