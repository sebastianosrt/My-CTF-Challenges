const puppeteer = require('puppeteer');
const { PORT, FLAG, PUPPETEER_EXECUTABLE_PATH } = require('../config');

const VISIT_TIMEOUT_MS = 5000;
const POST_VISIT_DELAY_MS = 2000;

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function mountBotRoute(app) {
  app.get('/bot', async (req, res) => {
    const targetRaw = String(req.query.url || '');
    if (!targetRaw) {
      return res.status(400).json({ error: 'url query parameter is required.' });
    }

    let targetUrl;
    try {
      targetUrl = new URL(targetRaw);
    } catch (err) {
      return res.status(400).json({ error: 'Invalid URL.' });
    }

    if (!['http:', 'https:'].includes(targetUrl.protocol)) {
      return res.status(400).json({ error: 'Only http and https URLs are allowed.' });
    }

    const origin = `http://localhost:${PORT}`;
    let browser;
    try {
      browser = await puppeteer.launch({
        headless: 'new',
        ...(PUPPETEER_EXECUTABLE_PATH
          ? { executablePath: PUPPETEER_EXECUTABLE_PATH }
          : {}),
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--ignore-certificate-errors'],
        ignoreHTTPSErrors: true,
        pipe: true
      });
      const page = await browser.newPage();

      await page.goto(`${origin}/login`, {
        waitUntil: 'domcontentloaded',
        timeout: VISIT_TIMEOUT_MS
      });
      await page.type('#username', 'bot');
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle0', timeout: VISIT_TIMEOUT_MS }),
        page.click('button[type="submit"]')
      ]);

      await page.click('#open-settings');
      await wait(300);
      await page.click('#api-key');
      await page.type('#api-key', FLAG);
      await page.click('#submit-api-key-button');
      await page.waitForNavigation({ waitUntil: 'networkidle0', timeout: VISIT_TIMEOUT_MS });

      await page.goto(targetUrl.toString(), {
        waitUntil: 'domcontentloaded',
        timeout: VISIT_TIMEOUT_MS
      });

      await wait(POST_VISIT_DELAY_MS);

      const title = await page.title();

      res.json({ status: 'visited', title });
    } catch (error) {
      console.error(error);
      res.status(500).json({ error: 'Bot failed to visit the requested URL.' });
    } finally {
      if (browser) {
        await browser.close();
      }
    }
  });
}

module.exports = { mountBotRoute };
