<script>
(function(){
    var saved = localStorage.getItem('1inme_theme');
    if(saved === 'light') document.documentElement.classList.add('light-mode');

    function applyTheme(val){
        var isLight = val === 'light';
        if(document.documentElement.classList.contains('light-mode') === isLight) return;
        document.documentElement.classList.toggle('light-mode', isLight);
        try {
            window.dispatchEvent(new CustomEvent('1inme:theme-change', { detail: { theme: isLight ? 'light' : 'dark' } }));
        } catch(e) {}
    }

    window.addEventListener('storage', function(e){
        if(e.key !== '1inme_theme') return;
        applyTheme(e.newValue === 'light' ? 'light' : 'dark');
    });
})();
</script>
