ALTER TABLE `companies` 
ADD COLUMN `contact_phone` VARCHAR(30) NULL AFTER `address`,
ADD COLUMN `contact_email` VARCHAR(100) NULL AFTER `contact_phone`;

-- Patch for Leave Request Regularization
UPDATE `leave_requests` SET `origin` = 'system' WHERE `remarks` LIKE '%System-Generated%';
