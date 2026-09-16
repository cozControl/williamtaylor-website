import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import {writeFile} from 'node:fs/promises';
const browser=await chromium.launch(),results=[];
try{
 const page=await browser.newPage();
 for(const width of [1440,390]){
  await page.setViewportSize({width,height:900});const response=await page.goto('http://william.taylor');assert.equal(response.status(),200);await page.waitForTimeout(1800);
  const sale=page.locator('main [data-homepage-hot-sale]');await sale.scrollIntoViewIfNeeded();
  const result=await sale.evaluate(s=>({width:s.getBoundingClientRect().width,headingCount:s.querySelectorAll('h2').length,tiles:[...s.querySelectorAll('[data-hot-sale-tile]')].map(t=>({y:t.getBoundingClientRect().y,width:t.getBoundingClientRect().width,height:t.getBoundingClientRect().height,title:t.querySelector('h3').textContent.trim()})),overflow:document.documentElement.scrollWidth>innerWidth}));
  assert.equal(result.width,width);assert.equal(result.headingCount,1);assert.equal(result.tiles.length,3);assert.equal(result.overflow,false);assert.equal(result.tiles[0].width,width>=1024?width/2:width);assert.equal(result.tiles[2].width,width);const [a,b,c]=result.tiles;assert.equal(c.y-(width>=1024?a.y+a.height:b.y+b.height),1);await page.screenshot({path:`storage/app/ui-frontend-1c-evidence/revised-${width}.png`});results.push({viewport:width,...result});
 }
 console.log(JSON.stringify(results));await writeFile('storage/app/ui-frontend-1c-evidence/live-results.json',JSON.stringify(results,null,2));
}finally{await browser.close();}
