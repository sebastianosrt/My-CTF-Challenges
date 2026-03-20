const { requireAuth } = require('../lib/utils');
const { renderSettings } = require('../lib/render');
const { normalizeUserConfig } = require('../lib/state');
const { TIMEZONE_SET, MAX_NOTES_TEMPLATE_LENGTH } = require('../lib/config');

function registerSettingsRoutes(app) {
  app.get('/settings', requireAuth, (req, res) => {
    res.type('html').send(renderSettings(req));
  });

  app.post('/settings', requireAuth, (req, res) => {
    const timezone = typeof req.body.timezone === 'string' ? req.body.timezone : '';
    const notesTemplate = typeof req.body.notesTemplate === 'string' ? req.body.notesTemplate : '';
    const showPastEvents = req.body.showPastEvents === 'on';

    if (!TIMEZONE_SET.has(timezone)) {
      return res.redirect('/settings?msg=Invalid+timezone');
    }
    if (notesTemplate.length > MAX_NOTES_TEMPLATE_LENGTH) {
      return res.redirect('/settings?msg=Notes+template+too+long');
    }

    req.user.config = normalizeUserConfig({
      timezone,
      showPastEvents,
      notesTemplate
    });
    res.redirect('/settings?msg=Settings+saved');
  });
}

module.exports = registerSettingsRoutes;
