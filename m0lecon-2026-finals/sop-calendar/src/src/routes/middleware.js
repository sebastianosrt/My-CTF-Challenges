const express = require('express');
const crypto = require('crypto');
const { parseCookies } = require('../lib/utils');
const { readSession } = require('../lib/session');
const { users } = require('../lib/state');
const { SESSION_COOKIE, CHALLENGE_HOST } = require('../lib/config');

function registerMiddleware(app) {
  app.use(express.urlencoded({ extended: true }));

  app.use((req, res, next) => {
    const cookies = parseCookies(req.headers.cookie);
    const session = readSession(cookies[SESSION_COOKIE]);
    if (session && users.has(session.username)) {
      req.user = users.get(session.username);
    }
    next();
  });

  app.use((req, res, next) => {
    const nonce = crypto.randomBytes(16).toString('base64');
    req.cspNonce = nonce;

    const headers = {
      'Content-Security-Policy': `default-src 'none'; script-src 'self' 'nonce-${nonce}'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'; object-src 'none';`,
      'X-Frame-Options': 'DENY',
      'X-Content-Type-Options': 'nosniff',
      'Referrer-Policy': 'no-referrer',
      'Cross-Origin-Resource-Policy': 'same-origin'
    };

    for (const key in headers) {
      res.setHeader(key, headers[key]);
    }
    next();
  });
}

module.exports = registerMiddleware;
