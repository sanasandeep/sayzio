const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
test('catalog filters combine category, kind, location, price and search', () => {
    const listeners = [];
    const controls = Array.from({length:5},()=>({value:'',addEventListener:(event,fn)=>listeners.push(fn)}));
    const listings = [
        {dataset:{search:'garden flat parking',kind:'rent',category:'1',location:'City',price:'25000'}},
        {dataset:{search:'office tower',kind:'sale',category:'2',location:'Town',price:'100000'}},
        {dataset:{search:'garden villa',kind:'rent',category:'1',location:'City',price:''}},
    ];
    const sections = listings.map(listing=>({querySelectorAll:()=>[listing]}));
    const empty={hidden:true};const count={textContent:''};
    const ids=['catalog-search','catalog-kind','catalog-category','catalog-location','catalog-price'];
    const root={querySelectorAll:q=>q==='[data-listing]'?listings:q==='[data-catalog-section]'?sections:[]};
    const document={getElementById:id=>id==='listing-catalog'?root:id==='catalog-no-results'?empty:id==='catalog-result-count'?count:controls[ids.indexOf(id)]};
    vm.runInNewContext(fs.readFileSync('public/js/listing-catalog.js','utf8'),{document});
    controls[0].value=' GARDEN ';controls[1].value='rent';controls[2].value='1';controls[3].value='City';controls[4].value='30000';listeners[0]();
    assert.equal(listings[0].hidden,false);assert.equal(listings[1].hidden,true);assert.equal(listings[2].hidden,true);assert.equal(count.textContent,'1 matching listings');
    controls[4].value='0';listeners[0]();assert.equal(empty.hidden,false);
    controls.forEach(c=>c.value='');listeners[0]();assert.equal(count.textContent,'3 matching listings');assert.equal(sections.every(s=>!s.hidden),true);
});
test('inquiry button expands the selected form and focuses name',()=>{
    let click;let focused=false;
    const button={dataset:{openInquiry:'inquiry-7'},addEventListener:(e,fn)=>click=fn};
    const form={open:false,querySelector:()=>({focus:()=>focused=true})};
    const document={getElementById:id=>id==='listing-catalog'?{querySelectorAll:()=>[button]}:id==='inquiry-7'?form:null};
    vm.runInNewContext(fs.readFileSync('public/js/listing-catalog.js','utf8'),{document});
    click();assert.equal(form.open,true);assert.equal(focused,true);
});
