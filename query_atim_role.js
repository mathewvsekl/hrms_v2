const mysql = require('mysql2/promise');

async function main() {
    try {
        const connection = await mysql.createConnection({
            host: '127.0.0.1',
            user: 'root',
            password: '',
            database: 'hrms_v2'
        });
        const [rows] = await connection.execute("SELECT u.id, e.first_name, e.last_name, r.name, br.name as base_name FROM employees e JOIN users u ON u.employee_id = e.id JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id LEFT JOIN roles br ON r.base_role_id = br.id WHERE e.first_name = 'Atim'");
        console.log(rows);
        await connection.end();
    } catch (err) {
        console.log(err.message);
    }
}
main();
