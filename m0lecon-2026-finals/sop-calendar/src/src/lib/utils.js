const crypto = require('crypto');

function generateId() {
  return crypto.randomBytes(16).toString('hex');
}

function parseCookies(cookieHeader) {
  const cookies = {};
  if (cookieHeader) {
    cookieHeader.split(';').forEach(c => {
      const [key, ...rest] = c.trim().split('=');
      if (key) cookies[key] = rest.join('=');
    });
  }
  return cookies;
}

function escapeHtml(s) {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function requireAuth(req, res, next) {
  if (!req.user) return res.redirect('/');
  next();
}

module.exports = { generateId, parseCookies, escapeHtml, requireAuth };
