// Exercise the actual click handlers and storage with two independent providers.
const vm = require('node:vm');
const fs = require('node:fs');
const assert = require('node:assert/strict');
function run(saved = {}) {
  const stored = {...saved}, listeners = {}, frames = [];
  let reloads = 0;
  function box(service) {
    const nodes = {
      '.bbmc-placeholder': {hidden: false}, '.bbmc-revoke': {hidden: true},
      '.bbmc-remember': {checked: false}, '.bbmc-status': {textContent: ''},
      '.bbmc-target': {hidden: true, append(frame) {frames.push({service, src: frame.src});}}
    };
    const own = {};
    return {dataset: {service, bbmc: JSON.stringify({kind: 'iframe', src: 'https://example.com/'+service})}, querySelector: key => nodes[key], nodes, own, style: {setProperty() {}}, addEventListener: (type, fn) => {own[type] = fn;}};
  }
  const maps = box('maps'), instagram = box('instagram');
  const context = {
    document: {querySelectorAll: () => [maps, instagram], addEventListener: (type, fn) => {listeners[type] = fn;}, createElement: () => ({})},
    window: {addEventListener: () => {}}, location: {reload: () => reloads++},
    localStorage: {getItem: key => stored[key] ?? null, setItem: (key,value) => {stored[key]=value;}, removeItem: key => {delete stored[key];}}
  };
  vm.runInNewContext(fs.readFileSync('assets/maps.js','utf8'),context);
  function click(box, action) {
    const button = {dataset: {}, classList: {contains: name => name === action}, closest: () => box};
    listeners.click({target: {closest: () => button}});
  }
  return {maps, instagram, frames, stored, click, reloads: () => reloads};
}
let env = run();
assert.equal(env.frames.length,0);
// Clicks on the checkbox label and links stay inside the box (grid plugins cancel them further up).
let stopped = 0;
env.maps.own.click({target: {closest: sel => sel === 'label, a' ? {} : null}, stopPropagation: () => stopped++});
env.maps.own.click({target: {closest: () => null}, stopPropagation: () => stopped++});
assert.equal(stopped,1);
env.instagram.nodes['.bbmc-remember'].checked=true;
env.click(env.instagram,'bbmc-allow');
assert.deepEqual(env.frames.map(f=>f.service),['instagram']);
assert.equal(env.maps.nodes['.bbmc-placeholder'].hidden,false);
assert.ok(env.stored['bbmc-instagram-consent-v1']);
assert.equal(env.stored['bbmc-consent-v1'],undefined);
env.click(env.maps,'bbmc-allow');
assert.equal(env.frames.length,2);
assert.equal(env.stored['bbmc-consent-v1'],undefined);
env.click(env.instagram,'bbmc-revoke');
assert.equal(env.stored['bbmc-instagram-consent-v1'],undefined);
assert.equal(env.reloads(),1);
env = run({'bbmc-consent-v1':JSON.stringify({expires:Date.now()+60000})});
assert.deepEqual(env.frames.map(f=>f.service),['maps']);
env = run({'bbmc-instagram-consent-v1':JSON.stringify({expires:Date.now()-1000})});
assert.equal(env.frames.length,0);
assert.equal(env.stored['bbmc-instagram-consent-v1'],undefined);
console.log('Consent: independent providers, persistence, revocation, existing maps consent and expiration passed');
