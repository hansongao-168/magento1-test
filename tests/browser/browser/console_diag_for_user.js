(function(){
  var r={};
  r.g={XfeCaTypeSwitcher:typeof window.XfeCaTypeSwitcher, XfeCaFormNotes:typeof window.XfeCaFormNotes, XfeCaEditorLabels:typeof window.XfeCaEditorLabels, Prototype:typeof window.Prototype};
  if(window.XfeCaTypeSwitcher){r.g.methods=Object.keys(window.XfeCaTypeSwitcher);}
  r.ft_id=!!document.getElementById('field_type');
  r.ft_name=!!document.querySelector('select[name="field_type"]');
  r.oc_id=!!document.getElementById('options_csv');
  r.oc_name=!!document.querySelector('input[name="options_csv"]');
  r.dv_id=!!document.getElementById('default_value');
  r.dv_name=!!document.querySelector('input[name="default_value"]');
  r.ed_count=document.querySelectorAll('.xfe-ca-opt-editor').length;
  var ms=[];var ss=document.querySelectorAll('script[src]');for(var i=0;i<ss.length;i++){var u=ss[i].src;if(u.indexOf('switcher')!==-1||u.indexOf('xfe_carrier')!==-1){ms.push(u);}}
  r.xfe_scripts=ms;
  var sel=document.querySelector('select[name="field_type"]');
  if(sel){sel.value='boolean';sel.dispatchEvent(new Event('change',{bubbles:true}));var ed=document.querySelector('.xfe-ca-opt-editor');r.after={visible:ed?(ed.style.display!=='none'):null,rows:ed?ed.querySelectorAll('.xfe-ca-opt-row').length:0,ed_html:ed?ed.outerHTML.substring(0,200):null};}
  console.log('===XFE_DIAG===');
  console.log(JSON.stringify(r,null,2));
  console.log('===END===');
})();
