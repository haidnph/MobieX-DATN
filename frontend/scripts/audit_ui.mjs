import puppeteer from 'puppeteer-core';
import fs from 'fs';
import path from 'path';

const SCREENSHOT_DIR = path.resolve(import.meta.dirname, '..', '..', 'docs', 'audit_screenshots');
if (!fs.existsSync(SCREENSHOT_DIR)) {
  fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

const BROWSER_PATH = fs.existsSync('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe')
  ? 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
  : 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';

const BASE_URL = 'http://localhost:5173';

const viewports = [
  { name: 'desktop', width: 1280, height: 800, isMobile: false },
  { name: 'mobile', width: 390, height: 844, isMobile: true, hasTouch: true }
];

async function runAudit() {
  console.log(`Starting headless browser audit using: ${BROWSER_PATH}`);
  
  const browser = await puppeteer.launch({
    executablePath: BROWSER_PATH,
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu']
  });

  const auditLog = [];

  for (const vp of viewports) {
    console.log(`\n=== Testing Viewport: ${vp.name} (${vp.width}x${vp.height}) ===`);
    const page = await browser.newPage();
    await page.setViewport({ width: vp.width, height: vp.height, isMobile: vp.isMobile, hasTouch: vp.hasTouch });

    const errors = [];
    const warnings = [];
    const networkFailures = [];

    page.on('console', msg => {
      const type = msg.type();
      const text = msg.text();
      // Filter out react-devtools or harmless noise
      if (type === 'error') {
        errors.push({ type, text, location: msg.location() });
      } else if (type === 'warning') {
        warnings.push({ type, text });
      }
    });

    page.on('pageerror', err => {
      errors.push({ type: 'uncaught-exception', text: err.message, stack: err.stack });
    });

    page.on('requestfailed', req => {
      // Don't flag chrome-extension or external analytics
      const url = req.url();
      if (url.startsWith('http://localhost') || url.startsWith('http://127.0.0.1')) {
        networkFailures.push({ url, failure: req.failure()?.errorText });
      }
    });

    page.on('response', resp => {
      const url = resp.url();
      const status = resp.status();
      if ((url.startsWith('http://localhost') || url.startsWith('http://127.0.0.1')) && status >= 400) {
        // Exclude expected 401s during initial unauthenticated checks
        networkFailures.push({ url, status, statusText: resp.statusText() });
      }
    });

    const publicPages = [
      { name: 'homepage', url: '/' },
      { name: 'catalog', url: '/products' },
      { name: 'cart', url: '/cart' },
      { name: 'checkout', url: '/checkout' },
      { name: 'login', url: '/login' },
      { name: 'admin-login', url: '/admin/login' }
    ];

    for (const p of publicPages) {
      errors.length = 0;
      warnings.length = 0;
      networkFailures.length = 0;

      const targetUrl = `${BASE_URL}${p.url}`;
      console.log(`Navigating to [${vp.name}] ${p.name}: ${targetUrl}`);
      
      try {
        await page.goto(targetUrl, { waitUntil: 'networkidle2', timeout: 15000 });
        await new Promise(r => setTimeout(r, 1000)); // wait for render
        
        const screenshotPath = path.join(SCREENSHOT_DIR, `${vp.name}_${p.name}.png`);
        await page.screenshot({ path: screenshotPath, fullPage: false });

        auditLog.push({
          viewport: vp.name,
          page: p.name,
          url: p.url,
          status: 'SUCCESS',
          errors: [...errors],
          warnings: [...warnings],
          networkFailures: [...networkFailures]
        });
        console.log(`  -> OK | Errors: ${errors.length}, Network Failures: ${networkFailures.length}`);
        if (errors.length > 0) {
          console.log(`     Errors:`, JSON.stringify(errors, null, 2));
        }
        if (networkFailures.length > 0) {
          console.log(`     Network Failures:`, JSON.stringify(networkFailures, null, 2));
        }
      } catch (e) {
        console.error(`  -> FAILED: ${e.message}`);
        auditLog.push({
          viewport: vp.name,
          page: p.name,
          url: p.url,
          status: 'FAILED',
          error: e.message
        });
      }
    }

    // Now test product detail page dynamically by finding first product link or querying API
    try {
      errors.length = 0;
      networkFailures.length = 0;
      await page.goto(`${BASE_URL}/products`, { waitUntil: 'networkidle2', timeout: 15000 });
      const firstProductLink = await page.evaluate(() => {
        const a = document.querySelector('a[href^="/products/"]');
        return a ? a.getAttribute('href') : null;
      });

      if (firstProductLink) {
        console.log(`Navigating to [${vp.name}] product-detail: ${BASE_URL}${firstProductLink}`);
        await page.goto(`${BASE_URL}${firstProductLink}`, { waitUntil: 'networkidle2', timeout: 15000 });
        await new Promise(r => setTimeout(r, 1000));
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, `${vp.name}_product_detail.png`) });
        auditLog.push({
          viewport: vp.name,
          page: 'product-detail',
          url: firstProductLink,
          status: 'SUCCESS',
          errors: [...errors],
          networkFailures: [...networkFailures]
        });
        console.log(`  -> OK | Errors: ${errors.length}, Network Failures: ${networkFailures.length}`);
      } else {
        console.log(`  -> No product link found on /products`);
      }
    } catch (e) {
      console.error(`  -> Product detail failed: ${e.message}`);
    }

    // Admin pages test (only in desktop to test full dashboard interactions)
    if (vp.name === 'desktop') {
      console.log(`\n--- Testing Admin Workflow in Desktop ---`);
      errors.length = 0;
      networkFailures.length = 0;
      try {
        await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'networkidle2', timeout: 15000 });
        await page.waitForSelector('input[type="email"]', { timeout: 5000 });
        
        await page.type('input[type="email"]', 'superadmin@gmail.com');
        await page.type('input[type="password"]', 'superadmin1234567890');
        
        const submitBtn = await page.$('button[type="submit"]');
        if (submitBtn) {
          await submitBtn.click();
          await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 10000 }).catch(() => {});
          await new Promise(r => setTimeout(r, 1500));
        }

        const adminPages = [
          { name: 'admin-dashboard', url: '/admin' },
          { name: 'admin-orders', url: '/admin/orders' },
          { name: 'admin-products', url: '/admin/products' },
          { name: 'admin-inventory', url: '/admin/inventory' },
          { name: 'admin-flash-sales', url: '/admin/flash-sales' },
          { name: 'admin-users', url: '/admin/users' },
          { name: 'admin-coupons', url: '/admin/coupons' },
          { name: 'admin-trash', url: '/admin/trash' }
        ];

        for (const ap of adminPages) {
          errors.length = 0;
          networkFailures.length = 0;
          console.log(`Navigating to admin page: ${BASE_URL}${ap.url}`);
          await page.goto(`${BASE_URL}${ap.url}`, { waitUntil: 'networkidle2', timeout: 15000 });
          await new Promise(r => setTimeout(r, 1000));
          await page.screenshot({ path: path.join(SCREENSHOT_DIR, `admin_${ap.name}.png`) });
          auditLog.push({
            viewport: 'desktop',
            page: ap.name,
            url: ap.url,
            status: 'SUCCESS',
            errors: [...errors],
            networkFailures: [...networkFailures]
          });
          console.log(`  -> OK | Errors: ${errors.length}, Network Failures: ${networkFailures.length}`);
          if (errors.length > 0) {
            console.log(`     Errors:`, JSON.stringify(errors, null, 2));
          }
          if (networkFailures.length > 0) {
            console.log(`     Network Failures:`, JSON.stringify(networkFailures, null, 2));
          }
        }
      } catch (e) {
        console.error(`  -> Admin testing failed: ${e.message}`);
      }
    }

    await page.close();
  }

  await browser.close();

  const reportPath = path.resolve(import.meta.dirname, '..', '..', 'docs', 'ui_audit_report.json');
  fs.writeFileSync(reportPath, JSON.stringify(auditLog, null, 2));
  console.log(`\nAudit completed! Report saved to ${reportPath}`);
}

runAudit().catch(err => {
  console.error('Audit execution error:', err);
  process.exit(1);
});
