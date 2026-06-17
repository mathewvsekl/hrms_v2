const mysql = require('mysql2/promise');

async function main() {
    try {
        const connection = await mysql.createConnection({
            host: 'localhost',
            user: 'root',
            password: '', // Try blank, or we can check backend/config/database.php
            database: 'hrms_v2' // Guessing name
        });
        const [rows] = await connection.execute('SELECT id, name, address, contact_email, contact_phone FROM companies');
        console.log(rows);
        await connection.end();
    } catch (err) {
        console.log(err.message);
    }
}
main();
