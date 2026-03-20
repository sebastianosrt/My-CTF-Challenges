const express = require('express');
const { PORT } = require('./lib/config');
const registerMiddleware = require('./routes/middleware');
const registerAuthRoutes = require('./routes/auth');
const registerSettingsRoutes = require('./routes/settings');
const registerCalendarRoutes = require('./routes/calendar');
const registerReportRoutes = require('./routes/report');
const registerFlagRoutes = require('./routes/flag');

const app = express();

registerMiddleware(app);
registerAuthRoutes(app);
registerSettingsRoutes(app);
registerCalendarRoutes(app);
registerReportRoutes(app);
registerFlagRoutes(app);

app.listen(PORT, '0.0.0.0', () => {
  console.log(`Challenge listening on :${PORT}`);
});
