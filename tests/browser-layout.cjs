// SPDX-License-Identifier: GPL-3.0-or-later
// Optional browser regression against the local synthetic installation only.
const {chromium}=require('playwright');

(async()=>{
 if(process.env.NORWAY_SYNTHETIC_TEST !== '1' || !process.env.TEST_ADMIN_PASSWORD)throw Error('Synthetic test environment required');
 const base=process.env.NORWAY_LAYOUT_BASE_URL || 'http://127.0.0.1:8087';
 const url=new URL(base);
 if(!['127.0.0.1','localhost'].includes(url.hostname))throw Error('Local synthetic installation required');
 const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
 const page=await browser.newPage();
 await page.goto(base+'/index.php');
 await page.locator('input[name=username]').fill('testadmin');
 await page.locator('input[name=password]').fill(process.env.TEST_ADMIN_PASSWORD);
 await Promise.all([page.waitForURL(u=>!u.href.includes('actionlogin')),page.locator('input[type=submit]').first().click()]);
 await page.goto(base+'/custom/norway/admin/invoice.php');
 const invoicePath=await page.locator('a[href*="invoice.php?id="]').first().getAttribute('href');
 if(!invoicePath)throw Error('Synthetic invoice missing for amount layout');
 const invoicePaths=await page.locator('a[href*="invoice.php?id="]').evaluateAll(nodes=>nodes.map(node=>node.getAttribute('href')));
 let ledgerPath=null;
 for(const candidate of invoicePaths){
  await page.goto(base+candidate+'&ledger=1');
  if(await page.locator('th').filter({hasText:/Hovedboks-ID|Bookkeeping ID/}).count()){ledgerPath=candidate+'&ledger=1';break;}
 }
 if(!ledgerPath)throw Error('Posted synthetic invoice missing for ledger table layout');
 const evidencePath=ledgerPath.replace('/invoice.php','/evidence.php').replace('&ledger=1','');
 for(const width of [1440,1024,768,390]){
  await page.setViewportSize({width,height:900});
  for(const path of ['/index.php','/compta/bank/list.php','/compta/bank/bankentries_list.php?id=1','/compta/bank/transfer.php','/compta/bank/bilan.php','/compta/bank/graph.php?account=1','/compta/bank/card.php?action=create','/societe/list.php','/compta/facture/list.php','/custom/norway/admin/setup.php',
  '/custom/norway/admin/accounting.php',
  ...Array.from({length:11},(_,i)=>'/custom/norway/admin/wizard.php?step='+(i+1)),
  '/custom/norway/admin/defaults.php',
  '/custom/norway/admin/bankdefaults.php',
  '/custom/norway/admin/documents.php',
  '/custom/norway/admin/numbering.php',
  '/custom/norway/admin/periods.php',
  '/custom/norway/admin/vat.php',
  '/custom/norway/admin/registration.php',
  '/custom/norway/admin/taxmappings.php',
  '/custom/norway/admin/invoiceperiod.php?start=2026-10-01&end=2026-10-01',
  '/custom/norway/admin/journals.php',
  '/admin/company.php',
  '/custom/norway/admin/chart.php',
  '/custom/norway/admin/profile.php',
  invoicePath, ledgerPath, evidencePath, ...(process.env.NORWAY_LAYOUT_COMPARE_ID ? ['/custom/norway/admin/evidence.php?id='+process.env.NORWAY_LAYOUT_COMPARE_ID+'&copy_revision=1'] : []), invoicePath+'&mapping_revision=1', invoicePath+'&mapping=1&mapsource=LOCAL25&maptarget=3&mapstart=2026-01-01&mapend=2026-12-31', '/custom/norway/admin/registry.php','/custom/norway/admin/invoice.php','/custom/norway/admin/audit.php','/custom/norway/admin/pricing.php']){
   const response = await page.goto(base+path); if(response.status()!==200)throw Error('Page status '+response.status()+': '+path);await page.waitForTimeout(150);
   if(await page.locator('input[name=username]').count())throw Error('Synthetic authentication required: '+path);
   for(const expanded of path.includes('/wizard.php') ? [false,true] : [false]){
    if(expanded)await page.locator('.norway-wizard details').evaluateAll(nodes=>nodes.forEach(node=>node.open=true));
   const result=await page.evaluate(()=>({vw:innerWidth,width:document.documentElement.scrollWidth,offenders:[...document.querySelectorAll('#id-right *')].filter(e=>{let r=e.getBoundingClientRect();return r.width>0&&r.right>innerWidth+2&&!e.closest('.div-table-responsive,.div-table-responsive-no-min')}).slice(0,8).map(e=>({tag:e.tagName,cls:e.className,right:Math.round(e.getBoundingClientRect().right)}))}));
   console.log(JSON.stringify({viewport:width,path,expanded,...result}));
   if(result.width>width+1||result.offenders.length)throw Error('Overflow remains: '+path+' at '+width);
   if(!await page.locator('link[href*="layout-0.4.0.css"]').count())throw Error('Stylesheet not registered');
   }
  }
  await page.goto(base+evidencePath);
  await page.selectOption('select[name=mapping_revision]','1');
  await Promise.all([page.waitForNavigation(),page.locator('button[type=submit]').first().click()]);
  if(!await page.locator('input[name=preview_nonce]').count())throw Error('Evidence preview confirmation missing');
  await page.locator('.norway-page details').evaluateAll(nodes=>nodes.forEach(node=>node.open=true));
  const result=await page.evaluate(()=>({vw:innerWidth,width:document.documentElement.scrollWidth,offenders:[...document.querySelectorAll('#id-right *')].filter(e=>{let r=e.getBoundingClientRect();return r.width>0&&r.right>innerWidth+2&&!e.closest('.div-table-responsive,.div-table-responsive-no-min')}).slice(0,8).map(e=>({tag:e.tagName,cls:e.className,right:Math.round(e.getBoundingClientRect().right)}))}));
  console.log(JSON.stringify({viewport:width,path:evidencePath,expanded:true,...result}));
  if(result.width>width+1||result.offenders.length)throw Error('Expanded evidence overflow at '+width);
  const numberingPath='/custom/norway/admin/numbering.php';
  await page.goto(base+numberingPath);
  await Promise.all([page.waitForNavigation(),page.locator('button[type=submit]').first().click()]);
  if(!await page.locator('input[name=preview_nonce]').count())throw Error('Numbering confirmation preview missing');
  for(const name of ['invoice_start','credit_start','proposal_start','customer_start','supplier_start'])if(!await page.locator('input[name='+name+']').count())throw Error('Numbering series missing: '+name);
  const numberingResult=await page.evaluate(()=>({vw:innerWidth,width:document.documentElement.scrollWidth,offenders:[...document.querySelectorAll('#id-right *')].filter(e=>{let r=e.getBoundingClientRect();return r.width>0&&r.right>innerWidth+2&&!e.closest('.div-table-responsive,.div-table-responsive-no-min')}).slice(0,8).map(e=>({tag:e.tagName,cls:e.className,right:Math.round(e.getBoundingClientRect().right)}))}));
  console.log(JSON.stringify({viewport:width,path:numberingPath,preview:true,...numberingResult}));
  if(numberingResult.width>width+1||numberingResult.offenders.length)throw Error('Numbering preview overflow at '+width);

 }
 await browser.close();
})().catch(e=>{console.error(e.message);process.exit(1)});
