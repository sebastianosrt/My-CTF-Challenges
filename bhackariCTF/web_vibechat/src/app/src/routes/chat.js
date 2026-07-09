const { getRequestSession, setSessionCookie, generateApiKey } = require('../services/session');
const { serializeJsonScript } = require('../utils/json-script');

function mountChatRoutes(app) {
  app.get('/', (req, res) => {
    const session = getRequestSession(req);
    if (!session) {
      return res.redirect('/login');
    }

    if (!session.apiKey) {
      session.apiKey = generateApiKey();
      setSessionCookie(res, session);
    }

    res.render('pages/home', {
      username: session.username,
      initialState: serializeJsonScript({
        username: session.username,
        apiKey: session.apiKey
      })
    });
  });

  app.post('/', (req, res) => {
    const session = getRequestSession(req);
    if (!session) {
      return res.status(401).json({ error: 'Not authenticated.' });
    }

    if (!req.body) return res.redirect('/');

    const apiKey = String(req.body['api-key'] || '').trim();
    if (!apiKey) {
      return res.status(400).json({ error: 'API key is required.' });
    }

    if (apiKey.length > 128) {
      return res
        .status(400)
        .json({ error: 'API key must be 128 characters or fewer.' });
    }

    session.apiKey = apiKey;
    setSessionCookie(res, session);
    return res.redirect('/');
  });
}

module.exports = { mountChatRoutes };
