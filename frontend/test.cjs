const axios = require('axios');

async function testLogin() {
    try {
        const res = await axios.post('http://localhost:8000/api/login', {
            username: 'mathew.vsekl@gmail.com',
            password: 'Vision@2026'
        });
        console.log("Success:", res.data);
    } catch (err) {
        console.error("Error:", err.response ? err.response.data : err.message);
    }
}

testLogin();
