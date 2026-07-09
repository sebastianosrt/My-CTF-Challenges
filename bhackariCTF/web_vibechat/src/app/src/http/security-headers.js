const crypto = require('crypto');

function buildCspHeader(nonce) {
  return [
    "default-src 'self'",
    `script-src 'self' 'nonce-${nonce}' 'unsafe-eval'`,
    `style-src 'self' 'nonce-${nonce}'`,
    "img-src 'self' data:",
    "base-uri 'none'",
    "frame-ancestors 'none'",
    "object-src 'none'"
  ].join('; ');
}

function securityHeaders(req, res, next) {
  const nonce = crypto.randomBytes(16).toString('base64');
  res.locals.cspNonce = nonce;
  res.set({
    'Content-Security-Policy': buildCspHeader(nonce),
    'Referrer-Policy': 'no-referrer',
    'X-Content-Type-Options': 'nosniff',
    'X-Frame-Options': 'DENY',
    'Cross-Origin-Resource-Policy': 'same-origin',
  });
  next();
}

module.exports = { securityHeaders };
