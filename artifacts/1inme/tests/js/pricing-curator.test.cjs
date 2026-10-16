const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
const path=require('node:path');
function setup(){
 const data=new Map();const context={window:{},sessionStorage:{getItem:k=>data.get(k)||null,setItem:(k,v)=>data.set(k,v),removeItem:k=>data.delete(k)}};
 vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../public/js/pricing-curator.js'),'utf8'),context);
 const plan=(id,name,pages,seats,cost,extras={})=>({id,slug:name.toLowerCase(),name,features:{max_biolinks:pages,max_seats_per_workspace:seats,...extras},prices:{USD:{monthly:{amount_minor:cost},annual:{amount_minor:cost*10}}}});
 const catalogue=[plan(1,'Free',1,1,0),plan(2,'Bio',3,1,400),plan(3,'Creator',25,3,1200,{custom_domains:true}),plan(4,'Professional',100,10,2500,{custom_domains:true,paid_forms:true,api_access:true})];
 const state=context.window.sayzioPricingCurator(catalogue,false);state.currency='USD';state.cycle='monthly';return {state,data,catalogue,context};
}
test('lowest priced exact match comes first, without upselling free requirements',()=>{const {state}=setup();assert.equal(state.matchCatalogue()[0].name,'Free');state.answers.pages='3';assert.equal(state.matchCatalogue()[0].name,'Bio');state.answers.domain=true;assert.equal(state.matchCatalogue()[0].name,'Creator');});
test('developer and paid-form requirements exclude plans without those features',()=>{const {state}=setup();state.answers.purpose='developer';assert.equal(state.matchCatalogue()[0].name,'Professional');state.answers.purpose='bio';state.answers.payments=true;assert.equal(state.matchCatalogue()[0].name,'Professional');});
test('unmet needs are labelled, never presented as exact matches',()=>{const {state}=setup();state.answers.seats='25';const matches=state.matchCatalogue();assert.ok(matches.length);assert.ok(matches.every(p=>p.exact===false));assert.match(matches[0].reason,/team seats/);});
test('unlimited capacity meets large requirements',()=>{const {state,catalogue}=setup();catalogue[3].features.max_seats_per_workspace=-1;state.answers.seats='25';assert.equal(state.matchCatalogue()[0].name,'Professional');assert.equal(state.matchCatalogue()[0].exact,true);});
test('uses the selected currency and billing cadence to rank plans',()=>{const {state,catalogue}=setup();catalogue[1].prices.INR={monthly:{amount_minor:100},annual:{amount_minor:9999}};catalogue[2].prices.INR={monthly:{amount_minor:999},annual:{amount_minor:200}};state.currency='INR';state.answers.pages='3';assert.equal(state.matchCatalogue()[0].name,'Bio');state.cycle='annual';assert.equal(state.matchCatalogue()[0].name,'Creator');});
test('session restore recomputes current results, and reset clears only matcher state',()=>{const {state,data,context,catalogue}=setup();state.answers.pages='3';state.expanded=true;state.saveCuration(true);data.set('other','keep');const restored=context.window.sayzioPricingCurator(catalogue,false);restored.restoreCuration();assert.equal(restored.answers.pages,'3');assert.equal(restored.expanded,true);assert.equal(restored.matches[0].name,'Bio');restored.resetCuration();assert.equal(data.has('sayzio.pricing.matcher.v1'),false);assert.equal(data.get('other'),'keep');});
test('blocked or malformed session storage does not break the page',()=>{const {state,context}=setup();context.sessionStorage.getItem=()=>'{bad json';assert.doesNotThrow(()=>state.restoreCuration());context.sessionStorage.setItem=()=>{throw Error('blocked')};assert.doesNotThrow(()=>state.saveCuration(true));});
