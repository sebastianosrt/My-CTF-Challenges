const crypto = require('crypto');
const { SESSION_COOKIE, COOKIE_OPTIONS } = require('../config');

function getSessionFromCookie(rawCookie = '') {
  if (!rawCookie) {
    return null;
  }

  try {
    const decoded = Buffer.from(rawCookie, 'base64url').toString('utf8');
    const data = JSON.parse(decoded);
    if (!data || typeof data.username !== 'string' || !data.username) {
      return null;
    }
    if (typeof data.apiKey !== 'string') {
      data.apiKey = '';
    }
    return data;
  } catch (err) {
    return null;
  }
}

function getRequestSession(req) {
  return getSessionFromCookie(req.cookies[SESSION_COOKIE]);
}

function setSessionCookie(res, session) {
  const encoded = Buffer.from(JSON.stringify(session)).toString('base64url');
  res.cookie(SESSION_COOKIE, encoded, COOKIE_OPTIONS);
}

function generateApiKey() {
  return crypto.randomBytes(16).toString('hex');
}

module.exports = {
  getSessionFromCookie,
  getRequestSession,
  setSessionCookie,
  generateApiKey
};
