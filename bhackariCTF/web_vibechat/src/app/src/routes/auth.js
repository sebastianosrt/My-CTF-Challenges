const { getRequestSession, setSessionCookie, generateApiKey } = require('../services/session');

function mountAuthRoutes(app) {
  app.get('/login', (req, res) => {
    const session = getRequestSession(req);
    if (session) {
      return res.redirect('/');
    }
    res.render('pages/login');
  });

  app.post('/login', (req, res) => {
    const username = (req.body?.username || '').trim();
    if (!username) {
      return res.status(400).send('Username is required.');
    }
    if (!/^[a-z0-9]+$/i.test(username)) {
      return res
        .status(400)
        .send('Username can only contain letters and numbers.');
    }

    const session = {
      username,
      apiKey: generateApiKey()
    };
    setSessionCookie(res, session);
    res.redirect('/');
  });
}

module.exports = { mountAuthRoutes };
