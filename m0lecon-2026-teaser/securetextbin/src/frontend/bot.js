import puppeteer from "puppeteer";
import { computeExecutablePath } from "@puppeteer/browsers";
const FLAG = process.env.FLAG || "flag{test_flag_0a1d}";

const firefoxExecutablePath = computeExecutablePath({
    browser: 'firefox',
    buildId: 'stable',
    cacheDir: '/root/.cache/puppeteer/',
});

async function visit(url) {
    let browser;
    try {
        browser = await puppeteer.launch({
            browser: "firefox",
            headless: true,
            executablePath: firefoxExecutablePath,
            args: ['--no-sandbox', '--disable-setuid-sandbox'],
            pipe: true
        });

        const ctx = await browser.createBrowserContext();
        const page = await ctx.newPage();
        
        await page.goto('http://frontend:1337/', { timeout: 2000 });
        await page.evaluate((flag) => {
          localStorage.setItem("flag", flag);  
        }, FLAG)

        console.log(`Visiting: ${url}`);
        await page.goto(url, { timeout: 2000 });

        await new Promise((r) => setTimeout(r, 2000));
    } catch (err) {
        console.log(err);
    } finally {
        if (browser) await browser.close();
    }
}

export default visit;