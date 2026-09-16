import { chromium } from '@playwright/test';
import { spawn, spawnSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '../..');
const runtime = resolve(root, 'storage/app/test-runtime', `snippe-checkout-${Date.now()}`);
mkdirSync(`${runtime}/sessions`, { recursive: true });
const env = { ...process.env, SNIPPE_CHECKOUT_RUNTIME: runtime, APP_ENV: 'local', XDEBUG_MODE: 'off' };
const fixture = resolve(root, 'scripts/evidence/snippe-checkout-runtime.php');
function php(action) {
    const result = spawnSync('php', ['-d', 'xdebug.mode=off', fixture, action], { cwd: root, env, encoding: 'utf8', timeout: 120000 });
    if (result.status !== 0) throw new Error(result.stdout + result.stderr);
    return result.stdout;
}
php('seed');
const server = spawn('php', ['-d', 'xdebug.mode=off', '-S', '127.0.0.1:8137', '-t', 'public', fixture], { cwd: root, env, windowsHide: true, stdio: 'ignore' });
const browser = await chromium.launch({ headless: true });
const results = [];
const base = 'http://127.0.0.1:8137';
try {
    for (let i = 0; i < 40; i++) {
        try { if ((await fetch(`${base}/up`)).ok) break; } catch {}
        await new Promise(r => setTimeout(r, 250));
    }
    for (const [width, height] of [[375,812],[768,1024],[1440,900],[639,900],[640,900],[767,900],[1023,900],[1024,900]]) {
        const context = await browser.newContext({ viewport: { width, height } });
        const page = await context.newPage();
        await page.route('https://**/*', route => route.abort());
        await page.goto(`${base}/cart`);
        const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
        await context.request.post(`${base}/cart/items`, { form: { _token: csrf, variant_id: readFileSync(`${runtime}/variant`, 'utf8'), quantity: '2' } });
        await page.goto(`${base}/checkout`);
        const inlineLinkDiagnosis = await page.evaluate(() => {
            const link = document.querySelector('.wt-checkout-summary .btn-outline');
            link.style.display = 'inline';
            const linkBox = link.getBoundingClientRect(), previous = link.previousElementSibling.getBoundingClientRect();
            const overlap = previous.bottom - linkBox.top;
            link.style.removeProperty('display');
            return { originalInlineLinkOverlapsPreviousTextBy: overlap };
        });
        results.push({width,diagnosis:inlineLinkDiagnosis});
        async function capture(state) {
            await page.screenshot({ path: `${runtime}/${width}-${state}.png`, fullPage: true });
            const layout = await page.evaluate(() => {
                const bounds = selector => { const el = document.querySelector(selector); if (!el) return null; const r = el.getBoundingClientRect(); return { x:r.x,y:r.y,width:r.width,height:r.height,bottom:r.bottom,right:r.right }; };
                return { overflow: document.documentElement.scrollWidth > innerWidth, details:bounds('.wt-checkout-details'), summary:bounds('.wt-checkout-summary'), action:bounds('.wt-checkout-action'), heading:document.querySelector('h1')?.textContent };
            });
            if (layout.overflow) throw new Error(`Horizontal overflow ${width} ${state}`);
            if (layout.summary && layout.details) {
                const a=layout.details,b=layout.summary;
                if (Math.min(a.right,b.right)>Math.max(a.x,b.x)+1 && Math.min(a.bottom,b.bottom)>Math.max(a.y,b.y)+1) throw new Error('Form and summary overlap');
                if (width<1024 && layout.action.y < b.bottom) throw new Error('CTA precedes summary');
            }
            results.push({width,height,state,...layout});
        }
        await capture('checkout');
        if (![375,768,1440].includes(width)) { await context.close(); continue; }
        for (const [name,value] of Object.entries({name:'Browser Buyer',email:'browser@example.test',phone:'0712345678',address:'123 Test Street',city:'Dar es Salaam',region:'Dar es Salaam',payer_phone:'123'})) await page.locator(`[name="${name}"]`).fill(value);
        await page.getByRole('button', {name:'Place Order & Pay',exact:true}).click();
        await page.getByText('Enter a valid Tanzanian mobile number, for example 0712 345 678.').first().waitFor();
        await capture('validation');
        await page.locator('[name="payer_phone"]').fill('0712345678');
        // Hold one submission in the browser after the application's submit listener.
        // Inspect its real loading state without racing a document navigation.
        await page.evaluate(() => document.querySelector('#wt-checkout-form').addEventListener('submit', event => event.preventDefault(), {once:true}));
        await page.getByRole('button', {name:'Place Order & Pay',exact:true}).click();
        const loading = await page.evaluate(() => ({disabled:document.querySelector('.wt-checkout-submit').disabled,busy:document.querySelector('#wt-checkout-form').getAttribute('aria-busy')}));
        if (!loading.disabled || loading.busy !== 'true') throw new Error('Missing submission lock');
        await capture('submitting');
        await page.reload();
        for (const [name,value] of Object.entries({name:'Browser Buyer',email:'browser@example.test',phone:'0712345678',address:'123 Test Street',city:'Dar es Salaam',region:'Dar es Salaam',payer_phone:'0712345678'})) await page.locator(`[name="${name}"]`).fill(value);
        await page.getByRole('button', {name:'Place Order & Pay',exact:true}).click();
        await page.waitForURL('**/order-confirmation/**');
        await page.getByRole('heading',{name:'Check your phone',exact:true}).waitFor();
        await capture('pending');
        writeFileSync(`${runtime}/provider-state`, 'completed');
        php('reconcile');
        await page.getByRole('heading',{name:'Payment received',exact:true}).waitFor({timeout:25000});
        await capture('paid');
        await page.goto(`${base}/cart`);
        const token = await page.locator('meta[name="csrf-token"]').getAttribute('content');
        await context.request.post(`${base}/cart/items`,{form:{_token:token,variant_id:readFileSync(`${runtime}/variant`,'utf8'),quantity:'1'}});
        await page.goto(`${base}/checkout`);
        for (const [name,value] of Object.entries({name:'Browser Buyer',email:'browser@example.test',phone:'0712345678',address:'123 Test Street',city:'Dar es Salaam',region:'Dar es Salaam',payer_phone:'0712345678'})) await page.locator(`[name="${name}"]`).fill(value);
        await page.getByRole('button',{name:'Place Order & Pay',exact:true}).click();
        await page.waitForURL('**/order-confirmation/**');
        writeFileSync(`${runtime}/provider-state`, 'expired'); php('reconcile');
        await page.getByText('The payment request has expired.',{exact:true}).waitFor({timeout:25000});
        await capture('expired');
        await context.close();
    }
    writeFileSync(`${runtime}/results.json`, JSON.stringify(results,null,2));
    console.log(JSON.stringify({runtime,captures:results.length,result:'passed'}));
} finally {
    await browser.close();
    server.kill();
}
