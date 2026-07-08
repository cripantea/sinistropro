const express = require('express');
const config = require('./src/config');
const routes = require('./src/routes');

const app = express();
app.use(express.json());
app.get('/health', (req, res) => res.json({ ok: true }));
app.use(routes);

app.listen(config.port, '127.0.0.1', () => {
  console.log(`whatsapp-service in ascolto su 127.0.0.1:${config.port}`);
});
