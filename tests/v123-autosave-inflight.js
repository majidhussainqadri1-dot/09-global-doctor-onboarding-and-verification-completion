'use strict';
/** File09 V12.3: real browser-script VM reproduction of autosave lost edits. */
const fs=require('fs');
const vm=require('vm');
const assert=require('assert');
const script=fs.readFileSync(require('path').join(__dirname,'../assets/js/onboarding.js'),'utf8');
async function run(){
 const listeners={};const timers=new Map();let timerID=0;
 const posts=[];const responses=[];
 const input={name:'display_name',type:'text',value:'A'};
 const attributes={'data-application-id':'42','data-row-version':'1'};
 const form={
   getAttribute(n){return attributes[n]||'';},
   setAttribute(n,v){attributes[n]=String(v);},
   querySelectorAll(s){return s==='[data-gdo-profile]'?[input]:[];},
   addEventListener(n,fn){listeners[n]=fn;}
 };
 const status={textContent:''};
 const sandbox={
   window:{gdoOnboarding:{restUrl:'/wp-json/file09',nonce:'fixture',autosaveDelay:1,messages:{saving:'Saving',saved:'Saved'}}},
   document:{
     querySelector(s){return s==='[data-gdo-wizard]'?form:s==='[data-gdo-autosave-status]'?status:null;},
     querySelectorAll(){return [];}
   },
   setTimeout(fn){const id=++timerID;timers.set(id,fn);return id;},
   clearTimeout(id){timers.delete(id);},
   fetch(url,options){posts.push(JSON.parse(options.body));return new Promise(resolve=>responses.push(resolve));},
 };
 vm.runInNewContext(script,sandbox,{filename:'onboarding.js'});
 const type=(value)=>{input.value=value;listeners.input({target:{matches:()=>true}});};
 const fire=()=>{const active=Array.from(timers.values());timers.clear();active.forEach(fn=>fn());};
 const flush=async()=>{for(let i=0;i<25;i++)await Promise.resolve();};
 const success=(version)=>({ok:true,json:()=>Promise.resolve({row_version:version})});
 type('A');fire();
 assert.strictEqual(posts.length,1,'first autosave started');
 assert.strictEqual(posts[0].profile.display_name,'A');
 type('B');fire(); // debounce expires while A is in-flight
 assert.strictEqual(posts.length,1,'no duplicate overlapping write');
 responses.shift()(success(2));await flush();
 assert.strictEqual(posts.length,2,'MUST save an edit made during earlier in-flight write');
 assert.strictEqual(posts[1].profile.display_name,'B','MUST send most recent profile after first write');
 assert.strictEqual(posts[1].row_version,2,'MUST use latest row version in next write');
 responses.shift()(success(3));await flush();
 assert.strictEqual(attributes['data-row-version'],'3');
 console.log('PASS File09 in-flight autosave: no lost second edit / correct revision sequencing');
}
run().catch(error=>{console.error('FAIL File09 in-flight autosave: '+error.message);process.exitCode=1;});
