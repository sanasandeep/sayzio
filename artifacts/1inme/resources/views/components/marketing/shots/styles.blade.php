{{--
    Styles for the drawn product mock UI used as the fallback for every home
    page shot slot. Included once per response by the shot component.

    Every size is in cqw (container query width units), so one block reads
    correctly at 1100px inside a browser frame and at 380px inside a collage
    without a second stylesheet. The mock is deliberately dark in both site
    themes because the product's own interface is dark.
--}}
@once
<style>
.szmk{container-type:inline-size;display:flex;background:#080C26;color:#E9EBF7;overflow:hidden;
  font-feature-settings:"tnum";line-height:1.3;position:relative;width:100%}
.szmk *{box-sizing:border-box}
.szmk .side{width:20cqw;flex:none;background:#05081C;border-right:1px solid rgba(255,255,255,.07);
  padding:2.2cqw 1.6cqw;display:flex;flex-direction:column;gap:1.6cqw}
.szmk .brand{display:flex;align-items:center;gap:.8cqw;font-size:1.35cqw;font-weight:800;letter-spacing:.14em}
.szmk .brand i{width:1.9cqw;height:1.9cqw;border-radius:.5cqw;background:linear-gradient(135deg,#3858F8,#7040F8);flex:none}
.szmk .ws{display:flex;align-items:center;gap:.8cqw;background:rgba(255,255,255,.055);border-radius:.9cqw;padding:.9cqw}
.szmk .ws u{width:2.2cqw;height:2.2cqw;border-radius:50%;background:linear-gradient(135deg,#E08A00,#E23D6E);flex:none;text-decoration:none}
.szmk .ws b{display:block;font-size:1.25cqw;font-weight:700}
.szmk .ws em{display:block;font-style:normal;font-size:1.02cqw;color:#8E95BF;margin-top:.15cqw}
.szmk nav{display:flex;flex-direction:column;gap:.15cqw;margin-top:.4cqw}
.szmk nav span{display:flex;align-items:center;gap:.85cqw;font-size:1.18cqw;color:#9AA1C8;padding:.8cqw .9cqw;border-radius:.7cqw}
.szmk nav span i{width:1.25cqw;height:1.25cqw;border-radius:.35cqw;background:currentColor;opacity:.5;flex:none}
.szmk nav span.on{background:rgba(79,70,229,.28);color:#fff}
.szmk nav span.on i{opacity:1;background:#8CA0FF}
.szmk .main{flex:1;min-width:0;display:flex;flex-direction:column}
.szmk .top{display:flex;align-items:center;gap:1cqw;padding:1.4cqw 1.8cqw;border-bottom:1px solid rgba(255,255,255,.07)}
.szmk .crumb{font-size:1.25cqw;font-weight:600}
.szmk .search{flex:1;max-width:26cqw;background:rgba(255,255,255,.05);border-radius:.7cqw;padding:.7cqw 1cqw;font-size:1.08cqw;color:#7C83AE}
.szmk .new{margin-left:auto;background:linear-gradient(135deg,#3858F8,#7040F8);border-radius:99px;padding:.7cqw 1.4cqw;font-size:1.08cqw;font-weight:700}
.szmk .body{padding:1.8cqw;display:flex;flex-direction:column;gap:1.5cqw}
.szmk .hello b{display:block;font-size:2.1cqw;font-weight:600;letter-spacing:-.02em}
.szmk .hello span{display:block;font-size:1.15cqw;color:#8E95BF;margin-top:.4cqw}
.szmk .kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:1cqw}
.szmk .kpi{background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.06);border-radius:1cqw;padding:1.1cqw}
.szmk .kpi em{display:block;font-style:normal;font-size:1cqw;color:#8E95BF;letter-spacing:.06em;text-transform:uppercase;font-weight:700}
.szmk .kpi b{display:block;font-size:2.6cqw;font-weight:600;letter-spacing:-.035em;margin-top:.5cqw}
.szmk .kpi u{display:inline-block;text-decoration:none;font-size:1cqw;font-weight:700;margin-top:.35cqw;color:#39D3A0}
.szmk .kpi u.dn{color:#F1603C}
.szmk .kpi svg{display:block;width:100%;height:2.6cqw;margin-top:.6cqw;overflow:visible}
.szmk .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1cqw}
.szmk .card{background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.06);border-radius:1cqw;padding:1.2cqw;min-width:0}
.szmk .card.w2{grid-column:span 2}
.szmk .card h5{margin:0 0 .9cqw;font-size:1.12cqw;font-weight:700;color:#C7CCEA;display:flex;justify-content:space-between;gap:1cqw}
.szmk .card h5 span{color:#7C83AE;font-weight:600}
.szmk .plot{width:100%;height:11cqw;display:block;overflow:visible}
.szmk .bars{display:flex;align-items:flex-end;gap:.55cqw;height:9.4cqw}
.szmk .bars i{flex:1;border-radius:.3cqw .3cqw 0 0;background:linear-gradient(180deg,#4F46E5,#2A2FA8)}
.szmk .bars i.hot{background:linear-gradient(180deg,#E08A00,#B36A00)}
.szmk .blabs{display:flex;justify-content:space-between;font-size:.95cqw;color:#7C83AE;margin-top:.6cqw;font-weight:600}
.szmk .donut{display:flex;align-items:center;gap:1.2cqw}
.szmk .donut svg{width:9.4cqw;height:9.4cqw;flex:none}
.szmk .lgd{display:flex;flex-direction:column;gap:.6cqw;font-size:1.02cqw;min-width:0}
.szmk .lgd span{display:flex;align-items:center;gap:.6cqw;color:#B2B8DC;white-space:nowrap}
.szmk .lgd i{width:.85cqw;height:.85cqw;border-radius:50%;flex:none}
.szmk .lgd b{margin-left:auto;color:#fff;font-weight:700}
.szmk .rows{display:flex;flex-direction:column;gap:.85cqw}
.szmk .rows .r{display:grid;grid-template-columns:1.3fr 3fr auto;align-items:center;gap:1cqw;font-size:1.08cqw}
.szmk .rows .r span{color:#C7CCEA;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.szmk .rows .r u{height:.75cqw;border-radius:99px;background:rgba(255,255,255,.09);text-decoration:none;display:block;position:relative}
.szmk .rows .r u s{position:absolute;left:0;top:0;bottom:0;border-radius:99px;text-decoration:none;background:var(--c,#4F46E5)}
.szmk .rows .r b{font-weight:700;color:#fff}
.szmk .chips{display:flex;gap:.7cqw;flex-wrap:wrap}
.szmk .chips span{background:rgba(255,255,255,.055);border:1px solid rgba(255,255,255,.07);border-radius:99px;
  padding:.55cqw 1cqw;font-size:1.05cqw;color:#B2B8DC;font-weight:600}
.szmk .chips span b{color:#fff;font-weight:700}
.szmk .tbl{border:1px solid rgba(255,255,255,.07);border-radius:1cqw;overflow:hidden}
.szmk .tbl .th,.szmk .tbl .tr{display:grid;grid-template-columns:2.6fr 1.1fr .9fr 1.5fr .7fr;align-items:center;
  gap:1cqw;padding:1cqw 1.2cqw;font-size:1.08cqw}
.szmk .tbl .th{background:rgba(255,255,255,.05);color:#7C83AE;font-size:.98cqw;font-weight:700;letter-spacing:.07em;text-transform:uppercase}
.szmk .tbl .tr{border-top:1px solid rgba(255,255,255,.06)}
.szmk .tbl .tr b{display:block;font-weight:600;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.szmk .tbl .tr em{display:block;font-style:normal;color:#7C83AE;font-size:.98cqw;margin-top:.2cqw}
.szmk .tag2{justify-self:start;border-radius:99px;padding:.35cqw .8cqw;font-size:.95cqw;font-weight:700;color:#fff}
.szmk .num{font-weight:700;font-variant-numeric:tabular-nums}
.szmk .tbl svg{width:100%;height:2.2cqw;display:block;overflow:visible}
.szmk .live{display:inline-flex;align-items:center;gap:.5cqw;font-size:.98cqw;color:#39D3A0;font-weight:700}
.szmk .live i{width:.7cqw;height:.7cqw;border-radius:50%;background:#39D3A0;flex:none}
.szmk.page{display:block;background:#F6F7FB;color:#0B1033;padding:4cqw 5cqw}
.szmk.page .ph{height:9cqw;border-radius:2.5cqw;background:linear-gradient(135deg,#3858F8,#7040F8)}
.szmk.page .nm{font-size:4.4cqw;font-weight:700;letter-spacing:-.03em;margin-top:2.4cqw}
.szmk.page .bio{font-size:2.6cqw;color:#4E5680;margin-top:.8cqw}
.szmk.page .lk{display:flex;align-items:center;gap:2cqw;background:#fff;border:1px solid #E6E8F2;border-radius:2.6cqw;
  padding:2.6cqw 2.8cqw;margin-top:2cqw;font-size:2.8cqw;font-weight:600}
.szmk.page .lk i{width:3.4cqw;height:3.4cqw;border-radius:1cqw;flex:none}
.szmk.page .cred{text-align:center;font-size:2cqw;letter-spacing:.08em;text-transform:uppercase;
  font-weight:700;color:#8B91B0;margin-top:3.4cqw}
</style>
@endonce
