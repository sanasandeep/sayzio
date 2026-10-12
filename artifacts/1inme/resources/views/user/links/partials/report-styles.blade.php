    .section-card {
        position: relative;
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: 18px;
        padding: 26px 28px;
        overflow: hidden;
    }
    .section-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: 10px; margin-bottom: 18px; flex-wrap: wrap;
    }
    .section-title {
        display: flex; align-items: center; gap: 12px;
        font-size: 13px; font-weight: 700; color: var(--text-primary);
    }
    .section-icon {
        width: 34px; height: 34px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        color: var(--text-dimmed); font-size: 13px;
        background: var(--bg-glass-hover);
    }
    .section-pill {
        font-size: 10px; font-weight: 600; padding: 3px 9px; border-radius: 8px;
        background: transparent;
        color: var(--text-faint);
        border: 1px solid var(--border-glass);
    }
    /* Table action button */
    .table-action {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 7px 12px; border-radius: 11px;
        font-size: 11px; font-weight: 600;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        color: var(--text-muted);
        transition: all .2s ease;
    }
    .table-action:hover { background: var(--bg-glass-hover); color: var(--text-primary); border-color: var(--accent); }

    /* Download / Copy on a chart card. Smaller than a table action because it
       sits on the title row beside the period pill, and the chart is what the
       eye should land on first. */
    .chart-actions { display: inline-flex; align-items: center; gap: 6px; }
    .chart-actions .table-action { padding: 5px 10px; font-size: 10.5px; }


@media (max-width: 640px) {
    .section-card { padding: 18px 16px; }
    .section-title { flex-wrap: wrap; }
}
