const express = require('express');
const puppeteer = require('puppeteer');

const app = express();
const PORT = 3001;

const SECRET = process.env.SECRET || 'redacted';
const FLAG = process.env.FLAG || 'ptm{fake_flag}';
const CHALLENGE_HOST = `${SECRET}.app.chall.tld`;
const FLAG_HOST = `${SECRET}.flag.chall.tld`;
const ALLOWED_HOSTS = [CHALLENGE_HOST, FLAG_HOST];
const VISIT_TIMEOUT = 10_000;
const PAGE_WAIT = 5_000;

let visiting = false;

app.use(express.json());

app.post('/visit', async (req, res) => {
  const { url } = req.body;
  if (!url || typeof url !== 'string') {
    return res.status(400).json({ error: 'Missing url' });
  }

  let parsed;
  try {
    parsed = new URL(url);
  } catch {
    return res.status(400).json({ error: 'Invalid URL' });
  }

  if (!ALLOWED_HOSTS.includes(parsed.hostname)) {
    return res.status(400).json({ error: 'URL host not allowed' });
  }

  if (visiting) {
    return res.status(429).json({ error: 'Bot is busy, try again later' });
  }

  visiting = true;
  let browser;

  try {
    browser = await puppeteer.launch({
      headless: 'new',
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-gpu',
        '--disable-dev-shm-usage'
      ]
    });

    const interceptPage = async (p) => {
      await p.setRequestInterception(true);
      p.on('request', (interceptedReq) => {
        try {
          const u = new URL(interceptedReq.url());
          if (u.hostname === FLAG_HOST && u.pathname !== '/flag') { // whoooops
            interceptedReq.abort('blockedbyclient');
            return;
          }
        } catch {}
        interceptedReq.continue();
      });
    };

    const page = await browser.newPage();

    await page.goto(`http://${FLAG_HOST}`, { waitUntil: 'networkidle2', timeout: VISIT_TIMEOUT });
    await page.evaluate((flag) => {
      localStorage.setItem("flag", flag);
    }, FLAG);

    await interceptPage(page);
    browser.on('targetcreated', async (target) => {
      if (target.type() === 'page') {
        try {
          const p = await target.page();
          if (p) await interceptPage(p);
        } catch {}
      }
    });

    await page.goto(url, { waitUntil: 'networkidle2', timeout: VISIT_TIMEOUT });
    await new Promise(r => setTimeout(r, PAGE_WAIT));

    res.json({ ok: true });
  } catch (e) {
    console.error('Visit error:', e.message);
    res.json({ ok: false, error: 'Visit failed' });
  } finally {
    if (browser) await browser.close().catch(() => {});
    visiting = false;
  }
});

app.get('/health', (req, res) => res.json({ ok: true }));

app.listen(PORT, '0.0.0.0', () => {
  console.log(`Bot listening on :${PORT}`);
});
