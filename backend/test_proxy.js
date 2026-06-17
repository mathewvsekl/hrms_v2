const fs = require('fs');

async function test() {
    try {
        const response = await fetch("https://hrms.anedins.com/db_proxy.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-HRMS-Proxy-Token": "HRMS_LOCAL_DEV_SECURE_TOKEN_55"
            },
            body: JSON.stringify({
                sql: "SHOW COLUMNS FROM employee_appraisals WHERE Field='status'",
                params: []
            })
        });
        
        const data = await response.json();
        console.log(JSON.stringify(data, null, 2));
    } catch (e) {
        console.error(e);
    }
}
test();
