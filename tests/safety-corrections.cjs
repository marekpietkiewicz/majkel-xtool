// Disposable local WordPress only; creates posts and deletes them on completion.
// WP_TEST_CONTAINER=... WP_TEST_COMPOSE=/path/to/compose.yml WP_TEST_PROJECT=... node tests/safety-corrections.cjs
const { execFileSync } = require('node:child_process');
const { resolve } = require('node:path');
const assert = require('node:assert/strict');
const container = process.env.WP_TEST_CONTAINER;
const compose = process.env.WP_TEST_COMPOSE;
const project = process.env.WP_TEST_PROJECT;
if (!container || !compose || !project) throw new Error('A disposable local WordPress container and Compose project are required');
const manifest = require('../scripts/blog/safety-corrections.json');
const encoded = Buffer.from(JSON.stringify(manifest)).toString('base64');
const php = (script) => execFileSync('docker', ['exec', '-i', container, 'php'], { input: `<?php require '/var/www/html/wp-load.php'; ${script}`, encoding: 'utf8' }).trim();
const data = JSON.parse(php(`
 $manifest=json_decode(base64_decode('${encoded}'),true); $rows=[];
 foreach($manifest as $seed=>$pairs){
  $existing=get_posts(['post_type'=>'post','post_status'=>'any','meta_key'=>'_lasery_seed','meta_value'=>$seed,'posts_per_page'=>-1]);
  if($existing) {fwrite(STDERR,"Fixture seed exists: $seed\\n");exit(1);}
 }
 foreach($manifest as $seed=>$pairs){
  $parts=[]; $next=[];
  foreach($pairs as $pair){$parts[]='<p>'.esc_html($pair[0]).'</p>'; $next[]='<p>'.esc_html($pair[1]).'</p>';}
  $original='<p>EDITOR CUSTOM CONTENT: retain this.</p>'.implode('', $parts);
  $expected='<p>EDITOR CUSTOM CONTENT: retain this.</p>'.implode('', $next);
  $id=wp_insert_post(wp_slash(['post_type'=>'post','post_status'=>'publish','post_title'=>'Safety test fixture','post_content'=>$original]),true);
  if(is_wp_error($id)) {fwrite(STDERR,$id->get_error_message());exit(1);}
  update_post_meta($id,'_lasery_seed',$seed); $rows[]=['id'=>$id,'seed'=>$seed,'before'=>$original,'after'=>$expected];
 }
 echo wp_json_encode($rows);
`));
const ids = data.map((row) => row.id);
const snapshot = () => JSON.parse(php(`$ids=${JSON.stringify(ids)};$result=[];foreach($ids as $id)$result[]=['id'=>$id,'content'=>get_post($id)->post_content];echo wp_json_encode($result);`));
const before = data.map(({id,before}) => ({id,content:before}));
const after = data.map(({id,after}) => ({id,content:after}));
const args = ['compose','-p',project,'-f',compose,'run','--rm','--no-deps','-v',`${resolve('scripts')}:/safety-scripts:ro`];
const run = (apply, fault = false) => {
 const env = ['-e','LASERY_XTOOL_INTEGRATION_TEST=1'];
 if (apply) env.push('-e','LASERY_APPLY_SAFETY_CORRECTIONS=1');
 return execFileSync('docker',[...args,...env,'cli','wp','eval-file',fault ? '/tests/safety-corrections-fault.php' : '/safety-scripts/apply-safety-corrections.php'],{encoding:'utf8',stdio:['ignore','pipe','pipe']});
};
const expectFailure = (action, message) => { let failed=false; try { action(); } catch (error) { failed=true; assert.match(String(error.stderr),message); } assert(failed, 'expected the migration to fail'); };
try {
 assert.match(run(false),/Preview only/); assert.deepEqual(snapshot(),before);
 // If one editor changed a target fragment, no other post may be touched.
 php(`$post=get_post(${ids[0]}); wp_update_post(wp_slash(['ID'=>$post->ID,'post_content'=>str_replace(${JSON.stringify(data[0].before)},'EDITOR CHANGED THIS POST',$post->post_content)]));`);
 const conflict=snapshot(); expectFailure(()=>run(true),/Cannot safely identify/); assert.deepEqual(snapshot(),conflict);
 php(`wp_update_post(wp_slash(['ID'=>${ids[0]},'post_content'=>${JSON.stringify(data[0].before)}]));`);
 assert.deepEqual(snapshot(),before);
 expectFailure(()=>run(true,true),/all corrections were rolled back/); assert.deepEqual(snapshot(),before);
 assert.match(run(true),new RegExp(`Updated ${data.length} posts`)); assert.deepEqual(snapshot(),after);
 assert.match(run(true),/Updated 0 posts/); assert.deepEqual(snapshot(),after);
 console.log('Safety corrections: preview, edited-content conflict, transaction rollback, apply and idempotence passed.');
} finally {
 php(`foreach(${JSON.stringify(ids)} as $id)wp_delete_post($id,true);`);
}
