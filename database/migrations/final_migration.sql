SET FOREIGN_KEY_CHECKS = 0;
-- HRMS V2 - Excel Migration Script
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
INSERT IGNORE INTO `offices` (`id`, `country_id`, `name`, `timezone`) VALUES (1, 1, 'Kampala HQ', 'Africa/Kampala');
INSERT IGNORE INTO `departments` (`id`, `office_id`, `name`) VALUES (1, 1, 'Operations');
INSERT IGNORE INTO `designations` (`id`, `department_id`, `title`) VALUES (51, 1, 'Business Development Executive');
INSERT IGNORE INTO `designations` (`id`, `department_id`, `title`) VALUES (52, 1, 'CEO');
INSERT IGNORE INTO `designations` (`id`, `department_id`, `title`) VALUES (53, 1, 'Country Manager');
INSERT IGNORE INTO `designations` (`id`, `department_id`, `title`) VALUES (54, 1, 'Finance Manager');
INSERT IGNORE INTO `designations` (`id`, `department_id`, `title`) VALUES (55, 1, 'Front Office Executive');
INSERT IGNORE INTO `designations` (`id`, `department_id`, `title`) VALUES (56, 1, 'Office Assistant');
INSERT IGNORE INTO `designations` (`id`, `department_id`, `title`) VALUES (57, 1, 'Sales  Manager');
INSERT IGNORE INTO `designations` (`id`, `department_id`, `title`) VALUES (58, 1, 'Ware House Executive');
INSERT INTO `employees` (`id`, `employee_code`, `office_id`, `department_id`, `designation_id`, `first_name`, `last_name`, `email`, `phone`, `hire_date`, `custom_data`, `status`) VALUES 
(301, 'VUEMP001', 1, 1, (SELECT id FROM designations WHERE title = 'Sales  Manager' LIMIT 1), 'Nyinimuntu', 'Angela', 'angela.nyinimuntu@visionscientificafrica.com', '+256 773 004908', '2018-11-01', '{"tin": "1013867104", "nssf_number": "8804201096294", "bank_name": "Bank of Baroda", "bank_account": "95010100019969"}', 'active');
INSERT INTO `salary_structures` (`employee_id`, `base_salary`, `currency_code`, `effective_date`) VALUES (301, 4506276.6, 'UGX', CURDATE());
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (301, 5, 2026, 4.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (301, 2, 2026, 30.0, 3.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (301, 1, 2026, 21.0, 1.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (301, 3, 2026, 60.0, 21.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (301, 4, 2026, 4.0, 4.0);
INSERT INTO `employees` (`id`, `employee_code`, `office_id`, `department_id`, `designation_id`, `first_name`, `last_name`, `email`, `phone`, `hire_date`, `custom_data`, `status`) VALUES 
(302, 'VUEMP002', 1, 1, (SELECT id FROM designations WHERE title = 'Finance Manager' LIMIT 1), 'Joseph', 'Opolot', 'joseph.opolot@visionscientificafrica.com', '+256 701 179851', '2016-04-01', '{"tin": "1011124524", "nssf_number": "7713800012485", "bank_name": "Bank of Baroda", "bank_account": "95010100019969"}', 'active');
INSERT INTO `salary_structures` (`employee_id`, `base_salary`, `currency_code`, `effective_date`) VALUES (302, 3321661.0, 'UGX', CURDATE());
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (302, 5, 2026, 4.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (302, 2, 2026, 30.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (302, 1, 2026, 21.0, 2.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (302, 3, 2026, 60.0, 60.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (302, 4, 2026, 4.0, 0.0);
INSERT INTO `employees` (`id`, `employee_code`, `office_id`, `department_id`, `designation_id`, `first_name`, `last_name`, `email`, `phone`, `hire_date`, `custom_data`, `status`) VALUES 
(303, 'VUEMP004', 1, 1, (SELECT id FROM designations WHERE title = 'Ware House Executive' LIMIT 1), 'Moses', 'Ikwara', 'vuemp004@visionscientificafrica.com', '+256 772 900196', '2015-11-01', '{"tin": "1011362350", "nssf_number": "7714200249854", "bank_name": "Bank of Baroda", "bank_account": "95140100002364"}', 'active');
INSERT INTO `salary_structures` (`employee_id`, `base_salary`, `currency_code`, `effective_date`) VALUES (303, 1425600.0, 'UGX', CURDATE());
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (303, 5, 2026, 4.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (303, 2, 2026, 30.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (303, 1, 2026, 21.0, 6.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (303, 3, 2026, 60.0, 60.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (303, 4, 2026, 4.0, 0.0);
INSERT INTO `employees` (`id`, `employee_code`, `office_id`, `department_id`, `designation_id`, `first_name`, `last_name`, `email`, `phone`, `hire_date`, `custom_data`, `status`) VALUES 
(304, 'VUEMP005', 1, 1, (SELECT id FROM designations WHERE title = 'Front Office Executive' LIMIT 1), 'Hawa', 'Sirikye', 'hawa.nasinza@visionscientificafrica.com', '+256 755 229943', '2018-02-01', '{"tin": "1019037946", "nssf_number": "9100301275708", "bank_name": "Bank of Baroda", "bank_account": "95140100002019"}', 'active');
INSERT INTO `salary_structures` (`employee_id`, `base_salary`, `currency_code`, `effective_date`) VALUES (304, 1730215.2, 'UGX', CURDATE());
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (304, 5, 2026, 4.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (304, 2, 2026, 30.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (304, 1, 2026, 21.0, 1.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (304, 3, 2026, 60.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (304, 4, 2026, 4.0, 4.0);
INSERT INTO `employees` (`id`, `employee_code`, `office_id`, `department_id`, `designation_id`, `first_name`, `last_name`, `email`, `phone`, `hire_date`, `custom_data`, `status`) VALUES 
(305, 'VUEMP008', 1, 1, (SELECT id FROM designations WHERE title = 'Country Manager' LIMIT 1), 'Aneesh', 'Mathew', 'aneesh.mathew@visionscientificafrica.com', '+256 709167800', '2021-05-01', '{"tin": "1018279351", "nssf_number": "8418704097550", "bank_name": "Bank of Baroda", "bank_account": "95140100002412"}', 'active');
INSERT INTO `salary_structures` (`employee_id`, `base_salary`, `currency_code`, `effective_date`) VALUES (305, 6930000.0, 'UGX', CURDATE());
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (305, 5, 2026, 4.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (305, 2, 2026, 30.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (305, 1, 2026, 21.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (305, 3, 2026, 60.0, 60.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (305, 4, 2026, 4.0, 0.0);
INSERT INTO `employees` (`id`, `employee_code`, `office_id`, `department_id`, `designation_id`, `first_name`, `last_name`, `email`, `phone`, `hire_date`, `custom_data`, `status`) VALUES 
(306, 'VUEMP009', 1, 1, (SELECT id FROM designations WHERE title = 'Office Assistant' LIMIT 1), 'Atim', 'Bernadatte', 'vuemp009@visionscientificafrica.com', '+256 786 135912', '2021-06-01', '{"tin": "", "nssf_number": "", "bank_name": "", "bank_account": ""}', 'active');
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (306, 5, 2026, 4.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (306, 2, 2026, 30.0, 2.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (306, 1, 2026, 21.0, 1.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (306, 3, 2026, 60.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (306, 4, 2026, 4.0, 4.0);
INSERT INTO `employees` (`id`, `employee_code`, `office_id`, `department_id`, `designation_id`, `first_name`, `last_name`, `email`, `phone`, `hire_date`, `custom_data`, `status`) VALUES 
(307, 'VUEMP011', 1, 1, (SELECT id FROM designations WHERE title = 'Business Development Executive' LIMIT 1), 'Kawunde', 'Paul', 'paul.kawunde@visionscientificafrica.com', '+256 780 610392', '2020-02-01', '{"tin": "1019028053", "nssf_number": "9418702271143", "bank_name": "Bank of Baroda", "bank_account": "95140100002021"}', 'active');
INSERT INTO `salary_structures` (`employee_id`, `base_salary`, `currency_code`, `effective_date`) VALUES (307, 4476754.9, 'UGX', CURDATE());
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (307, 5, 2026, 4.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (307, 2, 2026, 30.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (307, 1, 2026, 21.0, 1.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (307, 3, 2026, 60.0, 60.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (307, 4, 2026, 4.0, 0.0);
INSERT INTO `employees` (`id`, `employee_code`, `office_id`, `department_id`, `designation_id`, `first_name`, `last_name`, `email`, `phone`, `hire_date`, `custom_data`, `status`) VALUES 
(308, 'VUEMP015', 1, 1, (SELECT id FROM designations WHERE title = 'Business Development Executive' LIMIT 1), 'Lubega', 'Joel Brian', 'brian.lubega@visionscientificafrica.com', '+256 784 042269', '2023-07-01', '{"tin": "1008938503", "nssf_number": "8710200077782", "bank_name": "Bank of Baroda", "bank_account": "95140100003237"}', 'active');
INSERT INTO `salary_structures` (`employee_id`, `base_salary`, `currency_code`, `effective_date`) VALUES (308, 2004000.7, 'UGX', CURDATE());
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (308, 5, 2026, 4.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (308, 2, 2026, 30.0, 1.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (308, 1, 2026, 21.0, 1.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (308, 3, 2026, 60.0, 60.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (308, 4, 2026, 4.0, 0.0);
INSERT INTO `employees` (`id`, `employee_code`, `office_id`, `department_id`, `designation_id`, `first_name`, `last_name`, `email`, `phone`, `hire_date`, `custom_data`, `status`) VALUES 
(309, 'VUEMP017', 1, 1, (SELECT id FROM designations WHERE title = 'Business Development Executive' LIMIT 1), 'Joseph', 'Wetaka Wandega', 'joseph.wandega@visionscientificafrica.com', '+256 771 456994', '2024-09-09', '{"tin": "1020842474", "nssf_number": "9318701601830", "bank_name": "Bank of Baroda", "bank_account": "95140100002519"}', 'active');
INSERT INTO `salary_structures` (`employee_id`, `base_salary`, `currency_code`, `effective_date`) VALUES (309, 1695385.0, 'UGX', CURDATE());
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (309, 5, 2026, 4.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (309, 2, 2026, 30.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (309, 1, 2026, 21.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (309, 3, 2026, 60.0, 60.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (309, 4, 2026, 4.0, 0.0);
INSERT INTO `employees` (`id`, `employee_code`, `office_id`, `department_id`, `designation_id`, `first_name`, `last_name`, `email`, `phone`, `hire_date`, `custom_data`, `status`) VALUES 
(310, 'VUEMP018', 1, 1, (SELECT id FROM designations WHERE title = 'Business Development Executive' LIMIT 1), 'Solomon', 'Kiwunda ', 'solomon.kiwunda@visionscientificafrica.com', '+256 770 774150', '2025-01-06', '{"tin": "1021583578", "nssf_number": "9618704182190", "bank_name": "Centenary Bank", "bank_account": "32028043180"}', 'active');
INSERT INTO `salary_structures` (`employee_id`, `base_salary`, `currency_code`, `effective_date`) VALUES (310, 2926154.0, 'UGX', CURDATE());
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (310, 5, 2026, 4.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (310, 2, 2026, 30.0, 0.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (310, 1, 2026, 21.0, 1.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (310, 3, 2026, 60.0, 60.0);
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`) VALUES (310, 4, 2026, 4.0, 0.0);
INSERT INTO `employees` (`id`, `employee_code`, `office_id`, `department_id`, `designation_id`, `first_name`, `last_name`, `email`, `phone`, `hire_date`, `custom_data`, `status`) VALUES 
(311, 'VUEMP019', 1, 1, (SELECT id FROM designations WHERE title = 'CEO' LIMIT 1), 'Prem', 'Kishore Babu Kumaradas', 'vuemp019@visionscientificafrica.com', NULL, '2024-01-01', '{"tin": "1008809372", "nssf_number": "nan", "bank_name": "nan", "bank_account": "nan"}', 'active');
INSERT INTO `salary_structures` (`employee_id`, `base_salary`, `currency_code`, `effective_date`) VALUES (311, 3500000.0, 'UGX', CURDATE());
COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
