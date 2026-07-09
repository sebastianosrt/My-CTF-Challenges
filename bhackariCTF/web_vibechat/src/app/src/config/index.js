const PORT = process.env.PORT || 3000;
const FLAG = process.env.FLAG || 'bhackariCTF{test_flag}';
const PUPPETEER_EXECUTABLE_PATH = process.env.PUPPETEER_EXECUTABLE_PATH || '';
const COOKIE_SECURE = process.env.COOKIE_SECURE === 'true';
const SESSION_COOKIE = 'vibechatSession';
const COOKIE_OPTIONS = {
  httpOnly: true,
  secure: COOKIE_SECURE,
  sameSite: 'lax',
  path: '/',
  maxAge: 24 * 60 * 60 * 1000
};

module.exports = {
  PORT,
  FLAG,
  PUPPETEER_EXECUTABLE_PATH,
  COOKIE_SECURE,
  SESSION_COOKIE,
  COOKIE_OPTIONS
};
