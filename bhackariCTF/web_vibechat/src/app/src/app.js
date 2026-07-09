const express = require('express');
const cookieParser = require('cookie-parser');
const path = require('path');

const { securityHeaders } = require('./http/security-headers');
const { mountAuthRoutes } = require('./routes/auth');
const { mountBotRoute } = require('./routes/bot');
const { mountChatRoutes } = require('./routes/chat');
const { mountJsonpRoute } = require('./routes/jsonp');

function createApp() {
  const app = express();

  app.set('views', path.join(__dirname, 'views'));
  app.set('view engine', 'ejs');

  app.use(express.urlencoded({ extended: false }));
  app.use(cookieParser());

  app.use('/static', express.static(path.join(__dirname, '..', 'public')));
  app.use(
    '/vendor',
    express.static(path.join(__dirname, '..', 'node_modules', 'dompurify', 'dist'))
  );

  app.use(securityHeaders);

  mountAuthRoutes(app);
  mountChatRoutes(app);
  mountJsonpRoute(app);
  mountBotRoute(app);

  return app;
}

module.exports = { createApp };
