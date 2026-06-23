CREATE TABLE `appraisal_approval_matrices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `step_order` int NOT NULL,
  `role_required` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appraisal_approvals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appraisal_id` int NOT NULL,
  `approver_id` int NOT NULL,
  `status` enum('pending','approved','returned') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `comment` text COLLATE utf8mb4_general_ci,
  `step_order` int DEFAULT '0',
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `appraisal_id` (`appraisal_id`),
  KEY `approver_id` (`approver_id`),
  CONSTRAINT `appraisal_approvals_ibfk_1` FOREIGN KEY (`appraisal_id`) REFERENCES `employee_appraisals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appraisal_approvals_ibfk_2` FOREIGN KEY (`approver_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=431 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `appraisal_comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appraisal_id` int NOT NULL,
  `section` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'e.g., Section_C_Challenges, Section_D_Recommendation',
  `author_id` int NOT NULL COMMENT 'Employee who wrote this comment (can be self, manager, or HR)',
  `comment_text` text COLLATE utf8mb4_general_ci NOT NULL,
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `appraisal_id` (`appraisal_id`),
  KEY `author_id` (`author_id`),
  CONSTRAINT `appraisal_comments_ibfk_1` FOREIGN KEY (`appraisal_id`) REFERENCES `employee_appraisals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appraisal_comments_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `appraisal_cycle_landmarks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cycle_id` int NOT NULL,
  `employee_submission_deadline` date DEFAULT NULL,
  `manager_review_deadline` date DEFAULT NULL,
  `hr_review_deadline` date DEFAULT NULL,
  `management_approval_deadline` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cycle_id` (`cycle_id`),
  CONSTRAINT `appraisal_cycle_landmarks_ibfk_1` FOREIGN KEY (`cycle_id`) REFERENCES `appraisal_cycles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appraisal_cycles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL COMMENT 'e.g., Annual Appraisal 2025',
  `year` int DEFAULT NULL,
  `frequency` varchar(50) DEFAULT 'Annual',
  `period` varchar(50) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('draft','active','closed') DEFAULT 'draft',
  `selected_offices` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `employee_deadline` date DEFAULT NULL,
  `manager_deadline` date DEFAULT NULL,
  `hr_deadline` date DEFAULT NULL,
  `management_deadline` date DEFAULT NULL,
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `appraisal_hr_reviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appraisal_id` int NOT NULL,
  `soft_skills_post_mapping` decimal(5,2) DEFAULT NULL,
  `overall_kpi_post_calibration` int DEFAULT NULL,
  `hr_observations` text COLLATE utf8mb4_unicode_ci,
  `final_performance_rating` int DEFAULT NULL,
  `eligible_increment` tinyint(1) DEFAULT '0',
  `eligible_bonus` tinyint(1) DEFAULT '0',
  `special_notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `appraisal_id` (`appraisal_id`),
  CONSTRAINT `appraisal_hr_reviews_ibfk_1` FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appraisal_kpis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appraisal_id` int NOT NULL,
  `kra` text COLLATE utf8mb4_unicode_ci,
  `achievements` text COLLATE utf8mb4_unicode_ci,
  `employee_rating` int DEFAULT NULL,
  `manager_rating` int DEFAULT NULL,
  `manager_comments` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `appraisal_id` (`appraisal_id`),
  CONSTRAINT `appraisal_kpis_ibfk_1` FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appraisal_letters` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appraisal_id` int NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Draft','Published','Acknowledged') COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `old_salary` decimal(15,2) DEFAULT NULL,
  `new_salary` decimal(15,2) DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `appraisal_id` (`appraisal_id`),
  CONSTRAINT `appraisal_letters_ibfk_1` FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appraisal_manager_reviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appraisal_id` int NOT NULL,
  `overall_achievement` text COLLATE utf8mb4_unicode_ci,
  `final_overall_rating` int DEFAULT NULL,
  `recommendations` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `appraisal_id` (`appraisal_id`),
  CONSTRAINT `appraisal_manager_reviews_ibfk_1` FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appraisal_ratings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appraisal_id` int NOT NULL,
  `kra_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'For dynamic KPIs in Section B',
  `achievements` text COLLATE utf8mb4_general_ci COMMENT 'Employee provided achievements',
  `question_id` int DEFAULT NULL COMMENT 'Maps to template_questions for predefined soft skills',
  `employee_rating` decimal(4,2) DEFAULT NULL,
  `manager_rating` decimal(4,2) DEFAULT NULL,
  `hr_adjusted_rating` decimal(4,2) DEFAULT NULL,
  `manager_comment` text COLLATE utf8mb4_general_ci,
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `appraisal_id` (`appraisal_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `appraisal_ratings_ibfk_1` FOREIGN KEY (`appraisal_id`) REFERENCES `employee_appraisals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appraisal_ratings_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `template_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=423 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `appraisal_soft_skills_ratings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appraisal_id` int NOT NULL,
  `skill_id` int NOT NULL,
  `employee_rating` int DEFAULT NULL,
  `manager_rating` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `appraisal_id` (`appraisal_id`),
  KEY `skill_id` (`skill_id`),
  CONSTRAINT `appraisal_soft_skills_ratings_ibfk_1` FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appraisal_soft_skills_ratings_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `appraisal_template_soft_skills` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appraisal_summaries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appraisal_id` int NOT NULL,
  `overall_summary` text COLLATE utf8mb4_unicode_ci,
  `challenges_faced` text COLLATE utf8mb4_unicode_ci,
  `areas_of_improvement` text COLLATE utf8mb4_unicode_ci,
  `training_required` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `appraisal_id` (`appraisal_id`),
  CONSTRAINT `appraisal_summaries_ibfk_1` FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appraisal_system_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_general_ci,
  `category` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'general',
  `updated_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `appraisal_template_soft_skills` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL,
  `skill_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `rating_scale_max` int DEFAULT '10',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`),
  CONSTRAINT `appraisal_template_soft_skills_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `appraisal_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appraisal_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `min_kpis` int DEFAULT '3',
  `max_kpis` int DEFAULT '10',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `appraisal_workflow_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appraisal_id` int NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `performed_by` int NOT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `appraisal_id` (`appraisal_id`),
  CONSTRAINT `appraisal_workflow_logs_ibfk_1` FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appraisals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `cycle_id` int NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `overall_kpi_rating` decimal(3,2) DEFAULT NULL,
  `overall_soft_skills_rating` decimal(3,2) DEFAULT NULL,
  `final_rating` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `cycle_id` (`cycle_id`),
  KEY `employee_id` (`employee_id`),
  KEY `status` (`status`),
  CONSTRAINT `appraisals_ibfk_1` FOREIGN KEY (`cycle_id`) REFERENCES `appraisal_cycles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approval_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module` enum('leave','appraisal','attendance','onboarding') COLLATE utf8mb4_general_ci NOT NULL,
  `reference_id` int NOT NULL COMMENT 'ID from leave_requests, employee_appraisals, or attendance_logs',
  `actor_id` int NOT NULL COMMENT 'users.id of the person who performed the action',
  `action` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'submitted, approved, rejected, returned, cancelled, finalized',
  `comment` text COLLATE utf8mb4_general_ci,
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_module_ref` (`module`,`reference_id`),
  KEY `actor_id` (`actor_id`),
  CONSTRAINT `approval_history_ibfk_1` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `asset_allocations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `asset_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `allocated_by_id` int DEFAULT NULL COMMENT 'User who performed the allocation',
  `allocation_date` date NOT NULL,
  `expected_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `status` enum('active','returned','overdue','lost') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `remarks` text COLLATE utf8mb4_general_ci,
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `attachment` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`),
  KEY `employee_id` (`employee_id`),
  KEY `allocated_by_id` (`allocated_by_id`),
  CONSTRAINT `asset_allocations_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_allocations_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_allocations_ibfk_3` FOREIGN KEY (`allocated_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `assets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'e.g., MacBook Pro 14',
  `category` enum('laptop','mobile','hardware','software','furniture','other') COLLATE utf8mb4_general_ci DEFAULT 'other',
  `serial_number` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `model_number` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(15,2) DEFAULT NULL,
  `currency_code` varchar(3) COLLATE utf8mb4_general_ci DEFAULT 'KES',
  `status` enum('available','allocated','damaged','lost','retired') COLLATE utf8mb4_general_ci DEFAULT 'available',
  `remarks` text COLLATE utf8mb4_general_ci,
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `base_currency_cost` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial_number` (`serial_number`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `assets_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `attendance_audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `attendance_log_id` int NOT NULL,
  `changed_by_id` int NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `change_reason` text,
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `attendance_log_id` (`attendance_log_id`),
  KEY `changed_by_id` (`changed_by_id`),
  CONSTRAINT `attendance_audit_logs_ibfk_1` FOREIGN KEY (`attendance_log_id`) REFERENCES `attendance_logs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_audit_logs_ibfk_2` FOREIGN KEY (`changed_by_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2084 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `attendance_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `company_id` int NOT NULL,
  `attendance_date` date NOT NULL COMMENT 'Master anchor for daily reporting',
  `check_in_utc` timestamp NULL DEFAULT NULL,
  `check_out_utc` timestamp NULL DEFAULT NULL,
  `source` varchar(50) DEFAULT 'web',
  `status` varchar(50) DEFAULT 'present',
  `approval_status` varchar(50) DEFAULT 'approved',
  `submitted_by_id` int DEFAULT NULL,
  `approved_by_id` int DEFAULT NULL,
  `remarks` text,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'IP or Geolocation tracing data',
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_default_applied` tinyint(1) DEFAULT '0',
  `is_manually_modified` tinyint(1) DEFAULT '0',
  `actor_type` enum('system','user') DEFAULT 'user',
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `submitted_by_id` (`submitted_by_id`),
  KEY `approved_by_id` (`approved_by_id`),
  KEY `idx_attendance_company` (`company_id`),
  KEY `idx_attendance_date` (`attendance_date`),
  KEY `idx_attendance_emp_date` (`employee_id`,`attendance_date`),
  CONSTRAINT `attendance_logs_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `attendance_logs_ibfk_2` FOREIGN KEY (`submitted_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_logs_ibfk_3` FOREIGN KEY (`approved_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1805 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `attendance_policies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `shift_start` time DEFAULT '08:00:00',
  `shift_end` time DEFAULT '17:00:00',
  `grace_period_mins` int DEFAULT '15',
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `attendance_policies_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `companies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `country_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `contact_phone` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contact_email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `logo_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT 'default_logo.png',
  `timezone` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `attendance_mode` enum('time_based','status_based') COLLATE utf8mb4_general_ci DEFAULT 'time_based',
  `is_time_tracking_enabled` tinyint(1) DEFAULT '0' COMMENT 'Super Admin toggle for strict clock-in/out vs macro daily status',
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  `updated_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `country_id` (`country_id`),
  CONSTRAINT `companies_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `company_custom_fields` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `field_key` varchar(50) NOT NULL COMMENT 'JSON key reference for custom_data',
  `field_name` varchar(100) NOT NULL,
  `field_type` enum('text','number','date','boolean','dropdown','json_array') NOT NULL,
  `field_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'JSON array of options for dropdowns',
  `display_order` int DEFAULT '0',
  `is_required` tinyint(1) DEFAULT '0',
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `company_custom_fields_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `company_documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `document_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'Policy',
  `file_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `company_id` int DEFAULT NULL,
  `country_id` int DEFAULT NULL,
  `uploaded_by_id` int DEFAULT NULL,
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `country_id` (`country_id`),
  KEY `uploaded_by_id` (`uploaded_by_id`),
  CONSTRAINT `company_documents_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `company_documents_ibfk_2` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `company_documents_ibfk_3` FOREIGN KEY (`uploaded_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `company_leave_policies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `year` int NOT NULL DEFAULT '2026',
  `leave_type_id` int NOT NULL,
  `default_days_per_year` decimal(5,2) NOT NULL,
  `carry_forward_allowed` tinyint(1) DEFAULT '0',
  `is_calendar_days` tinyint(1) DEFAULT '0' COMMENT 'If FALSE, ignores weekends during leave calculation',
  `weekend_definition` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Array like ["Saturday", "Sunday"] to localize logic per company',
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `leave_type_id` (`leave_type_id`),
  CONSTRAINT `company_leave_policies_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `company_leave_policies_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `countries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `iso_code` varchar(3) NOT NULL,
  `currency_code` varchar(3) NOT NULL,
  `default_timezone` varchar(50) NOT NULL,
  `tax_formula_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'JSON structure defining the tax rules/formulas',
  `pension_formula_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'JSON structure defining the pension rules/formulas',
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `department_kpi_requirements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `department_id` int NOT NULL,
  `min_kpis` int DEFAULT '3',
  `updated_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `department_kpi_requirements_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Soft???delete flag (1 = active, 0 = inactive)',
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  `updated_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `designations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `department_id` int NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `level` int DEFAULT '0' COMMENT 'Integer ranking for hierarchy tracking',
  `is_active` tinyint(1) DEFAULT '1' COMMENT 'Soft delete for historical integrity',
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `designations_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `employee_appraisals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `manager_id` int DEFAULT NULL COMMENT 'Snapshot of reporting manager at creation time',
  `cycle_id` int NOT NULL,
  `template_id` int NOT NULL,
  `status` enum('draft','manager_review','hr_review','l1_review','l2_review','l3_review','hr_calibration','finalized','withdrawn','rejected') COLLATE utf8mb4_general_ci DEFAULT 'draft',
  `eligible_for_increment` tinyint(1) DEFAULT NULL COMMENT 'Determined by HR in Section E',
  `eligible_for_bonus` tinyint(1) DEFAULT NULL COMMENT 'Determined by HR in Section E',
  `final_rating` decimal(4,2) DEFAULT NULL,
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `manager_id` (`manager_id`),
  KEY `cycle_id` (`cycle_id`),
  KEY `template_id` (`template_id`),
  KEY `idx_appraisal_cycle_status` (`cycle_id`,`status`),
  CONSTRAINT `employee_appraisals_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `employee_appraisals_ibfk_2` FOREIGN KEY (`manager_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_appraisals_ibfk_3` FOREIGN KEY (`cycle_id`) REFERENCES `appraisal_cycles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_appraisals_ibfk_4` FOREIGN KEY (`template_id`) REFERENCES `appraisal_templates` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=245 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `employee_companies` (
  `employee_id` int NOT NULL,
  `company_id` int NOT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `include_in_payroll` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1' COMMENT 'Soft-deactivation flag; FALSE = historically deactivated, data preserved',
  `deactivated_at_utc` timestamp NULL DEFAULT NULL COMMENT 'UTC timestamp when this link was deactivated',
  PRIMARY KEY (`employee_id`,`company_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `employee_companies_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_companies_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `employee_contracts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `contract_type` enum('permanent','fixed_term','probation') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'permanent',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL COMMENT 'NULL for permanent contracts',
  `probation_end_date` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `employee_contracts_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `employee_documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `document_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `document_type` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'other',
  `expiry_date` date DEFAULT NULL,
  `uploaded_by_id` int DEFAULT NULL,
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `uploaded_by_id` (`uploaded_by_id`),
  CONSTRAINT `employee_documents_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_documents_ibfk_2` FOREIGN KEY (`uploaded_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `employee_kpi_configs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `kpi_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'KRA / Objective Name',
  `target_description` text COLLATE utf8mb4_general_ci COMMENT 'Detailed target or goal description',
  `weightage` decimal(5,2) DEFAULT '0.00' COMMENT 'Optional weightage percentage',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `employee_kpi_configs_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `employee_salary_components` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `component_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `effective_date` date NOT NULL,
  `currency_code` varchar(3) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'UGX',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `emp_comp_date_unique` (`employee_id`,`component_id`,`effective_date`),
  KEY `component_id` (`component_id`),
  CONSTRAINT `employee_salary_components_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_salary_components_ibfk_2` FOREIGN KEY (`component_id`) REFERENCES `payroll_components` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `employees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_code` varchar(50) NOT NULL,
  `department_id` int DEFAULT NULL COMMENT 'Structural linkage',
  `designation_id` int DEFAULT NULL COMMENT 'Job title linkage',
  `reporting_manager_id` int DEFAULT NULL COMMENT 'Hierarchical superior, must be checked for circular dependencies',
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `personal_email` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `personal_phone` varchar(30) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `bank_account_no` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `profile_image_path` varchar(255) DEFAULT NULL,
  `employment_type` enum('full_time','part_time','contractor') NOT NULL DEFAULT 'full_time',
  `job_description` text COMMENT 'Brief employment details/role description',
  `status` enum('active','inactive','onboarding','offboarding','pending_approval') DEFAULT 'active',
  `performance_rating` decimal(3,2) DEFAULT NULL,
  `hire_date` date NOT NULL,
  `custom_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'JSON object storing company-specific custom field values',
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_code` (`employee_code`),
  UNIQUE KEY `email` (`email`),
  KEY `department_id` (`department_id`),
  KEY `designation_id` (`designation_id`),
  KEY `reporting_manager_id` (`reporting_manager_id`),
  KEY `idx_employees_status` (`status`),
  KEY `idx_employees_dept` (`department_id`),
  KEY `idx_employees_desig` (`designation_id`),
  KEY `idx_employees_manager` (`reporting_manager_id`),
  CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`designation_id`) REFERENCES `designations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_ibfk_3` FOREIGN KEY (`reporting_manager_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `exchange_rates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `from_currency` varchar(3) COLLATE utf8mb4_general_ci NOT NULL,
  `to_currency` varchar(3) COLLATE utf8mb4_general_ci NOT NULL,
  `rate` decimal(15,6) NOT NULL,
  `effective_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rate_pair_date` (`from_currency`,`to_currency`,`effective_date`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `global_settings` (
  `setting_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_general_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'general',
  `updated_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `holidays` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `holiday_date` date NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `is_recurring` tinyint(1) DEFAULT '0' COMMENT 'If TRUE, occurs every year on this date',
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `idx_holiday_date` (`holiday_date`),
  CONSTRAINT `holidays_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `kpi_config_audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kpi_config_id` int NOT NULL,
  `changed_by_id` int NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `emp_leave_year` (`employee_id`,`leave_type_id`,`year`),
  KEY `leave_type_id` (`leave_type_id`),
  CONSTRAINT `leave_balances_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `leave_balances_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=310 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `leave_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `leave_type_id` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` decimal(5,2) NOT NULL COMMENT 'Calculated post weekend exclusion if applicable',
  `status` enum('draft','pending','approved','rejected','cancel_requested','cancelled') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `approved_by_id` int DEFAULT NULL COMMENT 'Manager who approved the request',
  `request_group_id` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `updated_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `manager_comment` text COLLATE utf8mb4_general_ci,
  `remarks` text COLLATE utf8mb4_general_ci,
  `attachment_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cancellation_reason` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `leave_type_id` (`leave_type_id`),
  KEY `approved_by_id` (`approved_by_id`),
  KEY `request_group_id` (`request_group_id`),
  KEY `idx_leave_emp_status` (`employee_id`,`status`),
  CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`),
  CONSTRAINT `leave_requests_ibfk_3` FOREIGN KEY (`approved_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `leave_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'e.g., Annual, Sick, Maternity',
  `code` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `display_code` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_paid` tinyint(1) DEFAULT '1',
  `gender_restriction` enum('male','female','none') COLLATE utf8mb4_general_ci DEFAULT 'none',
  `color_code` varchar(10) COLLATE utf8mb4_general_ci DEFAULT '#6b7280',
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_company_code` (`company_id`,`code`),
  UNIQUE KEY `unique_company_code` (`company_id`,`code`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'e.g., info, success, warning, error',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Associated metadata for notification actions',
  `is_read` tinyint(1) DEFAULT '0',
  `read_at_utc` timestamp NULL DEFAULT NULL,
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_notifications_is_read` (`is_read`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `office_attendance_configs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `config_date` date NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Dynamic status key matching office_attendance_status_definitions or system defaults',
  `remarks` text COLLATE utf8mb4_general_ci,
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_office_date` (`company_id`,`config_date`),
  CONSTRAINT `office_attendance_configs_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `office_attendance_status_definitions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `status_key` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'e.g., work_from_home, on_site',
  `status_label` varchar(100) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'e.g., Work From Home',
  `display_code` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `color_code` varchar(20) COLLATE utf8mb4_general_ci DEFAULT '#3b82f6',
  `is_default` tinyint(1) DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_status_key` (`company_id`,`status_key`),
  CONSTRAINT `office_attendance_status_definitions_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `office_weekly_schedules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') COLLATE utf8mb4_general_ci NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `remarks` text COLLATE utf8mb4_general_ci,
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_day` (`company_id`,`day_of_week`),
  CONSTRAINT `office_weekly_schedules_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `payroll_components` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `computation_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'FIXED',
  `value` decimal(10,2) NOT NULL DEFAULT '0.00',
  `formula` text COLLATE utf8mb4_general_ci,
  `company_id` int DEFAULT NULL,
  `country_id` int DEFAULT NULL,
  `is_statutory` tinyint(1) DEFAULT '0',
  `is_non_taxable` tinyint(1) DEFAULT '0',
  `is_income_tax` tinyint(1) DEFAULT '0',
  `round_off` tinyint(1) DEFAULT '0',
  `status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Active',
  `display_in_payslip` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `country_id` (`country_id`),
  CONSTRAINT `payroll_components_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_components_ibfk_2` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `payroll_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `company_id` int NOT NULL DEFAULT '1',
  `month` int NOT NULL,
  `year` int NOT NULL,
  `basic_pay` decimal(15,2) DEFAULT '0.00',
  `earnings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `commissions` decimal(15,2) DEFAULT '0.00',
  `other_earnings` decimal(15,2) DEFAULT '0.00',
  `gross_chargeable_income` decimal(15,2) DEFAULT '0.00',
  `paye_deduction` decimal(15,2) DEFAULT '0.00',
  `nssf_employee_deduction` decimal(15,2) DEFAULT '0.00',
  `nssf_employer_contribution` decimal(15,2) DEFAULT '0.00',
  `deductions_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `advance_deductions` decimal(15,2) DEFAULT '0.00',
  `net_pay` decimal(15,2) DEFAULT '0.00',
  `reporting_currency` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `exchange_rate` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Draft',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `payroll_records_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_records_chk_1` CHECK (json_valid(`earnings_json`)),
  CONSTRAINT `payroll_records_chk_2` CHECK (json_valid(`deductions_json`))
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `payroll_runs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `month` int NOT NULL,
  `year` int NOT NULL,
  `status` enum('draft','processing','completed','paid') COLLATE utf8mb4_general_ci DEFAULT 'draft',
  `processed_at_utc` timestamp NULL DEFAULT NULL COMMENT 'UTC Normalized',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `idx_payroll_period` (`year`,`month`,`status`),
  CONSTRAINT `payroll_runs_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `payslips` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `company_id` int DEFAULT NULL,
  `month` int NOT NULL,
  `year` int NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `uploaded_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=237 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `public_holidays` (
  `id` int NOT NULL AUTO_INCREMENT,
  `country_id` int NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'e.g., Diwali, Independence Day, Eid Al Fitr',
  `holiday_date` date NOT NULL,
  `year` int NOT NULL,
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `country_id` (`country_id`),
  KEY `idx_pub_holiday_date` (`holiday_date`),
  CONSTRAINT `public_holidays_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `role_permissions` (
  `role_id` int NOT NULL,
  `permission_id` int NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `normalized_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `base_role_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `fk_roles_base_role` (`base_role_id`),
  CONSTRAINT `fk_roles_base_role` FOREIGN KEY (`base_role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `salary_advance_installments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `salary_advance_id` int NOT NULL,
  `payroll_id` int NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `deduction_date` date DEFAULT NULL,
  `remaining_balance` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `salary_advances` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `installment_amount` decimal(15,2) DEFAULT NULL,
  `deducted_amount` decimal(10,2) DEFAULT '0.00',
  `deduction_start_date` date DEFAULT NULL,
  `currency_code` varchar(3) COLLATE utf8mb4_general_ci DEFAULT 'UGX',
  `date_requested` date NOT NULL,
  `reason` text COLLATE utf8mb4_general_ci,
  `status` enum('Pending','Reviewed','Approved','Rejected','Partially Deducted','Deducted','Cancelled') COLLATE utf8mb4_general_ci DEFAULT 'Pending',
  `deducted_in_payroll_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `attachment` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `manager_comment` text COLLATE utf8mb4_general_ci,
  `reviewed_by` int DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `deducted_in_payroll_id` (`deducted_in_payroll_id`),
  CONSTRAINT `salary_advances_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_advances_ibfk_2` FOREIGN KEY (`deducted_in_payroll_id`) REFERENCES `payroll_records` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `salary_structures` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `base_salary` decimal(15,2) NOT NULL,
  `commissions` decimal(15,2) DEFAULT '0.00',
  `other_earnings` decimal(15,2) DEFAULT '0.00',
  `currency_code` varchar(3) COLLATE utf8mb4_general_ci NOT NULL,
  `effective_date` date NOT NULL,
  `end_date` date DEFAULT NULL COMMENT 'Null means active. Used to preserve historical salary changes.',
  `created_at_utc` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `salary_structures_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tax_slabs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `component_id` int NOT NULL,
  `effective_date` date DEFAULT NULL,
  `min_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `max_amount` decimal(15,2) DEFAULT NULL,
  `tax_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PERCENTAGE',
  `percentage` decimal(5,2) DEFAULT NULL,
  `fixed_amount` decimal(15,2) DEFAULT NULL,
  `company_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `personal_relief` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `fk_tax_slabs_component` (`component_id`),
  KEY `fk_tax_slabs_company` (`company_id`),
  CONSTRAINT `fk_tax_slabs_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tax_slabs_component` FOREIGN KEY (`component_id`) REFERENCES `payroll_components` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tax_slabs_ibfk_1` FOREIGN KEY (`component_id`) REFERENCES `payroll_components` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tax_slabs_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `template_questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL,
  `section` enum('B_KPI','B_SOFT_SKILL','C_SUMMARY','D_MANAGER','E_HR') COLLATE utf8mb4_general_ci NOT NULL,
  `question_text` text COLLATE utf8mb4_general_ci NOT NULL,
  `is_mandatory` tinyint(1) DEFAULT '1',
  `display_order` int DEFAULT '0',
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `description` text COLLATE utf8mb4_general_ci,
  `rating_scale_max` int DEFAULT '5',
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`),
  CONSTRAINT `template_questions_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `appraisal_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=205 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_otps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `otp_code` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_used` tinyint(1) DEFAULT '0',
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_otps_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=314 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_roles` (
  `user_id` int NOT NULL,
  `role_id` int NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int DEFAULT NULL COMMENT 'Maps the login credential to an active employee profile. Can be null for system-only Super Admins.',
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `api_token` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `token_expires_at` timestamp NULL DEFAULT NULL,
  `last_login_utc` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at_utc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `api_token` (`api_token`),
  KEY `employee_id` (`employee_id`),
  KEY `idx_user_token` (`api_token`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

