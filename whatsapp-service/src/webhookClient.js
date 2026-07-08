const axios = require('axios');
const config = require('./config');

async function notifyLaravel(tenantId, event, data) {
  if (!config.laravelWebhookUrl) return;
  try {
    await axios.post(
      config.laravelWebhookUrl,
      { tenantId, event, data },
      { headers: { 'X-Internal-Secret': config.internalSecret }, timeout: 10000 }
    );
  } catch (err) {
    console.error(`[webhook] notifica fallita (tenant ${tenantId}, evento ${event}):`, err.message);
  }
}

module.exports = { notifyLaravel };
