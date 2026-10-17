// SPDX-License-Identifier: GPL-3.0-or-later
// Read-only browser regression against the local synthetic installation.
const {chromium}=require('playwright');
(async()=>{
 if(process.env.NORWAY_SYNTHETIC_TEST!=='1'||!process.env.TEST_ADMIN_PASSWORD)throw Error('Synthetic test environment required');
 const base=process.env.NORWAY_LAYOUT_BASE_URL||'http://127.0.0.1:8087';
 if(!['127.0.0.1','localhost'].includes(new URL(base).hostname))throw Error('Local synthetic installation required');
 const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
 try{
  const page=await browser.newPage();await page.goto(base+'/index.php');
  await page.locator('input[name=username]').fill('testadmin');await page.locator('input[name=password]').fill(process.env.TEST_ADMIN_PASSWORD);
  await page.locator('input[type=submit]').first().click();await page.waitForLoadState('networkidle');
  await page.goto(base+'/societe/list.php');
  const partyLink=await page.locator('a[href*="/societe/card.php?socid="]').first().getAttribute('href');
  const partyId=partyLink&&partyLink.match(/[?&]socid=([0-9]+)/);if(!partyId)throw Error('Synthetic customer missing');
  const paths=['/compta/facture/card.php?action=create&type=2&socid='+partyId[1],'/custom/norway/admin/wizard.php','/custom/norway/admin/setup.php','/custom/norway/admin/accounting.php','/custom/norway/admin/numbering.php','/admin/company.php','/compta/bank/list.php','/compta/bank/card.php?action=create','/societe/list.php','/product/list.php','/compta/facture/list.php'];
  for(const width of [1440,1024,768,390])for(const lang of ['nb_NO','en_US']){
   await page.setViewportSize({width,height:900});
   for(const path of paths){
    const response=await page.goto(base+path+(path.includes('?')?'&':'?')+'lang='+lang);
    if(response.status()!==200||await page.locator('input[name=username]').count())throw Error('Page unavailable: '+path);
    if(!await page.locator('link[href*="layout-0.5.0.css"]').count())throw Error('Module style missing');
    if(path.includes('type=2')){
     const row=page.locator('#norway-original-reference');
     if(!await row.isVisible())throw Error('External original reference hidden');
     if(width<=700){const overlap=await page.locator('.norway-invoice-create > tbody > tr').evaluateAll(rows=>rows.some(row=>{const box=row.getBoundingClientRect();return box.height>0&&[...row.querySelectorAll('td,input,textarea,.select2-container')].some(child=>child.getBoundingClientRect().height>0&&child.getBoundingClientRect().bottom>box.bottom+2);}));if(overlap)throw Error('Invoice creation fields overlap');}

     const source=page.locator('select[name=fac_avoir]');
     const options=await source.locator('option').evaluateAll(nodes=>nodes.map(n=>n.value).filter(v=>parseInt(v,10)>0));
     if(options.length){await source.selectOption(options[0]);if(await row.isVisible())throw Error('External reference shown for an internal original');await source.selectOption('-1');if(!await row.isVisible())throw Error('External reference not restored');}
    }
    if(path.includes('/wizard.php')){
     const overview=page.locator('.norway-setup-overview');
     if(await overview.locator('tr[data-setup-task]').count()!==18||await page.locator('.norway-wizard form').count())throw Error('Overview tasks/forms');
     for(const task of ['Ehf','Saft','Tax'])if(await overview.locator('[data-setup-task="'+task+'"] [data-setup-status="Unavailable"]').count()!==1)throw Error('Missing support hidden: '+task);
     const hidden=await overview.locator('a').evaluateAll(nodes=>nodes.filter(n=>{const b=n.getBoundingClientRect();return b.width<1||b.right>innerWidth+2;}).length);
     if(hidden)throw Error('Overview links outside viewport');
     const overlap=await overview.locator('tbody tr').evaluateAll(rows=>rows.some(row=>[...row.querySelectorAll('p,a')].some(child=>child.getBoundingClientRect().bottom>row.getBoundingClientRect().bottom+2)));
     if(overlap)throw Error('Overview content overlaps the next task');
    }
    const result=await page.evaluate(()=>({width:document.documentElement.scrollWidth,offenders:[...document.querySelectorAll('#id-right *')].filter(e=>{const r=e.getBoundingClientRect();return r.width>0&&r.right>innerWidth+2&&!e.closest('.div-table-responsive,.div-table-responsive-no-min');}).length}));
    if(result.width>width+2||result.offenders)throw Error('Overflow: '+path+' '+width+' '+lang);
   }
   console.log(JSON.stringify({width,lang,pages:paths.length,pass:true}));
  }
  await page.goto(base+'/custom/norway/admin/wizard.php?lang=nb_NO');
  if(process.env.NORWAY_LAYOUT_SCREENSHOT_DIR){
   const out=process.env.NORWAY_LAYOUT_SCREENSHOT_DIR;
   await page.screenshot({path:out+'/setup-overview-mobile.png'});
   await page.locator('[data-setup-task="Ehf"]').scrollIntoViewIfNeeded();await page.screenshot({path:out+'/setup-overview-gaps.png'});
   await page.setViewportSize({width:1440,height:1000});await page.goto(base+'/custom/norway/admin/wizard.php?lang=nb_NO');await page.screenshot({path:out+'/setup-overview-desktop.png'});
  }
 }finally{await browser.close();}
})().catch(e=>{console.error(e.message);process.exit(1)});
