const { generateId, requireAuth } = require('../lib/utils');
const { parseDateInput, formatDayInput, formatTimeInput } = require('../lib/datetime');
const { readEvent, writeEvent, events } = require('../lib/state');
const { renderCalendar, renderEvent } = require('../lib/render');

function registerCalendarRoutes(app) {
  app.get('/calendar', requireAuth, (req, res) => {
    res.type('html').send(renderCalendar(req));
  });

  app.get('/events/:id', (req, res) => {
    const event = readEvent(req.params.id);
    if (!event) return res.status(404).send('Not found');

    const html = renderEvent(event, req);
    res.type('html').send(html);
  });

  app.post('/events', requireAuth, (req, res) => {
    const title = typeof req.body.title === 'string' ? req.body.title.trim() : '';
    const notes = typeof req.body.notes === 'string'
      ? req.body.notes
      : (typeof req.body.content === 'string' ? req.body.content : '');
    const day = typeof req.body.day === 'string' ? req.body.day : '';
    const start = typeof req.body.start === 'string' ? req.body.start : '';
    const end = typeof req.body.end === 'string' ? req.body.end : '';

    if (!title) {
      return res.redirect('/calendar?msg=Title+required');
    }

    const startAt = parseDateInput(day, start);
    const endAt = parseDateInput(day, end);
    if (!startAt || !endAt) {
      return res.redirect('/calendar?msg=Invalid+date+or+time');
    }
    if (endAt <= startAt) {
      return res.redirect('/calendar?msg=End+must+be+after+start');
    }

    const id = generateId();
    writeEvent({
      id,
      title,
      notes,
      owner: req.user.username,
      startAt,
      endAt,
      createdAt: new Date()
    });
    res.redirect(`/events/${encodeURIComponent(id)}/?msg=Event+created`);
  });

  app.post('/events/:id/edit', requireAuth, (req, res) => {
    const event = readEvent(req.params.id);
    if (!event || event.owner !== req.user.username) return res.redirect('/calendar');

    const title = typeof req.body.title === 'string' ? req.body.title.trim() : '';
    const notes = typeof req.body.notes === 'string'
      ? req.body.notes
      : (typeof req.body.content === 'string' ? req.body.content : event.notes);
    const day = typeof req.body.day === 'string' ? req.body.day : formatDayInput(event.startAt);
    const start = typeof req.body.start === 'string' ? req.body.start : formatTimeInput(event.startAt);
    const end = typeof req.body.end === 'string' ? req.body.end : formatTimeInput(event.endAt);

    if (!title) {
      return res.redirect(`/events/${encodeURIComponent(event.id)}?msg=Title+required`);
    }

    const startAt = parseDateInput(day, start);
    const endAt = parseDateInput(day, end);
    if (!startAt || !endAt) {
      return res.redirect(`/events/${encodeURIComponent(event.id)}?msg=Invalid+date+or+time`);
    }
    if (endAt <= startAt) {
      return res.redirect(`/events/${encodeURIComponent(event.id)}?msg=End+must+be+after+start`);
    }

    event.title = title;
    event.notes = notes;
    event.startAt = startAt;
    event.endAt = endAt;
    writeEvent(event);
    res.redirect(`/events/${encodeURIComponent(event.id)}`);
  });

  app.post('/events/:id/delete', requireAuth, (req, res) => {
    const event = readEvent(req.params.id);
    if (!event || event.owner !== req.user.username) return res.redirect('/calendar');

    events.delete(req.params.id);
    res.redirect('/calendar');
  });
}

module.exports = registerCalendarRoutes;
