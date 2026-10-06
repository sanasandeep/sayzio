@once
<script>
window.billingEstimate = function (lines, discount, fallback) {
    const normalized = lines.map(line => {
        const rule = line.tax_profile || fallback;
        const rate = line.tax_rate_bps !== '' && line.tax_rate_bps != null ? Number(line.tax_rate_bps) : Number(rule?.rate_bps || 0);
        const inclusive = line.tax_rate_bps !== '' && line.tax_rate_bps != null ? !!line.tax_inclusive : !!rule?.inclusive;
        const gross = Number(line.amount_minor || 0) * Math.max(1, Number(line.quantity || 1));
        return {net: inclusive && rate > 0 ? Math.round(gross * 10000 / (10000 + rate)) : gross, rate};
    });
    const subtotal = normalized.reduce((sum, line) => sum + line.net, 0);
    const reduction = Math.max(0, Math.min(Number(discount || 0), subtotal));
    const factor = subtotal ? (subtotal - reduction) / subtotal : 1;
    const tax = normalized.reduce((sum, line) => sum + Math.round(Math.round(line.net * factor) * line.rate / 10000), 0);
    return {subtotal, tax, total: Math.max(0, subtotal - reduction + tax)};
};
</script>
@endonce
