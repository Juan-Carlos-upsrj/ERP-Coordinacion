const https = require('https');

const data = JSON.stringify({
  action: 'get-full-schedule',
  profesor_nombre: 'juan carlos salgado robles'
});

const req = https.request({
  hostname: 'gestionacademica.tailaf0046.ts.net',
  path: '/coordinacion/api/sync.php',
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-API-KEY': 'UPSRJ_2025_SECURE_SYNC',
    'X-CARRERA': 'iaev',
    'Content-Length': data.length
  }
}, (res) => {
  let body = '';
  res.on('data', chunk => body += chunk);
  res.on('end', () => console.log(JSON.stringify(JSON.parse(body), null, 2)));
});

req.on('error', console.error);
req.write(data);
req.end();
