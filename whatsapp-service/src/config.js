const path = require('path');

require('dotenv').config({ path: path.join(__dirname, '..', '.env') });

module.exports = {
  port: process.env.PORT || 3002,
  internalSecret: process.env.WHATSAPP_SERVICE_SECRET,
  laravelWebhookUrl: process.env.LARAVEL_WEBHOOK_URL,
  sessionsDir: process.env.WHATSAPP_SESSIONS_DIR || path.join(__dirname, '..', '.sessions'),
};
