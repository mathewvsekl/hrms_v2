const fs = require('fs');
const path = require('path');

const basePath = 'C:\\Users\\AneeshMathew\\HRMS V2';
const outFile = path.join(basePath, 'release', 'v1.6.6_fresh_install.sql');

// List of files to append in order
const files = [
    { type: 'file', path: 'DATABASE_SCHEMA.sql' },
    { type: 'file', path: 'database\\migrations\\2026_03_22_configure_types.sql' },
    { type: 'file', path: 'database\\migrations\\2026_03_24_001_add_office_attendance_configs_table.sql' },
    { type: 'file', path: 'database\\migrations\\2026_03_24_002_add_status_definitions.sql' },
    { type: 'file', path: 'database\\migrations\\2026_03_24_weekly_schedule.sql' },
    { type: 'file', path: 'database\\migrations\\attendance_updates.sql' },
    { type: 'file', path: 'release\\v1.6.6\\migration_patch_v1.6.6.sql' },
    { type: 'file', path: 'release\\hotfix_v1.6.6.sql' },
    { type: 'file', path: 'seed_init.sql' }
];

let outContent = "-- HRMS V2 FULL FRESH INSTALL SCHEMA v1.6.6\n";
outContent += "SET FOREIGN_KEY_CHECKS = 0;\n\n";

for (const item of files) {
    const filePath = path.join(basePath, item.path);
    if (!fs.existsSync(filePath)) {
        console.error("Missing file: " + filePath);
        continue;
    }
    
    outContent += `\n-- --------------------------------------------------\n`;
    outContent += `-- FROM: ${item.path}\n`;
    outContent += `-- --------------------------------------------------\n`;

    const content = fs.readFileSync(filePath, 'utf8');
    
    if (item.type === 'file') {
        outContent += content + "\n";
    } else if (item.type === 'extract_appraisal') {
        const tablesToExtract = [
            'appraisal_cycles', 
            'appraisal_templates', 
            'template_questions', 
            'employee_appraisals', 
            'appraisal_ratings', 
            'appraisal_comments'
        ];
        
        for (const tableName of tablesToExtract) {
            // Find CREATE TABLE `tableName` ( ... ) ENGINE=InnoDB ... ;
            const regex = new RegExp(`CREATE TABLE \\\`${tableName}\\\` \\([\\s\\S]*?\\) ENGINE=InnoDB[\\s\\S]*?;`, 'g');
            const match = regex.exec(content);
            if (match) {
                outContent += match[0] + "\n\n";
            } else {
                console.error("Could not find table " + tableName + " in " + item.path);
            }
        }
    }
}

outContent += "\nSET FOREIGN_KEY_CHECKS = 1;\n";

fs.writeFileSync(outFile, outContent);
console.log("Successfully created " + outFile);
