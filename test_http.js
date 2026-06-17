const http = require('http');

http.get('http://localhost/hrms_v2/test_db.php', (res) => {
    let data = '';
    res.on('data', (chunk) => data += chunk);
    res.on('end', () => console.log(data));
}).on('error', (err) => {
    console.log("Error: " + err.message);
});
