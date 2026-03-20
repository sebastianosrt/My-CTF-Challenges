function toTwoDigits(value) {
  return String(value).padStart(2, '0');
}

function formatDayInput(date) {
  return `${date.getFullYear()}-${toTwoDigits(date.getMonth() + 1)}-${toTwoDigits(date.getDate())}`;
}

function formatTimeInput(date) {
  return `${toTwoDigits(date.getHours())}:${toTwoDigits(date.getMinutes())}`;
}

function getTimezone(config) {
  return config && typeof config.timezone === 'string' ? config.timezone : 'UTC';
}

function formatDateLabel(date, config) {
  return date.toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    timeZone: getTimezone(config)
  });
}

function formatTimeLabel(date, config) {
  return date.toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: getTimezone(config)
  });
}

function parseDateInput(day, time) {
  if (typeof day !== 'string' || typeof time !== 'string') return null;
  if (!/^\d{4}-\d{2}-\d{2}$/.test(day)) return null;
  if (!/^([01]\d|2[0-3]):([0-5]\d)$/.test(time)) return null;

  const date = new Date(`${day}T${time}:00`);
  if (Number.isNaN(date.getTime())) return null;

  if (formatDayInput(date) !== day || formatTimeInput(date) !== time) return null;
  return date;
}

module.exports = {
  formatDayInput,
  formatTimeInput,
  formatDateLabel,
  formatTimeLabel,
  parseDateInput
};
