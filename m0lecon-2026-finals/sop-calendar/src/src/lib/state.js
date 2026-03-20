const { parse, stringify } = require('./codec');
const {
  BOT_USER,
  BOT_PASS,
  TIMEZONE_SET,
  MAX_NOTES_TEMPLATE_LENGTH
} = require('./config');

const users = new Map();
const events = new Map();

function defaultUserConfig() {
  return {
    timezone: 'UTC',
    showPastEvents: true,
    notesTemplate: ''
  };
}

function normalizeUserConfig(raw) {
  const base = defaultUserConfig();
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return base;

  const timezone =
    typeof raw.timezone === 'string' && TIMEZONE_SET.has(raw.timezone)
      ? raw.timezone
      : base.timezone;
  const showPastEvents = typeof raw.showPastEvents === 'boolean'
    ? raw.showPastEvents
    : base.showPastEvents;
  const notesTemplate = typeof raw.notesTemplate === 'string'
    ? raw.notesTemplate.slice(0, MAX_NOTES_TEMPLATE_LENGTH)
    : base.notesTemplate;

  return { timezone, showPastEvents, notesTemplate };
}

function getUserConfig(user) {
  if (!user || typeof user !== 'object') return defaultUserConfig();
  const config = normalizeUserConfig(user.config);
  user.config = config;
  return config;
}

function createUser(username, password) {
  const user = {
    username,
    password,
    config: defaultUserConfig()
  };
  users.set(username, user);
  return user;
}

function normalizeEvent(value) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return null;
  if (typeof value.id !== 'string' || !value.id) return null;
  if (typeof value.owner !== 'string' || !value.owner) return null;
  if (typeof value.title !== 'string' || !value.title) return null;

  const startAt = value.startAt instanceof Date ? value.startAt : new Date(value.startAt);
  const endAt = value.endAt instanceof Date ? value.endAt : new Date(value.endAt);
  const createdAt =
    value.createdAt instanceof Date
      ? value.createdAt
      : (value.createdAt ? new Date(value.createdAt) : new Date());

  if (Number.isNaN(startAt.getTime()) || Number.isNaN(endAt.getTime())) return null;
  if (Number.isNaN(createdAt.getTime())) return null;
  if (endAt <= startAt) return null;

  return {
    id: value.id,
    owner: value.owner,
    title: value.title,
    notes: typeof value.notes === 'string' ? value.notes : '',
    startAt,
    endAt,
    createdAt
  };
}

function parseStoredEvent(raw) {
  if (typeof raw !== 'string') return null;
  try {
    return normalizeEvent(parse(raw));
  } catch {
    return null;
  }
}

function writeEvent(event) {
  events.set(event.id, stringify(event));
}

function readEvent(id) {
  if (!events.has(id)) return null;
  const event = parseStoredEvent(events.get(id));
  if (!event) events.delete(id);
  return event;
}

function listUserEvents(username, config = defaultUserConfig()) {
  const output = [];
  const hidePast = !config.showPastEvents;
  const now = Date.now();

  for (const [id, raw] of events.entries()) {
    const event = parseStoredEvent(raw);
    if (!event) {
      events.delete(id);
      continue;
    }
    if (hidePast && event.endAt.getTime() < now) continue;
    if (event.owner === username) output.push(event);
  }

  output.sort((a, b) => {
    const byTime = a.startAt.getTime() - b.startAt.getTime();
    if (byTime !== 0) return byTime;
    return a.title.localeCompare(b.title);
  });

  return output;
}

createUser(BOT_USER, BOT_PASS);

module.exports = {
  users,
  events,
  createUser,
  defaultUserConfig,
  normalizeUserConfig,
  getUserConfig,
  writeEvent,
  readEvent,
  listUserEvents
};
