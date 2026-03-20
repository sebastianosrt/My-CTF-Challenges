const { stringify } = require('../lib/codec');
const { BOT_URL, SECRET } = require('../lib/config');
const { readEvent } = require('../lib/state');

function registerReportRoutes(app) {
  app.post('/report', async (req, res) => {
    const eventId = typeof req.body.eventId === 'string' ? req.body.eventId : '';
    const secret = req.body.secret;
    const event = readEvent(eventId);
    
    if (secret !== SECRET) {
      return res.redirect('/calendar?msg=Invalid+secret');
    }
    if (!event) {
      return res.redirect('/calendar?msg=Invalid+event');
    }

    const url = `http://${SECRET}.app.chall.tld/events/${encodeURIComponent(eventId)}`;
    try {
      await fetch(`${BOT_URL}/visit`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: `{"url":${stringify(url)}}`
      });
      res.redirect('/calendar?msg=Reported!+Admin+will+review.');
    } catch {
      res.redirect('/calendar?msg=Bot+unavailable');
    }
  });
}

module.exports = registerReportRoutes;
