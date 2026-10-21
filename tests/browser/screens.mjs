// Visual review helper: node tests/browser/screens.mjs <baseUrl> <outDir> <email> <password>
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
let pw;
try { pw = require('playwright'); } catch { pw = require('/opt/node22/lib/node_modules/playwright'); }
const [base = 'http://127.0.0.1:8000', out = 'tests/output', email, password] = process.argv.slice(2);
const browser = await pw.chromium.launch();
for (const [name, viewport] of [['desktop', { width: 1366, height: 900 }], ['mobile', { width: 390, height: 844 }]]) {
  const ctx = await browser.newContext({ viewport, reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  const shots = ['/', '/professions', '/professions/soc-analyst-junior', '/simulations', '/login'];
  for (const p of shots) {
    await page.goto(base + p, { waitUntil: 'networkidle' });
    await page.screenshot({ path: `${out}/${name}${p.replace(/\//g, '_') || '_home'}.png`, fullPage: true });
  }
  if (email) {
    await page.goto(base + '/login');
    await page.fill('#email', email);
    await page.fill('#password', password);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
    for (const p of ['/dashboard', '/interview/new', '/history', '/account']) {
      await page.goto(base + p, { waitUntil: 'networkidle' });
      await page.screenshot({ path: `${out}/${name}${p.replace(/\//g, '_')}.png`, fullPage: true });
    }
    const report = await page.$('a[href*="/reports/"]');
    await page.goto(base + '/dashboard');
    const href = await page.getAttribute('a[href*="/reports/"]', 'href').catch(() => null);
    if (href) { await page.goto(base + href.replace(/^https?:\/\/[^/]+/, ''), { waitUntil: 'networkidle' }); await page.screenshot({ path: `${out}/${name}_report.png`, fullPage: true }); }
  }
  await ctx.close();
}
await browser.close();
console.log('screenshots written to', out);
