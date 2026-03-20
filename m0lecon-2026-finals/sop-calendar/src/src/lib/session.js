const crypto = require('crypto');
const { parse, stringify } = require('./codec');
const { SECRET, SESSION_COOKIE, SESSION_TTL_SECONDS } = require('./config');

function signSessionPayload(encodedPayload) {
  return crypto.createHmac('sha256', SECRET).update(encodedPayload).digest('base64url');
}

function setSessionCookie(res, username) {
  const payload = stringify({
    username,
    exp: Date.now() + SESSION_TTL_SECONDS * 1000
  });
  const encodedPayload = Buffer.from(payload, 'utf8').toString('base64url');
  const signature = signSessionPayload(encodedPayload);
  const token = `${encodedPayload}.${signature}`;

  res.setHeader(
    'Set-Cookie',
    `${SESSION_COOKIE}=${token}; Path=/; HttpOnly; SameSite=Lax; Max-Age=${SESSION_TTL_SECONDS}`
  );
}

function clearSessionCookie(res) {
  res.setHeader('Set-Cookie', `${SESSION_COOKIE}=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0`);
}

function readSession(cookieValue) {
  if (!cookieValue || typeof cookieValue !== 'string') return null;

  const split = cookieValue.lastIndexOf('.');
  if (split <= 0 || split === cookieValue.length - 1) return null;

  const encodedPayload = cookieValue.slice(0, split);
  const signature = cookieValue.slice(split + 1);
  const expected = signSessionPayload(encodedPayload);
  const signatureBuffer = Buffer.from(signature);
  const expectedBuffer = Buffer.from(expected);

  if (
    signatureBuffer.length !== expectedBuffer.length ||
    !crypto.timingSafeEqual(signatureBuffer, expectedBuffer)
  ) {
    return null;
  }

  let session;
  try {
    session = parse(Buffer.from(encodedPayload, 'base64url').toString('utf8'));
  } catch {
    return null;
  }

  if (!session || typeof session !== 'object' || Array.isArray(session)) return null;
  if (typeof session.username !== 'string' || !session.username) return null;
  if (typeof session.exp !== 'number' || !Number.isFinite(session.exp) || session.exp < Date.now()) {
    return null;
  }

  return session;
}

module.exports = {
  readSession,
  setSessionCookie,
  clearSessionCookie
};
