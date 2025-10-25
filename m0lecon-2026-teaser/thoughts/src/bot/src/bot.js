process.env.HOME = "/tmp";

const { delay, handleTargetCreated, logMainInfo, logMainError } = require("./utils");
const { VisitQueue, sleep_time } = require("./queue");
const crypto = require('node:crypto');
const redis = require('redis');
const puppeteer = require("puppeteer");

const tips = ["Every console.log usage on the bot will be sent back to you :)", "There is a small race window (~10ms) when a new tab is opened where console.log won't return output :("];
console.log(`==========\nTips: ${tips[Math.floor(Math.random() * tips.length)]}\n==========`);

const BASE_URL = process.env.BASE_URL || "https://thoughts:3000";
const MAX_CONCURRENCY = parseInt(process.env.MAX_CONCURRENCY) || 1;

const visitQueue = new VisitQueue(MAX_CONCURRENCY);

async function visit(url, userSecret) {
	logMainInfo("Starting the browser...");

	const browser = await puppeteer.launch({
		headless: "new",
		ignoreHTTPSErrors: true,
		pipe: true,
		args: [
			"--no-sandbox",
			"--disable-dev-shm-usage",
			"--js-flags=--jitless,--no-expose-wasm",
			"--disable-gpu",
			"--ignore-certificate-errors",
			"--user-agent=headless"
		],
		executablePath: "/usr/bin/chromium-browser"
	});

	const [page] = await browser.pages();
	await handleTargetCreated(page.target());
	await page.setDefaultNavigationTimeout(3000);

	try {
		
		const client = redis.createClient({ url: 'redis://redis:6379' });
		await client.connect();
		
		const secret = crypto.randomBytes(3).toString('hex');
		// console.log("Generated secret:", secret);
		
		logMainInfo("Setting secret");
		await client.setEx(`secret:${userSecret}`, sleep_time + 2, secret);
		await client.disconnect();

		await page.goto(BASE_URL, { waitUntil: 'domcontentloaded' });
		await page.setCookie({
			name: "secret",
			value: secret,
			expires: Math.floor(Date.now() / 1000) + 60,
			path: "/",
			secure: false,
			httpOnly: true,
			sameSite: "Strict"
		});

	} catch (e) {
		logMainError("Failed to set cookie: " + e.message);
		await browser.close();
		return;
	}

	logMainInfo("Going to the user provided link...");
	try { await page.goto(url) } catch { }
	await delay(sleep_time * 1000);

	logMainInfo("Leaving o/");
	await browser.close();
	return;
}

const isValidURL = (url) => {
	try {
		const urlObj = new URL(url);
		return urlObj.protocol === 'http:' || urlObj.protocol === 'https:';
	} catch {
		return false;
	}
};

const isValidSecret = (secret) => {
	return typeof secret === 'string' && /^[a-f0-9]{16}$/.test(secret);
};

process.stdin.on("data", async (data) => {
	try {
		const input = data.toString().trim();
		const [url, userSecret] = input.split(' ', 2);

		if (!url || !isValidURL(url)) {
			logMainError("You provided an invalid URL. It should start with http:// or https://.");
			process.exit(1);
		}

		if (!userSecret || !isValidSecret(userSecret)) {
			logMainError("Secret is invalid.");
			process.exit(1);
		}

		visitQueue.enqueue(url, userSecret, logMainInfo, logMainError)
			.then(() => {
				logMainInfo("Visit request queued successfully");
			})
			.catch((error) => {
				logMainError(`Failed to queue visit: ${error.message}`);
				if (process.env.ENVIRONMENT === "development") {
					console.error(error);
				}
			});
	} catch (e) {
		logMainError("Invalid input");
		process.exit(1);
	}
});

module.exports = { visit, sleep_time };