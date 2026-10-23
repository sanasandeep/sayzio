function staffRolePicker(picked, rows, presets) {
    return {
        picked, rows, presets, search: '', risk: '', preset: '', includeCritical: false, notice: '',
        matches(row) {
            return (!this.search.trim() || row.search.includes(this.search.trim().toLowerCase()))
                && (!this.risk || String(row.risk) === this.risk);
        },
        visible(group) { return this.rows.filter(row => row.group === group && this.matches(row)); },
        selectedIn(group) { return this.rows.filter(row => row.group === group && this.picked.includes(row.id)).length; },
        criticalCount() { return this.rows.filter(row => row.risk === 4 && this.picked.includes(row.id)).length; },
        selectCategory(group) {
            const visible = this.visible(group);
            const allowed = visible.filter(row => this.includeCritical || row.risk !== 4);
            this.picked = [...new Set([...this.picked, ...allowed.map(row => row.id)])];
            this.notice = `${allowed.length} visible permissions selected in ${group}. ${visible.length - allowed.length} Critical permissions skipped.`;
        },
        clearCategory(group) {
            const ids = new Set(this.visible(group).map(row => row.id));
            this.picked = this.picked.filter(id => !ids.has(id));
            this.notice = `Visible permissions cleared in ${group}. Hidden selections are unchanged.`;
        },
        applyPreset() {
            const template = this.presets[this.preset];
            if (!template?.available) return;
            this.picked = [...template.ids];
            this.notice = `${template.name} applied. Review the selection and save the role to persist it.`;
        }
    };
}
