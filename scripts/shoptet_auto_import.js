// Headless export + import pohoda XML pomocí Playwright
// Spuštění: node scripts/shoptet_auto_import.js
// Předtím: npm install playwright && npx playwright install chromium

const { chromium } = require('playwright');
const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const baseUrl = process.env.SHOPTET_BASE || 'https://www.wormup.com';
const email = process.env.SHOPTET_EMAIL || 'libor@wormup.com';
const password = process.env.SHOPTET_PASSWORD || 'ozov-uda-jecuv';
const eshop = process.env.ESHOP_SOURCE || 'wormup.com';

function formatDate(d) {
  const pad = (n) => (n < 10 ? '0' + n : '' + n);
  return `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()}`;
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  try {
    const today = new Date();
    const yesterday = new Date(today.getTime() - 24 * 60 * 60 * 1000);
    const dateFrom = formatDate(yesterday);
    const dateUntil = formatDate(today);
    const currencies = [
      { id: '1', label: 'czk' },
      { id: '9', label: 'eur' },
    ];

    // Login
    await page.goto(`${baseUrl}/admin/login/`, { waitUntil: 'networkidle' });
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle' }),
      page.click('input[type="submit"]'),
    ]);

    // Otevřít export
    await page.goto(`${baseUrl}/admin/export-faktur/`, { waitUntil: 'networkidle' });

    for (const cur of currencies) {
      // vyplnit formulář
      await page.fill('input[name="dateFrom"]', dateFrom);
      await page.fill('input[name="dateUntil"]', dateUntil);
      await page.selectOption('select[name="currencyId"]', cur.id);
      await page.selectOption('select[name="format"]', 'xml.stormware.cz');
      // odeslat a počkat na download
      const [download] = await Promise.all([
        page.waitForEvent('download'),
        page.click('a[data-testid="buttonExport"]'),
      ]);
      const filename = `shoptet_${cur.label}_${today.getFullYear()}${String(
        today.getMonth() + 1
      ).padStart(2, '0')}${String(today.getDate()).padStart(2, '0')}_${String(
        today.getHours()
      ).padStart(2, '0')}${String(today.getMinutes()).padStart(2, '0')}${String(
        today.getSeconds()
      ).padStart(2, '0')}.xml`;
      const filePath = path.join(__dirname, '..', 'xml', filename);
      await download.saveAs(filePath);
      console.log(`Staženo: ${filePath}`);

      // spustit import přes PHP CLI
      const proc = spawnSync('php', ['scripts/import_pohoda_cli.php', eshop, filePath], {
        stdio: 'inherit',
      });
      if (proc.status !== 0) {
        throw new Error(`Import selhal pro ${filePath} (exit ${proc.status})`);
      }
      fs.unlinkSync(filePath);
    }

    console.log('Hotovo.');
    await browser.close();
    process.exit(0);
  } catch (err) {
    console.error('CHYBA:', err.message || err);
    await page.screenshot({ path: path.join(__dirname, '..', 'xml', 'export_error.png'), fullPage: true }).catch(() => {});
    await browser.close();
    process.exit(1);
  }
})();
