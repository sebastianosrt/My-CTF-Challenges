const { PORT } = require('./src/config');
const { createApp } = require('./src/app');

const app = createApp();
const server = app.listen(PORT, () => {
  console.log(`Server listening on http://localhost:${PORT}`);
});

module.exports = { app, server };
