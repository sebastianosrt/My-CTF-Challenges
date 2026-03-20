const SECRET = process.env.SECRET || 'redacted';

const TIMEZONE_OPTIONS = [
  'UTC',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'Europe/London',
  'Asia/Tokyo'
];

module.exports = {
  PORT: 3000,
  BOT_URL: process.env.BOT_URL || 'http://bot:3001',
  SECRET,
  FLAG_SUBDOMAIN: process.env.FLAG_DOMAIN || `${SECRET}.flag.chall.tld`,
  BOT_USER: process.env.BOT_USER || 'admin',
  BOT_PASS: process.env.BOT_PASS || 'admin',
  SESSION_COOKIE: 'session',
  SESSION_TTL_SECONDS: 60 * 60 * 8,
  TIMEZONE_OPTIONS,
  CHALLENGE_HOST: `${SECRET}.app.chall.tld`,
  TIMEZONE_SET: new Set(TIMEZONE_OPTIONS),
  MAX_NOTES_TEMPLATE_LENGTH: 300
};
