import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import { chromium, expect } from '@playwright/test';

const origin = 'http://william.taylor';
const browser = await chromium.launch({ headless: true });
const checks = [];
try {
    const context = await browser.newContext();
    await context.route('**/*', route => {
        const request = route.request();
        return request.method() === 'GET' && new URL(request.url()).origin === origin
            ? route.continue() : route.abort();
    });
    const page = await context.newPage();
    for (const [path, width] of [['/', 1440], ['/', 375], ['/shop', 1440]]) {
        await page.setViewportSize({ width, height: 900 });
        const response = await page.goto(`${origin}${path}`, { waitUntil: 'networkidle' });
        assert.equal(response.status(), 200);
        const footer = page.locator('#root footer');
        await expect(footer.locator('a[href="mailto:hello@williamtaylor.co.tz"]')).toHaveText('hello@williamtaylor.co.tz');
        await expect(footer.locator('a[href="tel:+255724954876"]')).toHaveText('+255 724 954 876');
        await expect(page.locator('#root a[aria-label="Chat on WhatsApp"]')).toHaveAttribute('href', /^https:\/\/wa\.me\/255724954876\?text=/);
        assert.doesNotMatch(await page.locator('#root').innerText(), /info@williamtaylor\.co\.tz|255 656 464 876|hello@example\.com|0760123321/);
        checks.push({ path, width, contactLinks: 'passed' });
    }

    // Exercise Contact/FAQ fragments recreated later by the imported runtime.
    const probe = await context.newPage();
    const details = { email: 'hello@williamtaylor.co.tz', telephone: '+255724954876', telephoneDisplay: '+255 724 954 876', whatsAppUrl: 'https://wa.me/255724954876' };
    await probe.setContent(`<div id="root"><a id="email" href="mailto:info@williamtaylor.co.tz?subject=Help">info@williamtaylor.co.tz</a><a id="phone" href="tel:+255656464876">+255 656 464 876</a><a id="chat" href="https://wa.me/255656464876?text=Hello">WhatsApp</a><input id="customer" value="info@williamtaylor.co.tz"><textarea id="message">+255 656 464 876</textarea></div><script id="storefront-contact-details" type="application/json">${JSON.stringify(details)}</script>`);
    await probe.addScriptTag({ content: await fs.readFile('public/website/js/storefront-contact.js', 'utf8') });
    await expect(probe.locator('#email')).toHaveAttribute('href', 'mailto:hello@williamtaylor.co.tz?subject=Help');
    await expect(probe.locator('#phone')).toHaveAttribute('href', 'tel:+255724954876');
    await expect(probe.locator('#chat')).toHaveAttribute('href', 'https://wa.me/255724954876?text=Hello');
    await probe.evaluate(() => {
        const faq = document.createElement('p');
        faq.id = 'faq';
        faq.textContent = 'Contact info@williamtaylor.co.tz or WhatsApp +255 XXX XXX XXX.';
        document.getElementById('root').append(faq);
        document.getElementById('phone').setAttribute('href', 'tel:+255656464876');
    });
    await expect(probe.locator('#faq')).toHaveText('Contact hello@williamtaylor.co.tz or WhatsApp +255 724 954 876.');
    await expect(probe.locator('#phone')).toHaveAttribute('href', 'tel:+255724954876');
    await probe.evaluate(() => { document.getElementById('faq').firstChild.nodeValue = 'Call +255 656 464 876.'; });
    await expect(probe.locator('#faq')).toHaveText('Call +255 724 954 876.');
    await expect(probe.locator('#customer')).toHaveValue('info@williamtaylor.co.tz');
    await expect(probe.locator('#message')).toHaveValue('+255 656 464 876');
    checks.push({ importedContactAndFaq: 'passed', lateRenderAndLinkReset: 'passed', customerInputsPreserved: true });
    await context.close();
    console.log(JSON.stringify({ passed: true, checks }, null, 2));
} finally {
    await browser.close();
}
