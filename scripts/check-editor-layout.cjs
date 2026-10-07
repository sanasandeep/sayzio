const fs=require('fs'),assert=require('assert'),{chromium}=require('playwright');
(async()=>{
 const header=fs.readFileSync('artifacts/1inme/resources/views/user/links/partials/editor-header.blade.php','utf8');
 const shared=header.match(/<style>\s*([\s\S]*?\.editor-workspace[\s\S]*?)<\/style>/)[1];
 const editor=fs.readFileSync('artifacts/1inme/resources/views/user/links/biolink-editor.blade.php','utf8');
 const start=editor.indexOf('        #editorWorkCol {'),end=editor.indexOf('        /* Stacked (sub-lg)',start);
 const css=shared+editor.slice(start,end);
 const browser=await chromium.launch();const page=await browser.newPage();
 for(const width of [375,900,1024,1440]){
  await page.setViewportSize({width,height:900});let reference;
  for(const tab of ['blocks','settings','ai']){
   const work=tab==='blocks'?'<div id="editorWorkCol"><div id="editorPaletteCol">Palette</div><div id="editorCanvasCol">Toolbar and blocks</div></div>':'<div>'+tab+' controls</div>';
   await page.setContent(`<style>*{box-sizing:border-box}body{margin:20px}${css}.preview{height:600px}@media(max-width:899px){.preview{display:none}}</style><div class="editor-workspace">${work}<div class="preview" id="editorPreviewCol">Preview</div></div>`);
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
   if(width>=900){const bounds=await page.locator('.preview').boundingBox();if(reference){assert(Math.abs(bounds.x-reference.x)<1);assert(Math.abs(bounds.width-reference.width)<1);assert.equal(bounds.y,reference.y)}reference=bounds;}
  }
 }
 console.log('Preview position and width match across Blocks, Settings and AI at 900/1024/1440px; no overflow at 375px.');await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
