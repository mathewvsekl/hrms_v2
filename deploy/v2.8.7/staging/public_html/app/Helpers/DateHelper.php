<?php
 
 namespace App\Helpers;
 
 /**
  * DateHelper
  * 
  * Standardizes date formatting and timezone conversions across all HRMS modules.
  */
 class DateHelper
 {
     /**
      * Convert frontend input (DD/MM/YYYY) to SQL format (Y-m-d)
      */
     public static function toSql(?string $date): ?string
     {
         if (empty($date)) return null;
 
         // If it's already in Y-m-d format, just return it
         if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
             return $date;
         }
 
         // Try to parse DD/MM/YYYY
         if (strpos($date, '/') !== false) {
             $parts = explode('/', $date);
             if (count($parts) === 3) {
                 // Determine if it is DD/MM/YYYY or YYYY/MM/DD
                 if (strlen($parts[0]) === 4) {
                     return "{$parts[0]}-{$parts[1]}-{$parts[2]}";
                 }
                 return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
             }
         }
 
         // Generic fallback using PHP DateTime
         try {
             $dt = new \DateTime($date);
             return $dt->format('Y-m-d');
         } catch (\Exception $e) {
             return null;
         }
     }
 
     /**
      * Convert SQL format (Y-m-d) to Frontend display format (DD/MM/YYYY)
      */
     public static function toFrontend(?string $date): ?string
     {
         if (empty($date)) return null;
         
         try {
             $dt = new \DateTime($date);
             return $dt->format('d/M/Y');
         } catch (\Exception $e) {
             return $date;
         }
     }
 
     /**
      * Convert Local Time to UTC for storage
      * @param string|null $dateTime The local date time string
      * @param string $timezone The local timezone identifier
      * @return string|null UTC date time string
      */
     public static function toUtc(?string $dateTime, string $timezone = 'UTC'): ?string
     {
         if (empty($dateTime)) return null;
         try {
             $dt = new \DateTime($dateTime, new \DateTimeZone($timezone));
             $dt->setTimezone(new \DateTimeZone('UTC'));
             return $dt->format('Y-m-d H:i:s');
         } catch (\Exception $e) {
             return null;
         }
     }
 
     /**
      * Convert UTC Time to Local Time for display
      * @param string|null $dateTimeUtc The UTC date time string from DB
      * @param string $timezone The target local timezone identifier
      * @param string $format The output format
      * @return string|null Local date time string
      */
     public static function toLocal(?string $dateTimeUtc, string $timezone = 'UTC', string $format = 'Y-m-d H:i:s'): ?string
     {
         if (empty($dateTimeUtc)) return null;
         try {
             $dt = new \DateTime($dateTimeUtc, new \DateTimeZone('UTC'));
             $dt->setTimezone(new \DateTimeZone($timezone));
             return $dt->format($format);
         } catch (\Exception $e) {
             return $dateTimeUtc;
         }
     }
 
     /**
      * Get Timezone for a company
      */
     public static function getCompanyTimezone(int $companyId): string
     {
         try {
             $db = \Database::getInstance()->getConnection();
             $stmt = $db->prepare("SELECT timezone FROM companies WHERE id = ?");
             $stmt->execute([$companyId]);
             $tz = $stmt->fetchColumn();
             return $tz ?: 'UTC';
         } catch (\Exception $e) {
             return 'UTC';
         }
     }
 
     /**
      * Get Timezone for an employee based on their primary company
      */
     public static function getEmployeeTimezone(int $employeeId): string
     {
         try {
             $db = \Database::getInstance()->getConnection();
             $stmt = $db->prepare("
                 SELECT c.timezone 
                 FROM companies c
                 JOIN employee_companies ec ON c.id = ec.company_id
                 WHERE ec.employee_id = ? AND ec.is_primary = 1 AND ec.is_active = 1
                 LIMIT 1
             ");
             $stmt->execute([$employeeId]);
             $tz = $stmt->fetchColumn();
             return $tz ?: 'UTC';
         } catch (\Exception $e) {
             return 'UTC';
         }
     }
 }
