const fs = require('fs');
const path = require('path');
const { FLAG_SUBDOMAIN } = require('../lib/config');

const FLAG_PAGE = fs.readFileSync(path.join(__dirname, '..', 'pages', 'flag-page.html'), 'utf8');

function registerFlagRoutes(app) {
  app.get('/flag', (req, res) => {
    if (req.host !== FLAG_SUBDOMAIN) {
      return res.send(`${req.host} the flag is not here!`);
    }

    const nonce = req.cspNonce ?? '';

    res.send(
      FLAG_PAGE
        .replace(/\{\{nonce\}\}/g, nonce)
    );
  });
}

module.exports = registerFlagRoutes;
