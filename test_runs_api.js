const http = require('http');

const options = {
  hostname: 'localhost',
  port: 80,
  path: '/api/payroll/runs',
  method: 'GET',
};

const req = http.request(options, res => {
  console.log(`statusCode: ${res.statusCode}`);
  let data = '';
  res.on('data', d => {
    data += d;
  });
  res.on('end', () => {
    console.log("Response String:", data.substring(0, 500));
  });
});

req.on('error', error => {
  console.error(error);
});

req.end();
