<div x-data="themeToggle()" class="flex items-center">
    <button @click="toggle()" type="button" class="header-icon-btn" :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'" :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'">
        <i :class="isDark ? 'fas fa-moon' : 'fas fa-sun'"></i>
    </button>
</div>

<script>
function themeToggle() {
    return {
        isDark: !document.documentElement.classList.contains('light-mode'),
        toggle() {
            this.isDark = !this.isDark;
            const val = this.isDark ? 'dark' : 'light';
            if (this.isDark) {
                document.documentElement.classList.remove('light-mode');
            } else {
                document.documentElement.classList.add('light-mode');
            }
            try { localStorage.setItem('1inme_theme', val); } catch (e) {}
            try { document.cookie = '1inme_theme=' + val + '; path=/; max-age=31536000; SameSite=Lax'; } catch (e) {}
        }
    }
}
</script>
