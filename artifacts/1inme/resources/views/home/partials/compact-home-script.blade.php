<script>
(function(){
 document.querySelectorAll('[data-home-tabs]').forEach(function(group){
  if(group.dataset.tabsReady)return;
  group.dataset.tabsReady='true';
  var tabs=Array.from(group.querySelectorAll('[role="tab"]'));
  function select(tab){
   tabs.forEach(function(item){var active=item===tab;item.setAttribute('aria-selected',String(active));item.tabIndex=active?0:-1;var panel=document.getElementById(item.getAttribute('aria-controls'));if(panel)panel.hidden=!active;});
  }
  tabs.forEach(function(tab,index){
   tab.addEventListener('click',function(){select(tab);});
   tab.addEventListener('keydown',function(event){var next=index;if(event.key==='ArrowRight')next=(index+1)%tabs.length;else if(event.key==='ArrowLeft')next=(index-1+tabs.length)%tabs.length;else if(event.key==='Home')next=0;else if(event.key==='End')next=tabs.length-1;else return;event.preventDefault();select(tabs[next]);tabs[next].focus();});
  });
 });
})();
</script>
