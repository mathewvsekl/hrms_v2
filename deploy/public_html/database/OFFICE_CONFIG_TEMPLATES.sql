-- --------------------------------------------------------
-- COMPANY CONFIGURATION TEMPLATES SEEDER (2026 Compliance)
-- --------------------------------------------------------
-- Target Countries: UAE, Kenya, Uganda, Tanzania, Ethiopia, India

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

INSERT INTO `countries` (`id`, `name`, `iso_code`, `currency_code`, `default_timezone`, `tax_formula_config`, `pension_formula_config`) VALUES
(1, 'United Arab Emirates', 'ARE', 'AED', 'Asia/Dubai', '{"type": "no_income_tax", "vat_rate": 0.05}', '{"type": "gpps", "rate": "standard"}'),
(2, 'Kenya', 'KEN', 'KES', 'Africa/Nairobi', '{"type": "paye", "brackets": "standard_2026"}', '{"type": "nssf", "tier1": true, "tier2": true}'),
(3, 'Uganda', 'UGA', 'UGX', 'Africa/Kampala', '{"type": "paye", "brackets": "standard_2026"}', '{"type": "nssf", "employee_rate": 0.05, "employer_rate": 0.10}'),
(4, 'Tanzania', 'TZA', 'TZS', 'Africa/Dar_es_Salaam', '{"type": "paye", "brackets": "standard_2026"}', '{"type": "nssf", "employee_rate": 0.10, "employer_rate": 0.10}'),
(5, 'Ethiopia', 'ETH', 'ETB', 'Africa/Addis_Ababa', '{"type": "employment_tax", "brackets": "standard_2026"}', '{"type": "pf", "employee_rate": 0.07, "employer_rate": 0.11}'),
(6, 'India', 'IND', 'INR', 'Asia/Kolkata', '{"type": "tds", "regime": "new_2026"}', '{"type": "epf", "employee_rate": 0.12, "employer_rate": 0.12}');

-- 2. COMPANIES
INSERT INTO `companies` (`id`, `country_id`, `name`, `address`, `timezone`) VALUES
(1, 1, 'Avantgarde Inc FZCO (Dubai)', 'P.O. Box: 54833, 3W 214, DAFZA, Dubai, United Arab Emirates.\nTel: +971 4 2594192, Fax: +971 4 2502766', 'Asia/Dubai'),
(2, 2, 'Vision Scientific & Engineering Kenya Ltd.', 'Alpha Centre, Unit 87A, Mombasa Road\nP. O. Box: 14392-00800, Nairobi, Kenya\nTel: +254-722721665', 'Africa/Nairobi'),
(3, 3, 'Vision Scientific & Engineering Uganda Ltd.', 'Plot Shop G-3, Plot No. 231-233, Sixth Street\nIndustrial Area, Kampala-Uganda\nTel: +256 7091 67800', 'Africa/Kampala'),
(4, 4, 'Vision Scientific & Engineering Tanzania Ltd.', 'P.O Box: 5246-Dsm\nUnited Nations Road, Mtiti street\nDar Es Salaam, Tanzania\nTel: 00255 746 916 213', 'Africa/Dar_es_Salaam'),
(5, 5, 'Vision Scientific & Engineering Ethiopia Ltd.', 'Bole Biselex Building, 5th Floor\nAddis Ababa, Ethiopia', 'Africa/Addis_Ababa'),
(6, 6, 'Avantgarde Enterprises Pvt. Ltd. (India)', 'Bldg. No. 1-68/4 & 5, Arunodaya Co-Op Hsg. Soc. Ltd.\nMadhapur, Hyderabad - 500-081, India.\nTel: +91-40-4851-4122', 'Asia/Kolkata');

-- 4. COMPANY CUSTOM FIELDS (Compliance / Mandatory IDs)
-- UAE: Emirates ID, WPS Routing Code
INSERT INTO `company_custom_fields` (`company_id`, `field_key`, `field_name`, `field_type`, `is_required`) VALUES
(1, 'emirates_id', 'Emirates ID', 'text', TRUE),
(1, 'wps_routing_code', 'WPS Routing Code', 'text', TRUE);

-- Kenya: KRA PIN, National ID, SHA Number, NSSF
INSERT INTO `company_custom_fields` (`company_id`, `field_key`, `field_name`, `field_type`, `is_required`) VALUES
(2, 'kra_pin', 'KRA PIN', 'text', TRUE),
(2, 'national_id', 'National ID', 'text', TRUE),
(2, 'sha_number', 'SHA Number', 'text', TRUE),
(2, 'nssf_number', 'NSSF Number', 'text', TRUE);

-- Uganda: NIN, TIN, NSSF
INSERT INTO `company_custom_fields` (`company_id`, `field_key`, `field_name`, `field_type`, `is_required`) VALUES
(3, 'national_id_nin', 'National ID (NIN)', 'text', TRUE),
(3, 'tin_number', 'TIN Number', 'text', TRUE),
(3, 'nssf_number', 'NSSF Number', 'text', TRUE);

-- Tanzania: NIDA, TIN, NSSF
INSERT INTO `company_custom_fields` (`company_id`, `field_key`, `field_name`, `field_type`, `is_required`) VALUES
(4, 'nida_id', 'NIDA ID', 'text', TRUE),
(4, 'tin_number', 'TIN Number', 'text', TRUE),
(4, 'nssf_number', 'NSSF Number', 'text', TRUE);

-- Ethiopia: TIN, Pension ID
INSERT INTO `company_custom_fields` (`company_id`, `field_key`, `field_name`, `field_type`, `is_required`) VALUES
(5, 'tin_number', 'TIN Number', 'text', TRUE),
(5, 'pension_id', 'Pension ID', 'text', TRUE);

-- India: PAN, Aadhaar, UAN (EPF)
INSERT INTO `company_custom_fields` (`company_id`, `field_key`, `field_name`, `field_type`, `is_required`) VALUES
(6, 'pan_number', 'PAN', 'text', TRUE),
(6, 'aadhaar_number', 'Aadhaar Card', 'text', TRUE),
(6, 'uan_number', 'UAN (EPF)', 'text', TRUE);

COMMIT;
