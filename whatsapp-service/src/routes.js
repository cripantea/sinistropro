const express = require('express');
const requireInternalSecret = require('./auth');
const sessionManager = require('./sessionManager');

const router = express.Router();
router.use(requireInternalSecret);

router.post('/sessions/:tenantId/start', async (req, res) => {
  try {
    sessionManager.startSession(req.params.tenantId).catch((err) => {
      console.error(`[session:${req.params.tenantId}] avvio fallito:`, err.message);
    });
    res.json(sessionManager.statusOf(req.params.tenantId));
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.get('/sessions/:tenantId/status', (req, res) => {
  res.json(sessionManager.statusOf(req.params.tenantId));
});

router.post('/sessions/:tenantId/messages', async (req, res) => {
  const { to, message } = req.body;
  if (!to || !message) {
    return res.status(400).json({ error: 'to e message sono obbligatori' });
  }
  try {
    const result = await sessionManager.sendMessage(req.params.tenantId, to, message);
    res.json({ ok: true, id: result?.key?.id ?? null });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/sessions/:tenantId/stop', async (req, res) => {
  const stopped = await sessionManager.stopSession(req.params.tenantId);
  res.json({ stopped });
});

module.exports = router;
