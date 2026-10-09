const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function picker(type = '') {
    const source = fs.readFileSync('resources/views/user/links/create.blade.php', 'utf8').split('<script>')[1].split('</script>')[0];
    let factory;
    vm.runInNewContext(source, { document: { addEventListener: (_, callback) => callback() }, window: { Alpine: { data: (_, value) => { factory = value; } } } });
    const component = factory({ type, typeMeta: { url: {label:'Short Link'}, biolink: {label:'Link in Bio'}, education: {business:true,label:'Education'}, file: {label:'File'} }, cats: { catalog: [{value:'education',label:'Education',desc:'Courses'}, {value:'file',label:'File',desc:'Download'}] } });
    component.init();
    return component;
}
test('business selection cannot accidentally continue with an unrelated remembered type', () => {
    const p = picker('file'); assert.equal(p.group, 'more');
    p.openGroup('business'); assert.equal(p.type, '');
    assert.equal(p.matches('Education', 'Courses', 'catalog', 'education'), true);
    assert.equal(p.matches('File', 'Download', 'catalog', 'file'), false);
    p.type = 'education'; p.openGroup('more'); assert.equal(p.type, '');
});
test('primary choices close expanded options and clear search', () => {
    const p = picker('education'); assert.equal(p.group, 'business');
    p.search = 'something'; p.pickPrimary('url');
    assert.equal(p.type, 'url'); assert.equal(p.group, ''); assert.equal(p.search, '');
});
test('search finds only types in the selected group and reports no matches', () => {
    const p = picker(); p.openGroup('more'); p.search = 'download'; assert.equal(p.anyMatch(), true);
    p.search = 'courses'; assert.equal(p.anyMatch(), false);
});
