const path = require('path');
const fs = require('fs');
const { default: makeWASocket, useMultiFileAuthState, DisconnectReason } = require('baileys');
const QRCode = require('qrcode');
const config = require('./config');
const { notifyLaravel } = require('./webhookClient');

// tenantId -> { sock, status: 'STARTING'|'QR'|'CONNECTED', qr, saveCreds }
const sessions = new Map();

function statusOf(tenantId) {
  const state = sessions.get(tenantId);
  if (!state) return { status: 'DISCONNECTED' };
  return { status: state.status, qr: state.qr || null };
}

function toJid(rawNumber) {
  const digits = String(rawNumber).replace(/\D/g, '');
  return `${digits}@s.whatsapp.net`;
}

function fromJid(jid) {
  return String(jid || '').replace(/\D/g, '');
}

function extractText(message) {
  if (!message) return null;
  return message.conversation
    ?? message.extendedTextMessage?.text
    ?? message.imageMessage?.caption
    ?? message.videoMessage?.caption
    ?? null;
}

function extractMediaType(message) {
  if (!message) return null;
  const keys = Object.keys(message);
  const mediaKey = keys.find((k) => k.endsWith('Message') && k !== 'extendedTextMessage');
  if (!mediaKey || mediaKey === 'conversation') return null;
  return mediaKey.replace(/Message$/, '');
}

async function startSession(tenantId) {
  const existing = sessions.get(tenantId);
  if (existing && existing.status === 'CONNECTED') {
    return existing;
  }

  const sessionDataPath = path.join(config.sessionsDir, String(tenantId));
  fs.mkdirSync(sessionDataPath, { recursive: true });

  const { state, saveCreds } = await useMultiFileAuthState(sessionDataPath);

  const sessionState = { sock: null, status: 'STARTING', qr: null, saveCreds };
  sessions.set(tenantId, sessionState);

  const sock = makeWASocket({ auth: state });
  sessionState.sock = sock;

  sock.ev.on('creds.update', saveCreds);

  sock.ev.on('connection.update', async (update) => {
    const { connection, lastDisconnect, qr } = update;

    if (qr) {
      try {
        sessionState.qr = await QRCode.toDataURL(qr);
        sessionState.status = 'QR';
        await notifyLaravel(tenantId, 'qr', { qrcode: sessionState.qr });
      } catch (err) {
        console.error(`[session:${tenantId}] generazione QR fallita:`, err.message);
      }
    }

    if (connection === 'open') {
      sessionState.status = 'CONNECTED';
      sessionState.qr = null;
      const phoneNumber = sock.user?.id ? fromJid(sock.user.id.split(':')[0]) : null;
      await notifyLaravel(tenantId, 'ready', { phoneNumber });
    }

    if (connection === 'close') {
      const statusCode = lastDisconnect?.error?.output?.statusCode;
      const loggedOut = statusCode === DisconnectReason.loggedOut;
      const wasConnected = sessionState.status === 'CONNECTED';

      console.error(`[session:${tenantId}] disconnesso (era: ${sessionState.status}):`, {
        statusCode,
        message: lastDisconnect?.error?.message,
        payload: lastDisconnect?.error?.output?.payload,
      });

      sessions.delete(tenantId);
      await notifyLaravel(tenantId, 'state', { state: loggedOut ? 'UNPAIRED' : 'DISCONNECTED' });

      // Riconnette in automatico solo se una sessione già attiva si è interrotta:
      // durante il pairing (QR non ancora scansionato/rifiutato) non insiste da sola,
      // per non rischiare di intasare WhatsApp di tentativi ripetuti — va richiesto
      // esplicitamente un nuovo /start.
      if (!loggedOut && wasConnected) {
        setTimeout(() => {
          startSession(tenantId).catch((err) => {
            console.error(`[session:${tenantId}] riconnessione fallita:`, err.message);
          });
        }, 3000);
      }
    }
  });

  sock.ev.on('messages.upsert', async ({ messages }) => {
    for (const message of messages) {
      if (message.key.fromMe) continue;

      await notifyLaravel(tenantId, 'message', {
        message: {
          id: message.key.id,
          from: fromJid(message.key.remoteJid),
          body: extractText(message.message),
          notifyName: message.pushName,
          type: extractMediaType(message.message) ?? 'chat',
        },
      });
    }
  });

  sock.ev.on('messages.update', async (updates) => {
    for (const { key, update } of updates) {
      if (update.status === undefined || update.status === null) continue;

      await notifyLaravel(tenantId, 'ack', {
        ack: { id: key.id, ack: update.status },
      });
    }
  });

  return sessionState;
}

async function stopSession(tenantId) {
  const state = sessions.get(tenantId);
  if (!state || !state.sock) return false;

  await state.sock.logout().catch((err) => {
    console.error(`[session:${tenantId}] logout con errori (proseguo comunque):`, err.message);
  });
  sessions.delete(tenantId);
  await notifyLaravel(tenantId, 'stopped', {});
  return true;
}

async function sendMessage(tenantId, to, message) {
  const state = sessions.get(tenantId);
  if (!state || state.status !== 'CONNECTED') {
    throw new Error('sessione non connessa');
  }
  return state.sock.sendMessage(toJid(to), { text: message });
}

module.exports = { startSession, stopSession, statusOf, sendMessage };
