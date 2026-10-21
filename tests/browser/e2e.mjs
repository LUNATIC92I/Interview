// Browser end-to-end checks (Chromium via Playwright).
// Usage: node tests/browser/e2e.mjs <baseUrl> <outDir>
// Registers a candidate, runs a training simulation on a phone sized viewport, checks autosave,
// resume after reload, language switch without losing the draft, feedback, finish and report,
// and verifies that no page overflows horizontally at 360px.
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
let pw;
try { pw = require('playwright'); } catch { pw = require('/opt/node22/lib/node_modules/playwright'); }

const [base = 'http://127.0.0.1:8000', out = 'tests/output'] = process.argv.slice(2);
const results = [];
const check = (name, ok, detail = '') => { results.push({ name, ok, detail }); console.log(`${ok ? 'PASS' : 'FAIL'} browser: ${name}${detail ? ' (' + detail + ')' : ''}`); };

const browser = await pw.chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 375, height: 812 }, isMobile: true, hasTouch: true });
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
page.on('console', (m) => { if (m.type() === 'error') { errors.push(m.text()); } });
page.on('response', (r) => { if (r.status() === 404) { errors.push('404 ' + r.url() + ' ' + r.request().postData()); } });

try {
  const email = `e2e${Date.now()}@example.com`;
  await page.goto(base + '/register');
  await page.fill('#display_name', 'E2E Candidate');
  await page.fill('#email', email);
  await page.fill('#password', 'Browser12345');
  await page.fill('#password_confirmation', 'Browser12345');
  await page.check('input[name=terms]');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  check('registration redirects to dashboard', page.url().endsWith('/dashboard'));

  // Horizontal overflow on a phone
  for (const u of ['/', '/professions', '/professions/soc-analyst-junior', '/simulations', '/dashboard', '/interview/new', '/account']) {
    await page.goto(base + u, { waitUntil: 'networkidle' });
    const width = await page.evaluate(() => document.documentElement.scrollWidth);
    check(`no horizontal scroll on ${u}`, width <= 376, `scrollWidth=${width}`);
  }

  // Setup a training simulation
  await page.goto(base + '/interview/new?profession=soc-analyst-junior');
  await page.check('input[name=mode][value=training]');
  await page.selectOption('#question_count', '3');
  await page.selectOption('#interview_type', 'technical');
  await Promise.all([page.waitForNavigation(), page.click('form[data-setup-form] button[type=submit]')]);
  check('simulation page opens', /\/interview\/[a-f0-9]{32}$/.test(page.url()));
  await page.waitForSelector('[data-question-text]:not(:empty)');
  await page.screenshot({ path: `${out}/e2e_mobile_question.png`, fullPage: true });

  const draft = "D'abord je vérifie le contexte du compte, puis j'analyse l'adresse IP et la MFA.";
  await page.fill('[data-answer]', draft);
  await page.waitForFunction(() => /Enregistré|Saved/.test(document.querySelector('[data-save-status]').textContent), null, { timeout: 8000 });
  check('autosave confirms the draft', true);

  await page.reload();
  await page.waitForSelector('[data-question-text]:not(:empty)');
  check('draft restored after reload', (await page.inputValue('[data-answer]')) === draft);

  // Language switch keeps session and draft
  await Promise.all([page.waitForNavigation(), page.click('.lang-switch a[hreflang=en]')]);
  await page.waitForSelector('[data-question-text]:not(:empty)');
  const lang = await page.getAttribute('html', 'lang');
  check('interface switched to English without losing the draft', lang === 'en' && (await page.inputValue('[data-answer]')) === draft, `lang=${lang}`);

  // Submit and read instant feedback
  const longAnswer = draft + " Ensuite je corrèle avec les journaux d'authentification et l'EDR. Si la compromission est probable, je révoque les sessions, je réinitialise le mot de passe et j'escalade au niveau 2. Enfin je documente la chronologie dans le ticket. Résultat : incident contenu en 20 minutes.";
  await page.fill('[data-answer]', longAnswer);
  await page.click('[data-action=submit]');
  await page.waitForSelector('[data-feedback]:not([hidden])', { timeout: 15000 });
  const fb = await page.textContent('[data-feedback]');
  check('training feedback is displayed', /\/100/.test(fb));
  await page.screenshot({ path: `${out}/e2e_mobile_feedback.png`, fullPage: true });

  // Answer the remaining questions until everything is done
  for (let i = 0; i < 6; i++) {
    const allDone = await page.isVisible('[data-all-done]');
    if (allDone) { break; }
    if (await page.isVisible('[data-action=next]')) {
      const status = await page.textContent('[data-question-meta]');
      if (/Answered|Répondu/.test(status)) { await page.click('[data-action=next]'); await page.waitForTimeout(400); continue; }
    }
    await page.fill('[data-answer]', "Je procède par étapes : d'abord l'analyse, ensuite l'action, enfin la vérification. J'ai obtenu un résultat mesurable de 30 % et j'ai appris à mieux prioriser.");
    await page.click('[data-action=submit]');
    await page.waitForTimeout(800);
  }

  // Finish with confirmation dialog
  await page.click('.interview-top [data-action=finish-open]');
  await page.waitForSelector('#finish-dialog[open]');
  await Promise.all([page.waitForNavigation({ timeout: 30000 }), page.click('[data-action=finish-confirm]')]);
  check('finish leads to the report', /\/reports\/[a-f0-9]{32}$/.test(page.url()));
  await page.screenshot({ path: `${out}/e2e_mobile_report.png`, fullPage: true });
  const width = await page.evaluate(() => document.documentElement.scrollWidth);
  check('report has no horizontal scroll', width <= 376, `scrollWidth=${width}`);
} catch (e) {
  check('browser scenario completed', false, e.message);
}
check('no JavaScript errors', errors.length === 0, errors.slice(0, 3).join(' | '));
await browser.close();
process.exit(results.every((r) => r.ok) ? 0 : 1);
