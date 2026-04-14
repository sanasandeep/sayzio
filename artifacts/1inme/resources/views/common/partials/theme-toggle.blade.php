<div x-data="themeToggle()" class="flex items-center gap-2">
    <button @click="toggle()" class="theme-toggle-btn" :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'">
        <span class="toggle-knob">
            <i :class="isDark ? 'fas fa-moon' : 'fas fa-sun'" style="font-size:0.55rem;"></i>
        </span>
    </button>
</div>

<script>
function themeToggle() {
    return {
        isDark: !document.documentElement.classList.contains('light-mode'),
        toggle() {
            this.isDark = !this.isDark;
            if (this.isDark) {
                document.documentElement.classList.remove('light-mode');
                localStorage.setItem('1inme_theme', 'dark');
            } else {
                document.documentElement.classList.add('light-mode');
                localStorage.setItem('1inme_theme', 'light');
            }
        }
    }
}
</script>
