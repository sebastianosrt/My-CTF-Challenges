const { renderIndex } = require('../lib/render');
const { users, createUser } = require('../lib/state');
const { setSessionCookie, clearSessionCookie } = require('../lib/session');

function registerAuthRoutes(app) {
  app.get('/', (req, res) => {
    if (req.user) return res.redirect('/calendar');
    res.type('html').send(renderIndex(''));
  });

  app.post('/signup', (req, res) => {
    const username = typeof req.body.username === 'string' ? req.body.username.trim() : '';
    const password = typeof req.body.password === 'string' ? req.body.password : '';

    if (!username || !password) {
      return res.type('html').send(renderIndex('Username and password required'));
    }
    if (username.length < 3 || username.length > 20) {
      return res.type('html').send(renderIndex('Username must be 3-20 characters'));
    }
    if (password.length < 4 || password.length > 50) {
      return res.type('html').send(renderIndex('Password must be 4-50 characters'));
    }
    if (users.has(username)) {
      return res.type('html').send(renderIndex('User already exists'));
    }

    createUser(username, password);
    setSessionCookie(res, username);
    res.redirect('/calendar');
  });

  app.post('/login', (req, res) => {
    const username = typeof req.body.username === 'string' ? req.body.username.trim() : '';
    const password = typeof req.body.password === 'string' ? req.body.password : '';

    if (!username || !password) {
      return res.type('html').send(renderIndex('Username and password required'));
    }

    const user = users.get(username);
    if (!user || user.password !== password) {
      return res.type('html').send(renderIndex('Invalid credentials'));
    }

    setSessionCookie(res, username);
    res.redirect('/calendar');
  });

  app.get('/logout', (req, res) => {
    clearSessionCookie(res);
    res.redirect('/');
  });
}

module.exports = registerAuthRoutes;
