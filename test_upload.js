const http = require('http');

const boundary = '----WebKitFormBoundary7MA4YWxkTrZu0gW';
let postData = '';

postData += `--${boundary}\r\n`;
postData += `Content-Disposition: form-data; name="logo"; filename="test.png"\r\n`;
postData += `Content-Type: image/png\r\n\r\n`;
postData += `fakeimagecontent\r\n`;
postData += `--${boundary}--\r\n`;

const options = {
  hostname: 'localhost',
  port: 8000,
  path: '/api/companies/logo/1',
  method: 'POST',
  headers: {
    'Content-Type': `multipart/form-data; boundary=${boundary}`,
    'Content-Length': Buffer.byteLength(postData)
  }
};

const req = http.request(options, res => {
  console.log(`STATUS: ${res.statusCode}`);
  let data = '';
  res.on('data', chunk => data += chunk);
  res.on('end', () => console.log('BODY:', data));
});

req.on('error', e => console.error(e));
req.write(postData);
req.end();
