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
 for(const width of [1440,1024,768,390]){
  await page.setViewportSize({width,height:900});
  for(const path of ['/index.php','/compta/bank/list.php','/compta/bank/bankentries_list.php?id=1','/compta/bank/transfer.php','/compta/bank/bilan.php','/compta/bank/graph.php?account=1','/compta/bank/card.php?action=create','/societe/list.php','/compta/facture/list.php','/custom/norway/admin/setup.php',
  '/custom/norway/admin/accounting.php',
  '/custom/norway/admin/wizard.php?step=4',
  '/custom/norway/admin/defaults.php',
  '/custom/norway/admin/bankdefaults.php',
  '/custom/norway/admin/journals.php',
  '/admin/company.php',
  '/custom/norway/admin/chart.php',
  '/custom/norway/admin/profile.php',
  '/custom/norway/admin/registry.php','/custom/norway/admin/invoice.php','/custom/norway/admin/audit.php','/custom/norway/admin/pricing.php']){
   const response = await page.goto(base+path); if(response.status()!==200)throw Error('Page status '+response.status()+': '+path);await page.waitForTimeout(150);
   if(await page.locator('input[name=username]').count())throw Error('Synthetic authentication required: '+path);
   const result=await page.evaluate(()=>({vw:innerWidth,width:document.documentElement.scrollWidth,offenders:[...document.querySelectorAll('#id-right *')].filter(e=>{let r=e.getBoundingClientRect();return r.width>0&&r.right>innerWidth+2&&!e.closest('.div-table-responsive,.div-table-responsive-no-min')}).slice(0,8).map(e=>({tag:e.tagName,cls:e.className,right:Math.round(e.getBoundingClientRect().right)}))}));
   console.log(JSON.stringify({viewport:width,path,...result}));
   if(result.width>width+1||result.offenders.length)throw Error('Overflow remains: '+path+' at '+width);
   if(!await page.locator('link[href*="layout-0.4.0.css"]').count())throw Error('Stylesheet not registered');
  }
 }
 await browser.close();
})().catch(e=>{console.error(e.message);process.exit(1)});
