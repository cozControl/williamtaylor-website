import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { readFile, writeFile, stat } from 'node:fs/promises';
import path from 'node:path';
const out=path.resolve('storage/app/ui-frontend-1c-evidence'), pub=path.resolve('public'), origin='http://william.taylor';
const browser=await chromium.launch();
const results=[]; const errors=[];
try {
 const context=await browser.newContext(); let state='visible';
 await context.route('**/*',async route=>{
  const req=route.request(), url=new URL(req.url());
  if(req.isNavigationRequest() && url.origin===origin) return route.fulfill({contentType:'text/html',body:await readFile(path.join(out,state+'.html'))});
  if(url.origin===origin || url.hostname==='media.base44.com') {
   const file=url.hostname==='media.base44.com'?path.join(pub,'website/images',path.basename(url.pathname)):path.resolve(pub,'.'+decodeURIComponent(url.pathname));
   if(file.startsWith(pub+path.sep)) {try{if((await stat(file)).isFile()) return route.fulfill({path:file});}catch{}}
  }
  if(req.method()!=='GET'||url.pathname.startsWith('/api/')) return route.fulfill({json:{}});
  return route.continue();
 });
 const page=await context.newPage(); page.on('pageerror',e=>errors.push(e.message));
 for(const width of [1440,1366,1024,768,390,375,320]) {
  await page.setViewportSize({width,height:900}); await page.goto(origin);await page.waitForTimeout(1800);
  const sale=page.locator('main [data-homepage-hot-sale]'); await sale.scrollIntoViewIfNeeded();await page.waitForTimeout(700);
  await page.waitForFunction(()=>document.querySelector('main [data-homepage-hot-sale] video')?.readyState>=2,{},{timeout:15000}).catch(()=>{});
  const layout=await sale.evaluate(s=>({x:s.getBoundingClientRect().x,width:s.getBoundingClientRect().width,headings:s.querySelectorAll('h2,.section-subtitle').length,overflow:document.documentElement.scrollWidth>innerWidth,tiles:[...s.querySelectorAll('[data-hot-sale-tile]')].map(t=>{const r=t.getBoundingClientRect(),m=t.querySelector('img,video');return {x:r.x,y:r.y,width:r.width,height:r.height,cover:getComputedStyle(m).objectFit,loaded:m.tagName==='IMG'?m.complete&&m.naturalWidth>0:m.readyState>=2,time:m.tagName==='VIDEO'?m.currentTime:null,title:t.querySelector('h3').textContent.trim(),href:t.querySelector('a').href};})}));
  assert.equal(layout.x,0);assert.equal(layout.width,width);assert.equal(layout.headings,2);assert.equal(layout.overflow,false);assert.equal(layout.tiles.length,3);
  const [a,b,c]=layout.tiles;for(const t of layout.tiles)assert.equal(t.cover,'cover');
  if(width>=1024){assert.equal(a.width,width/2);assert.equal(a.y,b.y);assert.equal(a.height,b.height);assert.equal(a.height,900);assert.equal(c.width,width);assert.equal(c.y,a.y+a.height+1);}else{for(const t of layout.tiles)assert.equal(t.width,width);assert.equal(b.y,a.y+a.height+1);assert.equal(c.y,b.y+b.height+1);}
  await sale.screenshot({path:path.join(out,`hot-sale-${width}.png`)});
  await sale.evaluate(s=>scrollTo({top:s.getBoundingClientRect().top+scrollY+1,behavior:'instant'}));await page.waitForTimeout(400);await page.screenshot({path:path.join(out,`hot-sale-viewport-${width}.png`)});
  const journey=page.getByRole('heading',{name:'Follow the Journey',exact:true});await journey.scrollIntoViewIfNeeded();await page.waitForTimeout(600);assert.equal(await journey.count(),1);
  results.push({width,...layout});
 }
 await page.locator('main [data-hot-sale-tile="1"] a').click();assert.equal(page.url(),origin+'/shop?sort=newest');
 state='hidden';await page.goto(origin);await page.waitForTimeout(2000);assert.equal(await page.getByRole('heading',{name:'Follow the Journey',exact:true}).count(),0);assert.equal(await page.locator('main [data-homepage-follow-the-journey]').count(),0);
 await page.evaluate(()=>scrollTo(0,document.body.scrollHeight));await page.screenshot({path:path.join(out,'journey-hidden.png')});
 state='restored';await page.goto(origin);await page.waitForTimeout(2000);await page.getByRole('heading',{name:'Follow the Journey',exact:true}).scrollIntoViewIfNeeded();await page.waitForTimeout(500);await page.screenshot({path:path.join(out,'journey-restored.png')});
 assert.equal(await page.getByRole('heading',{name:'Follow the Journey',exact:true}).count(),1);
 state='admin';await page.setViewportSize({width:1440,height:1000});await page.goto(origin+'/admin/homepage');const row=page.locator('[data-homepage-section="11"]');await row.scrollIntoViewIfNeeded();assert.equal(await row.getByRole('switch').getAttribute('aria-checked'),'false');await page.screenshot({path:path.join(out,'admin-section-11.png')});
 await writeFile(path.join(out,'browser-results.json'),JSON.stringify({results,visibility:{hidden:true,restored:true,adminOff:true},errors},null,2));console.log(JSON.stringify({results,errors}));
}finally{await browser.close();}
