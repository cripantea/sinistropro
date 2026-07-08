const path = require('path');
const fs = require('fs');
const { create } = require('@open-wa/wa-automate');
const config = require('./config');
const { notifyLaravel } = require('./webhookClient');

// tenantId -> { client, status: 'STARTING'|'QR'|'CONNECTED', qr }
const sessions = new Map();

function statusOf(tenantId) {
  const state = sessions.get(tenantId);
  if (!state) return { status: 'DISCONNECTED' };
  return { status: state.status, qr: state.qr || null };
}

function toChatId(rawNumber) {
  const digits = String(rawNumber).replace(/\D/g, '');
  return `${digits}@c.us`;
}

function registerListeners(tenantId, state) {
  const { client } = state;

  client.onMessage(async (message) => {
    await notifyLaravel(tenantId, 'message', { message });
  });

  client.onAck(async (ack) => {
    await notifyLaravel(tenantId, 'ack', { ack });
  });

  client.onStateChanged((waState) => {
    notifyLaravel(tenantId, 'state', { state: waState });
    if (waState === 'CONFLICT' || waState === 'UNPAIRED' || waState === 'UNLAUNCHED') {
      client.forceRefocus();
    }
  });
}

async function startSession(tenantId) {
  const existing = sessions.get(tenantId);
  if (existing && existing.status === 'CONNECTED') {
    return existing;
  }

  const state = { client: null, status: 'STARTING', qr: null };
  sessions.set(tenantId, state);

  const sessionDataPath = path.join(config.sessionsDir, tenantId);
  fs.mkdirSync(sessionDataPath, { recursive: true });

  const client = await create({
    sessionId: tenantId,
    multiDevice: true,
    headless: true,
    authTimeout: 60,
    qrTimeout: 0,
    blockCrashLogs: true,
    disableSpins: true,
    popup: false,
    logConsole: false,
    cacheEnabled: false,
    sessionDataPath,
    qrCallback: (qrcode) => {
      state.qr = qrcode;
      state.status = 'QR';
      notifyLaravel(tenantId, 'qr', { qrcode });
    },
    statusFind: (statusSession) => {
      notifyLaravel(tenantId, 'status', { status: statusSession });
    },
  });

  state.client = client;
  state.status = 'CONNECTED';
  state.qr = null;
  registerListeners(tenantId, state);
  await notifyLaravel(tenantId, 'ready', {});

  return state;
}

async function stopSession(tenantId) {
  const state = sessions.get(tenantId);
  if (!state || !state.client) return false;
  await state.client.kill();
  sessions.delete(tenantId);
  await notifyLaravel(tenantId, 'stopped', {});
  return true;
}

async function sendMessage(tenantId, to, message) {
  const state = sessions.get(tenantId);
  if (!state || state.status !== 'CONNECTED') {
    throw new Error('sessione non connessa');
  }
  return state.client.sendText(toChatId(to), message);
}

module.exports = { startSession, stopSession, statusOf, sendMessage };
