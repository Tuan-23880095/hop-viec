<?php require __DIR__.'/lib.php'; if(!hv_config()){header('Location: setup.php');exit;} ?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hộp việc đội agent</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap">
<style>
/* Layout: a 3-tab operations board — Hộp việc (kanban theo trạng thái), Dự án, Tiêu hao.
   Summary strip on top, detail drawer on the right (stacks on phone). */
:root{
  --bg:#f3f4f2; --panel:#ffffff; --panel-2:#e9ebe7; --fg:#1b1f1d; --muted:#5f6764; --line:#d6dad5;
  --accent:#0f766e; --accent-ink:#ffffff; --warn:#b7791f; --bad:#b42318; --good:#1f7a3e;
  --c-claude:#c2410c; --c-gemini:#1d4ed8; --c-notebooklm:#15803d; --c-antigravity:#6d28d9; --c-spark:#b45309; --c-co-van:#9f1239; --c-tuan:#475569;
  --font-ui:"Be Vietnam Pro",system-ui,"Segoe UI",sans-serif; --font-num:"JetBrains Mono",ui-monospace,Consolas,monospace;
  --r:8px;
}
@media (prefers-color-scheme: dark){:root:not([data-theme="light"]){
  --bg:#141716; --panel:#1d2120; --panel-2:#262b29; --fg:#e8eae7; --muted:#9aa39f; --line:#343a37;
  --accent:#2dd4bf; --accent-ink:#06211e; --warn:#f3b24a; --bad:#f87171; --good:#4ade80;
  --c-claude:#fb923c; --c-gemini:#60a5fa; --c-notebooklm:#4ade80; --c-antigravity:#a78bfa; --c-spark:#fbbf24; --c-co-van:#fb7185; --c-tuan:#94a3b8;
  color-scheme:dark}}
:root[data-theme="dark"]{
  --bg:#141716; --panel:#1d2120; --panel-2:#262b29; --fg:#e8eae7; --muted:#9aa39f; --line:#343a37;
  --accent:#2dd4bf; --accent-ink:#06211e; --warn:#f3b24a; --bad:#f87171; --good:#4ade80;
  --c-claude:#fb923c; --c-gemini:#60a5fa; --c-notebooklm:#4ade80; --c-antigravity:#a78bfa; --c-spark:#fbbf24; --c-co-van:#fb7185; --c-tuan:#94a3b8;
  color-scheme:dark}
*{box-sizing:border-box}
body{background:var(--bg);color:var(--fg);font-family:var(--font-ui);font-size:14px;line-height:1.45;padding-inline:16px;padding-block:12px 32px;max-width:1280px;margin:0 auto}
h1,h2,h3{margin:0;text-wrap:balance;font-weight:600}
h1{font-size:20px}
h2{font-size:15px}
.eyebrow{font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);font-weight:600}
.num{font-family:var(--font-num);font-variant-numeric:tabular-nums}
button,input,select,textarea{font:inherit;color:inherit}
button{cursor:pointer;border:1px solid var(--line);background:var(--panel);border-radius:var(--r);padding:6px 10px}
button.primary{background:var(--accent);color:var(--accent-ink);border-color:transparent;font-weight:600}
button.ghost{background:transparent;border-color:transparent;color:var(--muted)}
button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible,[tabindex]:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
input,select,textarea{background:var(--panel);border:1px solid var(--line);border-radius:var(--r);padding:6px 8px;width:100%;min-width:0}
textarea{min-height:72px;resize:vertical}
label{display:grid;gap:4px;font-size:12px;color:var(--muted)}
header{display:flex;flex-wrap:wrap;align-items:center;gap:12px 20px;padding-block:6px 12px;border-bottom:1px solid var(--line)}
header .spacer{flex:1}
.tabs{display:flex;gap:4px}
.tabs button{border-color:transparent;background:transparent;color:var(--muted);font-weight:500;padding:6px 10px}
.tabs button[aria-selected="true"]{background:var(--panel-2);color:var(--fg);font-weight:600}
.status{font-size:12px;color:var(--muted)}
.status.off{color:var(--warn)}
.strip{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-block:14px}
.tile{background:var(--panel);border:1px solid var(--line);border-radius:var(--r);padding:10px 12px;display:grid;gap:2px}
.tile .v{font-size:22px;font-weight:600}
.tile .s{font-size:12px;color:var(--muted)}
main{display:grid;grid-template-columns:minmax(0,1fr);gap:16px}
@media(min-width:960px){main.with-drawer{grid-template-columns:minmax(0,1fr) 360px}}
section[hidden]{display:none!important}
.toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px}
.toolbar select{width:auto}
.board{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:10px;align-items:start}
.col{background:var(--panel-2);border-radius:var(--r);padding:8px;display:grid;gap:8px;min-width:0}
.col h3{font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);display:flex;justify-content:space-between;padding:2px 4px}
.card{background:var(--panel);border:1px solid var(--line);border-left:4px solid var(--who,var(--line));border-radius:var(--r);padding:8px 10px;display:grid;gap:4px;cursor:pointer;min-width:0}
.card:hover{border-color:var(--accent)}
.card.sel{outline:2px solid var(--accent)}
.card .t{font-weight:500;overflow-wrap:anywhere}
.card .m{display:flex;flex-wrap:wrap;gap:6px;font-size:11px;color:var(--muted);align-items:center}
.chip{display:inline-block;padding:1px 7px;border-radius:999px;font-size:11px;font-weight:600;background:var(--panel-2);color:var(--fg)}
.chip.who{color:var(--who);border:1px solid var(--who)}
.chip.p3{color:var(--bad)}
.chip.late{background:var(--bad);color:#fff}
.drawer{background:var(--panel);border:1px solid var(--line);border-radius:var(--r);padding:14px;display:grid;gap:10px;align-self:start;position:sticky;top:env(safe-area-inset-top,0px)}
.drawer .row{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.drawer .actions{display:flex;flex-wrap:wrap;gap:6px;justify-content:flex-end}
.result{background:var(--panel-2);border-radius:var(--r);padding:8px 10px;white-space:pre-wrap;overflow-wrap:anywhere;font-size:13px;max-height:320px;overflow:auto}
.empty{border:1px dashed var(--line);border-radius:var(--r);padding:28px 16px;text-align:center;color:var(--muted);display:grid;gap:6px}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{text-align:left;padding:6px 8px;border-bottom:1px solid var(--line);vertical-align:top}
th{font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);font-weight:600}
td.r,th.r{text-align:right}
.tablewrap{overflow-x:auto}
.plist{display:grid;gap:8px}
.proj{background:var(--panel);border:1px solid var(--line);border-radius:var(--r);padding:10px 12px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:4px 12px;align-items:center}
.proj .bar{grid-column:1/-1;height:6px;background:var(--panel-2);border-radius:3px;overflow:hidden}
.proj .bar i{display:block;height:100%;background:var(--accent)}
.usage-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}
.panel{background:var(--panel);border:1px solid var(--line);border-radius:var(--r);padding:12px;display:grid;gap:10px;min-width:0}
.meter{display:grid;gap:3px}
.meter .lbl{display:flex;justify-content:space-between;font-size:12px}
.meter .trk{height:8px;background:var(--panel-2);border-radius:4px;overflow:hidden}
.meter .trk i{display:block;height:100%;background:var(--who,var(--accent))}
.meter.hot .trk i{background:var(--bad)}
.chart{display:grid;grid-auto-flow:column;grid-auto-columns:minmax(0,1fr);gap:4px;align-items:end;height:140px;border-bottom:1px solid var(--line);padding-top:4px}
.chart .d{display:grid;align-content:end;gap:1px;height:100%}
.chart .d i{display:block;min-height:1px}
.chart .d span{font-size:10px;color:var(--muted);text-align:center;font-family:var(--font-num)}
.legend{display:flex;flex-wrap:wrap;gap:10px;font-size:12px;color:var(--muted)}
.legend b{display:inline-block;width:10px;height:10px;border-radius:2px;background:var(--who);vertical-align:-1px;margin-right:4px}
.form{display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));background:var(--panel);border:1px solid var(--line);border-radius:var(--r);padding:12px;margin-bottom:12px}
.form .wide{grid-column:1/-1}
.form .actions{grid-column:1/-1;display:flex;gap:6px;justify-content:flex-end}
.toast{position:fixed;left:50%;bottom:calc(16px + env(safe-area-inset-bottom,0px));transform:translateX(-50%);background:var(--fg);color:var(--bg);padding:8px 14px;border-radius:999px;font-size:13px;opacity:0;transition:opacity .2s;pointer-events:none}
.toast.on{opacity:1}
.hint{font-size:12px;color:var(--muted)}
.who-claude{--who:var(--c-claude)}.who-gemini{--who:var(--c-gemini)}.who-notebooklm{--who:var(--c-notebooklm)}.who-antigravity{--who:var(--c-antigravity)}.who-spark{--who:var(--c-spark)}.who-co-van{--who:var(--c-co-van)}.who-tuan{--who:var(--c-tuan)}
@media(prefers-reduced-motion:reduce){*{transition:none!important}}
body{margin:0}img{max-width:100%}[hidden]{display:none!important}
.tabs .badge{display:inline-block;min-width:18px;padding:0 5px;margin-left:4px;border-radius:999px;background:var(--warn);color:var(--panel);font-size:11px;font-weight:700;text-align:center}
.tile.link{cursor:pointer}.tile.link:hover{border-color:var(--accent)}
.rlist{display:grid;gap:10px;max-width:900px}
.rcard{background:var(--panel);border:1px solid var(--line);border-left:4px solid var(--who,var(--line));border-radius:var(--r);padding:12px 14px;display:grid;gap:8px;min-width:0;scroll-margin-top:12px}
.rcard.flash{outline:2px solid var(--accent)}
.rcard .t{font-size:15px;font-weight:600;overflow-wrap:anywhere}
.rcard .m{display:flex;flex-wrap:wrap;gap:6px;font-size:12px;color:var(--muted);align-items:center}
.rblock{font-size:13px;min-width:0}
.rblock .eyebrow{margin-bottom:2px}
.rblock p{margin:0;white-space:pre-wrap;overflow-wrap:anywhere}
.rblock.clamp p{display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden}
.rblock.q{background:var(--panel-2);border-radius:var(--r);padding:6px 8px}
.rblock.q.need p{color:var(--warn);font-weight:600}
.rlinks{display:flex;flex-wrap:wrap;gap:6px}
.rlinks a{display:inline-flex;align-items:center;gap:4px;border:1px solid var(--line);border-radius:var(--r);padding:4px 10px;color:var(--accent);text-decoration:none;font-weight:600;font-size:13px;overflow-wrap:anywhere}
.rlinks a:hover{border-color:var(--accent)}
.ractions{display:flex;flex-wrap:wrap;gap:6px;align-items:center;border-top:1px solid var(--line);padding-top:8px}
.ractions .grow{flex:1}
button.ok{background:var(--good);color:var(--panel);border-color:transparent;font-weight:600}
button.link{border:0;background:none;color:var(--accent);padding:2px 0;font-weight:600;font-size:12px;justify-self:start;text-align:left}
.rdone{font-size:13px;color:var(--muted);display:grid;gap:4px}
.login{max-width:360px;margin:60px auto;display:grid;gap:10px;background:var(--panel);border:1px solid var(--line);border-radius:var(--r);padding:20px}
</style></head><body>
<div class="login" id="login" hidden><h2>Đăng nhập Hộp việc</h2><label>Mật khẩu<input type="password" id="pw" autocomplete="current-password"></label><button class="primary" id="pw-go">Vào</button><div class="hint" id="pw-msg"></div></div>
<div id="app">

<header>
  <div>
    <div class="eyebrow">Đội agent của Tuấn</div>
    <h1>Hộp việc</h1>
  </div>
  <nav class="tabs" role="tablist">
    <button role="tab" aria-selected="true" data-tab="tasks" id="tab-tasks">Việc</button>
    <button role="tab" aria-selected="false" data-tab="review" id="tab-review">Chờ duyệt<span class="badge" id="review-badge" hidden></span></button>
    <button role="tab" aria-selected="false" data-tab="projects" id="tab-projects">Dự án</button>
    <button role="tab" aria-selected="false" data-tab="usage" id="tab-usage">Tiêu hao</button>
  </nav>
  <div class="spacer"></div>
  <div class="status" id="conn">Đang tải…</div><button class="ghost" id="settings-btn" title="Cài đặt">⚙</button><button class="ghost" id="logout" title="Đăng xuất">Thoát</button>
</header>

<div class="form" id="settings" hidden>
  <label>Gemini API key (cho nút Gợi ý điền; lấy tại aistudio.google.com/app/apikey)<input type="password" id="set-key" placeholder="dán key mới; để trống nếu giữ nguyên" autocomplete="off"></label>
  <div class="hint">Model: <b>tự chọn</b>. Hộp việc hỏi Google các model key này dùng được, chọn bản flash mới nhất và tự đổi sang model khác khi một model hết lượt.<br><span id="set-model-info"></span></div>
  <div class="hint wide" id="set-info"></div>
  <div class="hint wide" id="set-test-out" hidden></div>
  <div class="actions"><button id="set-close">Đóng</button><button id="set-test">Kiểm tra key</button><button class="primary" id="set-save">Lưu cài đặt</button></div>
</div>
<div class="strip" id="strip"></div>

<main id="main">
  <section id="sec-tasks">
    <div class="form" id="newTask">
      <label class="wide">Việc mới
        <input id="nt-title" placeholder="VD: Viết literature review nháp cho đề tài T2025-03">
      </label>
      <label>Dự án<select id="nt-project"><option value="">— không —</option></select></label>
      <label>Nhóm<select id="nt-group"></select></label>
      <label>Giao cho<select id="nt-who"></select></label>
      <label>Ưu tiên<select id="nt-pri"><option value="1">Thấp</option><option value="2" selected>Vừa</option><option value="3">Cao</option></select></label>
      <label>Hạn<input type="date" id="nt-due"></label>
      <label class="wide">Mô tả / yêu cầu đầu ra<textarea id="nt-desc" placeholder="Agent cần trả về gì? Nguồn ở notebook nào? Giới hạn độ dài?"></textarea></label>
      <div class="hint wide" id="nt-reason" hidden></div>
      <div class="actions"><button id="nt-claude" title="Thêm việc thô, Claude quản gia sẽ phân loại trong phiên sáng">Nhờ Claude điền</button><button id="nt-suggest" title="Gemini phân tích mô tả và điền sẵn các ô (thầy duyệt rồi mới Thêm)">✨ Gợi ý điền</button><button class="primary" id="nt-add">Thêm việc</button></div>
    </div>
    <div class="toolbar">
      <select id="f-who"><option value="">Mọi agent</option></select>
      <select id="f-group"><option value="">Mọi nhóm</option></select>
      <select id="f-project"><option value="">Mọi dự án</option></select>
      <label style="display:flex;gap:6px;align-items:center;font-size:13px;color:var(--fg)"><input type="checkbox" id="f-done" style="width:auto"> hiện việc đã xong</label>
    </div>
    <div class="board" id="board"></div>
  </section>

  <section id="sec-review" hidden>
    <p class="hint" style="margin:0 0 10px">Kết quả agent đã nộp. <b>Duyệt</b> chuyển việc sang Xong; <b>Trả lại</b> chuyển về Đang làm và chèn ghi chú của thầy lên đầu mô tả. Mỗi việc có link riêng (nút 🔗) để mở thẳng từ báo cáo hay tin nhắn.</p>
    <div class="rlist" id="rlist"></div>
    <div class="rdone" id="rdone" hidden></div>
  </section>

  <section id="sec-projects" hidden>
    <div class="form">
      <label>Tên dự án<input id="np-name" placeholder="VD: Đề tài T2025-03 mô hình laser"></label>
      <label>Nhóm<select id="np-group"></select></label>
      <label>Hạn<input type="date" id="np-due"></label>
      <label class="wide">Mục tiêu<textarea id="np-goal"></textarea></label>
      <div class="actions"><button class="primary" id="np-add">Thêm dự án</button></div>
    </div>
    <div class="plist" id="plist"></div>
  </section>

  <section id="sec-usage" hidden>
    <div class="usage-grid">
      <div class="panel">
        <h2>Tháng này so với hạn mức</h2>
        <div id="meters"></div>
        <p class="hint">Hạn mức sửa ở tài liệu <span class="num">config/budgets</span>; quản gia ghi số liệu ngày vào <span class="num">usage/YYYY-MM-DD</span>.</p>
      </div>
      <div class="panel">
        <h2>14 ngày gần nhất</h2>
        <div class="chart" id="chart"></div>
        <div class="legend" id="legend"></div>
      </div>
      <div class="panel" style="grid-column:1/-1">
        <h2>Ghi tiêu hao hôm nay</h2>
        <div class="form" style="margin:0;border:0;padding:0" id="usageForm"></div>
      </div>
      <div class="panel" style="grid-column:1/-1">
        <h2>Nhật ký ngày</h2>
        <div class="tablewrap"><table id="usageTable"></table></div>
      </div>
    </div>
  </section>

  <aside class="drawer" id="drawer" hidden></aside>
</main>
<div class="toast" id="toast"></div>

<script>
const WHO = {
  claude:{n:"Claude quản gia",unit:"token",budget:"claude_tokens"},
  gemini:{n:"Gemini API",unit:"token",budget:"gemini_tokens"},
  notebooklm:{n:"NotebookLM",unit:"truy vấn",budget:"nlm_queries"},
  antigravity:{n:"Antigravity",unit:"credit",budget:"agy_credits"},
  spark:{n:"Gemini Spark",unit:"tác vụ",budget:"spark_tasks"},
  "co-van":{n:"Cố vấn (Opus)",unit:"token",budget:"opus_tokens"},
  tuan:{n:"Tuấn",unit:"",budget:""}
};
const USAGE_KEYS = ["claude_tokens","opus_tokens","gemini_tokens","agy_credits","nlm_queries","spark_tasks"];
const USAGE_WHO = {claude_tokens:"claude",opus_tokens:"co-van",gemini_tokens:"gemini",agy_credits:"antigravity",nlm_queries:"notebooklm",spark_tasks:"spark"};
const USAGE_LABEL = {claude_tokens:"Claude (token)",opus_tokens:"Cố vấn Opus (token)",gemini_tokens:"Gemini API (token)",agy_credits:"Antigravity (credit)",nlm_queries:"NotebookLM (truy vấn)",spark_tasks:"Spark (tác vụ)"};
const GROUPS = {"nghien-cuu":"Nghiên cứu & công bố","giang-day":"Giảng dạy & thực địa","edtech":"Lập trình EdTech","quan-ly":"Quản lý & báo cáo"};
const STATUS = {moi:"Mới",dang_lam:"Đang làm",cho_duyet:"Chờ duyệt",chan:"Bị chặn",xong:"Xong"};
const DEFAULT_BUDGETS = {claude_tokens:2000000,opus_tokens:300000,gemini_tokens:5000000,agy_credits:1000,nlm_queries:1200,spark_tasks:300};

let db=null, tasks=[], projects=[], usage=[], budgets=DEFAULT_BUDGETS, sel=null, tab="tasks", canWrite=true;
const $=s=>document.querySelector(s);
const esc=s=>String(s??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const fmt=n=>new Intl.NumberFormat("vi-VN").format(Math.round(n||0));
const today=()=>new Date().toISOString().slice(0,10);
const toast=m=>{const t=$("#toast");t.textContent=m;t.classList.add("on");clearTimeout(t._h);t._h=setTimeout(()=>t.classList.remove("on"),1800)};

function fillSelects(){
  const who=Object.entries(WHO).map(([k,v])=>`<option value="${k}">${v.n}</option>`).join("");
  const grp=Object.entries(GROUPS).map(([k,v])=>`<option value="${k}">${v}</option>`).join("");
  $("#nt-who").innerHTML=who; $("#f-who").insertAdjacentHTML("beforeend",who);
  $("#nt-group").innerHTML=grp; $("#np-group").innerHTML=grp; $("#f-group").insertAdjacentHTML("beforeend",grp);
  $("#usageForm").innerHTML=USAGE_KEYS.map(k=>`<label>${USAGE_LABEL[k]}<input type="number" min="0" id="u-${k}" placeholder="0"></label>`).join("")
    +`<label class="wide">Ghi chú<input id="u-note" placeholder="VD: 3 phiên quản gia, 1 lần hỏi cố vấn"></label><div class="actions"><button class="primary" id="u-save">Lưu cho hôm nay</button></div>`;
}
function projectOptions(){
  const o=projects.map(p=>`<option value="${p.id}">${esc(p.name)}</option>`).join("");
  $("#nt-project").innerHTML='<option value="">— không —</option>'+o;
  $("#f-project").innerHTML='<option value="">Mọi dự án</option>'+o;
}

function renderStrip(){
  const open=tasks.filter(t=>t.status!=="xong");
  const late=open.filter(t=>t.due&&t.due<today()).length;
  const review=tasks.filter(t=>t.status==="cho_duyet").length;
  const m=monthTotals();
  const pct=k=>budgets[k]?Math.round(100*(m[k]||0)/budgets[k]):0;
  $("#strip").innerHTML=[
    ["Việc đang mở",open.length,late?`${late} quá hạn`:"không quá hạn",late?"bad":""],
    ["Chờ Tuấn duyệt",review,review?"bấm để duyệt →":"kết quả agent đã trả","review"],
    ["Claude tháng này",pct("claude_tokens")+"%",fmt(m.claude_tokens)+" / "+fmt(budgets.claude_tokens)+" token",pct("claude_tokens")>80?"bad":""],
    ["Gemini API",pct("gemini_tokens")+"%",fmt(m.gemini_tokens)+" token",""],
    ["NotebookLM hôm nay",(usage.find(u=>u.id===today())||{}).nlm_queries||0,"/ 50 truy vấn (free)",""]
  ].map(([l,v,s,c])=>`<div class="tile${c==="review"?" link":""}" ${c==="review"?'data-go="#duyet" tabindex="0" role="link"':""}><div class="eyebrow">${l}</div><div class="v num" style="${c==="bad"?"color:var(--bad)":(c==="review"&&v?"color:var(--warn)":"")}">${v}</div><div class="s">${s}</div></div>`).join("");
  const b=$("#review-badge");b.hidden=!review;b.textContent=review;
}
function monthTotals(){
  const ym=today().slice(0,7), out={};
  usage.filter(u=>u.id.startsWith(ym)).forEach(u=>USAGE_KEYS.forEach(k=>out[k]=(out[k]||0)+(+u[k]||0)));
  return out;
}

function renderBoard(){
  const fw=$("#f-who").value, fg=$("#f-group").value, fp=$("#f-project").value, showDone=$("#f-done").checked;
  const list=tasks.filter(t=>(!fw||t.who===fw)&&(!fg||t.group===fg)&&(!fp||t.projectId===fp)&&(showDone||t.status!=="xong"));
  const cols=Object.keys(STATUS).filter(s=>showDone||s!=="xong");
  if(!tasks.length){
    $("#board").innerHTML=`<div class="empty" style="grid-column:1/-1"><b>Chưa có việc nào.</b><span>Thêm việc ở trên, hoặc để Claude quản gia ghi vào kho (bộ sưu tập <span class="num">tasks</span>).</span></div>`;return;
  }
  $("#board").innerHTML=cols.map(s=>{
    const items=list.filter(t=>t.status===s).sort((a,b)=>(b.priority||2)-(a.priority||2)||String(a.due||"9").localeCompare(String(b.due||"9")));
    return `<div class="col"><h3><span>${STATUS[s]}</span><span class="num">${items.length}</span></h3>${items.map(card).join("")||'<div class="hint" style="padding:4px">—</div>'}</div>`;
  }).join("");
}
function card(t){
  const p=projects.find(p=>p.id===t.projectId);
  const late=t.due&&t.due<today()&&t.status!=="xong";
  return `<div class="card who-${esc(t.who)} ${sel===t.id?"sel":""}" data-id="${t.id}" tabindex="0">
    <div class="t">${esc(t.title)}</div>
    <div class="m"><span class="chip who">${esc((WHO[t.who]||{}).n||t.who)}</span>${t.priority==3?'<span class="chip p3">Cao</span>':""}${t.due?`<span class="chip ${late?"late":""} num">${esc(t.due)}</span>`:""}${p?`<span>${esc(p.name)}</span>`:""}</div>
  </div>`;
}

function renderDrawer(){
  const d=$("#drawer"), t=tasks.find(x=>x.id===sel);
  if(!t){d.hidden=true;$("#main").classList.remove("with-drawer");return;}
  d.hidden=false;$("#main").classList.add("with-drawer");
  const u=t.usage||{};
  d.innerHTML=`<div style="display:flex;justify-content:space-between;gap:8px;align-items:start"><h2 style="overflow-wrap:anywhere">${esc(t.title)}</h2><button class="ghost" id="d-close" aria-label="Đóng">✕</button></div>
  <div class="row">
    <label>Trạng thái<select id="d-status">${Object.entries(STATUS).map(([k,v])=>`<option value="${k}" ${t.status===k?"selected":""}>${v}</option>`).join("")}</select></label>
    <label>Giao cho<select id="d-who">${Object.entries(WHO).map(([k,v])=>`<option value="${k}" ${t.who===k?"selected":""}>${v.n}</option>`).join("")}</select></label>
    <label>Ưu tiên<select id="d-pri">${[1,2,3].map(n=>`<option value="${n}" ${(t.priority||2)==n?"selected":""}>${["","Thấp","Vừa","Cao"][n]}</option>`).join("")}</select></label>
    <label>Hạn<input type="date" id="d-due" value="${esc(t.due||"")}"></label>
  </div>
  <label>Mô tả / yêu cầu<textarea id="d-desc">${esc(t.desc||"")}</textarea></label>
  <div><div class="eyebrow">Kết quả agent trả về</div><div class="result">${t.result?esc(t.result):'<span class="hint">Chưa có. Agent sẽ ghi vào trường <span class="num">result</span>; link sản phẩm ở <span class="num">resultLink</span>.</span>'}</div>
  ${t.resultLink?`<div class="hint" style="margin-top:4px;overflow-wrap:anywhere">Sản phẩm: <a href="${esc(t.resultLink)}" target="_blank" rel="noopener">${esc(t.resultLink)}</a></div>`:""}</div>
  <div class="hint">Tiêu hao cho việc này: ${USAGE_KEYS.filter(k=>u[k]).map(k=>`<span class="num">${fmt(u[k])}</span> ${USAGE_LABEL[k].toLowerCase()}`).join(" · ")||"chưa ghi"}</div>
  <div class="hint num">id: ${esc(t.id)} · tạo ${esc((t.created_at||"").slice(0,16).replace("T"," "))}${t.updated_at?" · sửa "+esc(t.updated_at.slice(0,16).replace("T"," ")):""}</div>
  ${t.status==="cho_duyet"?`<button class="ok" id="d-review">Duyệt / trả lại việc này →</button>`:""}
  <div class="actions"><button id="d-del">Xoá</button><button class="primary" id="d-save">Lưu</button></div>`;
  $("#d-close").onclick=()=>{sel=null;renderBoard();renderDrawer();if(location.hash.startsWith("#viec-"))history.replaceState(null,"",location.pathname)};
  if($("#d-review"))$("#d-review").onclick=()=>{location.hash="#duyet-"+t.id};
  $("#d-save").onclick=async()=>{
    await write({action:"task",qs:{id:t.id}},{status:$("#d-status").value,who:$("#d-who").value,priority:+$("#d-pri").value,due:$("#d-due").value||"",desc:$("#d-desc").value},"Đã lưu");
  };
  $("#d-del").onclick=async()=>{
    if(d.dataset.confirm!==t.id){d.dataset.confirm=t.id;$("#d-del").textContent="Bấm lần nữa để xoá";return;}
    sel=null;await write({action:"task",method:"DELETE",qs:{id:t.id}},{id:t.id},"Đã xoá");
  };
}

// ---------- Chờ duyệt ----------
const rstate={}; let reviewedRecent=[]; let flashId=null;
function parseResult(r){
  r=String(r||"").trim(); const iV=r.search(/VẤN ĐỀ\s*:/), iQ=r.search(/CẦN QUYẾT ĐỊNH\s*:/);
  const ends=[iV,iQ].filter(i=>i>=0).sort((a,b)=>a-b);
  const kq=r.slice(0,ends.length?ends[0]:undefined).replace(/^KẾT QUẢ\s*:\s*/,"").trim();
  const vd=iV>=0?r.slice(iV,iQ>iV?iQ:undefined).replace(/^VẤN ĐỀ\s*:\s*/,"").trim():"";
  const qd=iQ>=0?r.slice(iQ,iV>iQ?iV:undefined).replace(/^CẦN QUYẾT ĐỊNH\s*:\s*/,"").trim():"";
  return {kq,vd,qd};
}
const needDecision=qd=>!!qd&&!/^không\b/i.test(qd);
function linkLabel(u){
  let m=u.match(/github\.com\/[^/]+\/([^/]+)\/pull\/(\d+)/); if(m) return "PR #"+m[2]+" ("+m[1]+")";
  if(/drive\.google\.com\/drive\/folders/.test(u)) return "Thư mục Drive";
  if(/docs\.google\.com\/spreadsheets/.test(u)) return "Google Sheet";
  if(/docs\.google\.com\/document/.test(u)) return "Google Doc";
  if(/docs\.google\.com\/presentation/.test(u)) return "Google Slides";
  if(/drive\.google\.com\/file/.test(u)) return "File Drive";
  try{return new URL(u).hostname.replace(/^www\./,"")}catch{return "Mở link"}
}
function taskLinks(t){
  const found=String(t.result||"").match(/https?:\/\/[^\s)"'\]<>]+/g)||[];
  return [...new Set([t.resultLink,...found].filter(Boolean).map(u=>u.replace(/[.,;:]+$/,"")))].filter(u=>/^https?:\/\//.test(u));
}
const permalink=(id)=>location.origin+location.pathname+"#duyet-"+id;
function fmtWhen(s){if(!s)return"";const d=new Date(s);return isNaN(d)?s:d.toLocaleString("vi-VN",{hour:"2-digit",minute:"2-digit",day:"2-digit",month:"2-digit"});}
function renderReview(){
  const box=$("#rlist"); if(!box) return;
  if(box.contains(document.activeElement)&&document.activeElement.tagName==="TEXTAREA") return; // đang gõ ghi chú: không vẽ lại
  const list=tasks.filter(t=>t.status==="cho_duyet").sort((a,b)=>(needDecision(parseResult(b.result).qd)-needDecision(parseResult(a.result).qd))||String(b.updated_at||"").localeCompare(String(a.updated_at||"")));
  if(!list.length){box.innerHTML=`<div class="empty"><b>Không còn việc nào chờ duyệt.</b><span>Khi agent nộp kết quả, việc sẽ hiện ở đây.</span></div>`;}
  else box.innerHTML=list.map(t=>{
    const st=rstate[t.id]||(rstate[t.id]={mode:"",note:"",open:false,busy:false,msg:""});
    const r=parseResult(t.result), need=needDecision(r.qd), p=projects.find(p=>p.id===t.projectId), links=taskLinks(t);
    let act;
    if(st.mode==="approve") act=`<span>Duyệt và chuyển sang <b>Xong</b>?</span><button class="ok" data-r="approve-go" ${st.busy?"disabled":""}>${st.busy?"Đang ghi…":"Xác nhận duyệt"}</button><button class="ghost" data-r="cancel">Huỷ</button>`;
    else if(st.mode==="return") act=`<label class="wide" style="flex-basis:100%">Cần sửa gì? (agent sẽ đọc ghi chú này)<textarea id="rn-${esc(t.id)}" data-r="note">${esc(st.note)}</textarea></label><button class="primary" data-r="return-go" ${st.busy?"disabled":""}>${st.busy?"Đang ghi…":"Gửi trả lại"}</button><button class="ghost" data-r="cancel">Huỷ</button>`;
    else act=`<button class="ok" data-r="approve">✓ Duyệt</button><button data-r="return">↩ Trả lại sửa</button><span class="grow"></span><button class="ghost" data-r="detail" title="Mở trong bảng Việc">Chi tiết</button><button class="ghost" data-r="copy" title="Chép link tới việc này">🔗 Link</button>`;
    return `<article class="rcard who-${esc(t.who)} ${flashId===t.id?"flash":""}" id="duyet-${esc(t.id)}" data-id="${esc(t.id)}">
      <div class="m"><span class="chip who">${esc((WHO[t.who]||{}).n||t.who)}</span>${need?'<span class="chip" style="color:var(--warn)">Cần thầy quyết định</span>':""}${t.due?`<span class="chip num ${t.due<today()?"late":""}">Hạn ${esc(t.due)}</span>`:""}${p?`<span>${esc(p.name)}</span>`:""}<span>nộp ${esc(fmtWhen(t.updated_at))}</span></div>
      <a class="t" href="#duyet-${esc(t.id)}" style="color:inherit;text-decoration:none">${esc(t.title)}</a>
      <div class="rblock ${st.open?"":"clamp"}"><div class="eyebrow">Kết quả</div><p>${esc(r.kq||"(agent chưa ghi kết quả)")}</p></div>
      ${st.open&&r.vd?`<div class="rblock"><div class="eyebrow">Vấn đề</div><p>${esc(r.vd)}</p></div>`:""}
      ${r.qd?`<div class="rblock q ${need?"need":""}"><div class="eyebrow">Cần quyết định</div><p>${esc(r.qd)}</p></div>`:""}
      <button class="link" data-r="toggle">${st.open?"Thu gọn":"Xem đầy đủ kết quả"+(r.vd?" và vấn đề":"")}</button>
      ${links.length?`<div class="rlinks">${links.map(u=>`<a href="${esc(u)}" target="_blank" rel="noopener">↗ ${esc(linkLabel(u))}</a>`).join("")}</div>`:""}
      <div class="ractions">${act}</div>
      ${st.msg?`<div class="hint" style="color:var(--bad)">${esc(st.msg)}</div>`:""}
    </article>`;
  }).join("");
  const d=$("#rdone"); d.hidden=!reviewedRecent.length;
  d.innerHTML=reviewedRecent.length?`<div class="eyebrow" style="margin-top:8px">Vừa xử lý</div>`+reviewedRecent.map(x=>`<div>${x.decision==="approve"?"✓ Đã duyệt":"↩ Đã trả lại"} — ${esc(x.title)} <span class="num">${esc(x.at)}</span></div>`).join(""):"";
}
async function reviewAct(id,decision){
  const st=rstate[id], t=tasks.find(x=>x.id===id); if(!st||!t) return;
  if(decision==="return"&&!st.note.trim()){st.msg="Ghi chú cần sửa gì trước khi trả lại.";renderReview();return;}
  st.busy=true;st.msg="";renderReview();
  try{
    await api("review","POST",{id,decision,note:decision==="return"?st.note:""});
    reviewedRecent.unshift({title:t.title,decision,at:new Date().toLocaleTimeString("vi-VN",{hour:"2-digit",minute:"2-digit"})});reviewedRecent=reviewedRecent.slice(0,8);
    delete rstate[id]; toast(decision==="approve"?"Đã duyệt — chuyển Xong":"Đã trả lại — chuyển Đang làm");
    if(location.hash==="#duyet-"+id) history.replaceState(null,"",location.pathname+"#duyet");
    await refresh();
  }catch(e){st.busy=false;st.msg="Ghi thất bại: "+e.message+". Bấm tải lại trang để kiểm tra trước khi thử lần nữa.";renderReview();}
}
function selectTab(name){
  tab=name;document.querySelectorAll(".tabs button").forEach(x=>x.setAttribute("aria-selected",x.dataset.tab===name));
  ["tasks","review","projects","usage"].forEach(s=>$("#sec-"+s).hidden=s!==name);try{localStorage.setItem("hv-tab",name)}catch{};renderAll();
}
function applyHash(){
  const h=decodeURIComponent(location.hash||"");
  if(h==="#duyet"){selectTab("review");window.scrollTo({top:0});return;}
  let m=h.match(/^#duyet-([A-Za-z0-9_-]+)$/);
  if(m){selectTab("review");flashId=m[1];renderReview();const c=document.getElementById("duyet-"+m[1]);
    if(c){c.scrollIntoView({block:"start",behavior:matchMedia("(prefers-reduced-motion: reduce)").matches?"auto":"smooth"});}
    else if(tasks.length){const t=tasks.find(x=>x.id===m[1]);toast(t?("Việc này đang ở trạng thái "+(STATUS[t.status]||t.status)):"Không tìm thấy việc này");}
    return;}
  m=h.match(/^#viec-([A-Za-z0-9_-]+)$/);
  if(m){selectTab("tasks");sel=m[1];renderBoard();renderDrawer();return;}
}

function renderProjects(){
  const el=$("#plist");
  if(!projects.length){el.innerHTML=`<div class="empty"><b>Chưa có dự án.</b><span>Dự án gom nhiều việc; tiến độ tính theo số việc đã xong.</span></div>`;return;}
  el.innerHTML=projects.map(p=>{
    const ts=tasks.filter(t=>t.projectId===p.id), done=ts.filter(t=>t.status==="xong").length, pct=ts.length?Math.round(100*done/ts.length):0;
    return `<div class="proj"><div><b>${esc(p.name)}</b><div class="hint">${esc(GROUPS[p.group]||p.group||"")}${p.due?" · hạn <span class='num'>"+esc(p.due)+"</span>":""}${p.goal?" · "+esc(p.goal):""}</div></div>
      <div class="num" style="text-align:right">${done}/${ts.length}<div class="hint">${pct}%</div></div><div class="bar"><i style="width:${pct}%"></i></div></div>`;
  }).join("");
}

function renderUsage(){
  const m=monthTotals();
  $("#meters").innerHTML=USAGE_KEYS.map(k=>{
    const b=budgets[k]||0, v=m[k]||0, pct=b?Math.min(100,Math.round(100*v/b)):0;
    return `<div class="meter who-${USAGE_WHO[k]} ${pct>=80?"hot":""}"><div class="lbl"><span>${USAGE_LABEL[k]}</span><span class="num">${fmt(v)} / ${fmt(b)} · ${pct}%</span></div><div class="trk"><i style="width:${pct}%"></i></div></div>`;
  }).join("");
  // chart: last 14 days, stacked share-of-budget per AI
  const days=[...Array(14)].map((_,i)=>{const d=new Date();d.setDate(d.getDate()-13+i);return d.toISOString().slice(0,10)});
  const rows=days.map(d=>usage.find(u=>u.id===d)||{id:d});
  const norm=(r,k)=>budgets[k]?(+r[k]||0)/budgets[k]*30:0; // % of a daily share of budget
  const max=Math.max(1,...rows.map(r=>USAGE_KEYS.reduce((s,k)=>s+norm(r,k),0)));
  $("#chart").innerHTML=rows.map(r=>`<div class="d" title="${r.id}">${USAGE_KEYS.map(k=>`<i class="who-${USAGE_WHO[k]}" style="height:${Math.round(100*norm(r,k)/max)}%;background:var(--who)"></i>`).join("")}<span>${r.id.slice(8)}</span></div>`).join("");
  $("#legend").innerHTML=USAGE_KEYS.map(k=>`<span class="who-${USAGE_WHO[k]}"><b></b>${USAGE_LABEL[k]}</span>`).join("")+`<span>Cột = % hạn mức ngày (hạn mức tháng / 30)</span>`;
  const sorted=[...usage].sort((a,b)=>b.id.localeCompare(a.id)).slice(0,31);
  $("#usageTable").innerHTML=`<tr><th>Ngày</th>${USAGE_KEYS.map(k=>`<th class="r">${USAGE_LABEL[k]}</th>`).join("")}<th>Ghi chú</th></tr>`+
    (sorted.map(u=>`<tr><td class="num">${esc(u.id)}</td>${USAGE_KEYS.map(k=>`<td class="r num">${fmt(u[k])}</td>`).join("")}<td>${esc(u.note||"")}</td></tr>`).join("")||`<tr><td colspan="8" class="hint">Chưa có ngày nào được ghi.</td></tr>`);
  const t=usage.find(u=>u.id===today())||{};
  USAGE_KEYS.forEach(k=>{const i=$("#u-"+k);if(i&&document.activeElement!==i)i.value=t[k]||""});
  if(document.activeElement!==$("#u-note"))$("#u-note").value=t.note||"";
}

async function api(action,method="GET",body=null,qs={}){
  const u=new URL("api.php",location.href);u.searchParams.set("action",action);Object.entries(qs).forEach(([k,v])=>v!==""&&v!=null&&u.searchParams.set(k,v));
  const r=await fetch(u,{method,headers:body?{"Content-Type":"application/json"}:{},body:body?JSON.stringify(body):null,credentials:"same-origin"});
  const j=await r.json().catch(()=>({error:"Phản hồi lỗi"}));
  if(r.status===401){showLogin();throw new Error(j.error||"Chưa đăng nhập");}
  if(!r.ok)throw new Error(j.error||("HTTP "+r.status));
  return j;
}
async function write(ref,data,msg){ // ref = {action, method, qs}
  try{await api(ref.action,ref.method||"POST",data,ref.qs||{});if(msg)toast(msg);await refresh();}
  catch(e){toast("Ghi thất bại: "+e.message);}
}
async function refresh(){
  try{
    const [t,p,u,b]=await Promise.all([api("tasks","GET",null,{includeDone:"1"}),api("projects"),api("usage","GET",null,{days:40}),api("budgets")]);
    tasks=t;projects=p;usage=u.days.map(d=>({id:d.day,...d}));budgets={...DEFAULT_BUDGETS,...b};
    projectOptions();renderAll();$("#conn").textContent="Cập nhật "+new Date().toLocaleTimeString("vi-VN",{hour:"2-digit",minute:"2-digit"});$("#conn").classList.remove("off");
  }catch(e){$("#conn").textContent=e.message;$("#conn").classList.add("off");}
}
function showLogin(){$("#login").hidden=false;$("#app").hidden=true;}
function setRO(){document.querySelectorAll("button.primary,#d-del").forEach(b=>b.disabled=true);$("#conn").textContent="Chỉ xem (không có quyền ghi)";$("#conn").classList.add("off")}

function renderAll(){renderStrip();if(tab==="tasks"){renderBoard();renderDrawer()}else{$("#drawer").hidden=true;$("#main").classList.remove("with-drawer")}if(tab==="review")renderReview();if(tab==="projects")renderProjects();if(tab==="usage")renderUsage();}

function wire(){
  document.querySelectorAll(".tabs button").forEach(b=>b.onclick=()=>{selectTab(b.dataset.tab);history.replaceState(null,"",location.pathname+(b.dataset.tab==="review"?"#duyet":""))});
  $("#strip").addEventListener("click",e=>{const g=e.target.closest("[data-go]");if(g)location.hash=g.dataset.go});
  $("#strip").addEventListener("keydown",e=>{const g=e.target.closest("[data-go]");if(g&&e.key==="Enter")location.hash=g.dataset.go});
  window.addEventListener("hashchange",applyHash);
  $("#rlist").addEventListener("input",e=>{if(e.target.dataset.r==="note"){const id=e.target.closest(".rcard").dataset.id;rstate[id].note=e.target.value}});
  $("#rlist").addEventListener("click",async e=>{
    const b=e.target.closest("[data-r]");if(!b||b.dataset.r==="note")return;const id=b.closest(".rcard").dataset.id, st=rstate[id];if(!st)return;
    const a=b.dataset.r;
    if(a==="approve"||a==="return"){st.mode=a;st.msg="";renderReview();if(a==="return")setTimeout(()=>document.getElementById("rn-"+id)?.focus(),0);return;}
    if(a==="cancel"){st.mode="";st.msg="";renderReview();return;}
    if(a==="toggle"){st.open=!st.open;renderReview();return;}
    if(a==="approve-go"){await reviewAct(id,"approve");return;}
    if(a==="return-go"){await reviewAct(id,"return");return;}
    if(a==="detail"){location.hash="#viec-"+id;return;}
    if(a==="copy"){const u=permalink(id);try{await navigator.clipboard.writeText(u);toast("Đã chép link việc này")}catch{prompt("Chép link:",u)}return;}
  });
  ["#f-who","#f-group","#f-project","#f-done"].forEach(s=>$(s).onchange=renderBoard);
  $("#board").addEventListener("click",e=>{const c=e.target.closest(".card");if(!c)return;sel=c.dataset.id;renderBoard();renderDrawer()});
  $("#board").addEventListener("keydown",e=>{if(e.key==="Enter"){const c=e.target.closest(".card");if(c){sel=c.dataset.id;renderBoard();renderDrawer()}}});
  $("#nt-add").onclick=async()=>{
    const title=$("#nt-title").value.trim();if(!title){toast("Nhập tên việc");return;}
    await write({action:"tasks"},{title,projectId:$("#nt-project").value,group:$("#nt-group").value,who:$("#nt-who").value,priority:+$("#nt-pri").value,due:$("#nt-due").value||"",desc:$("#nt-desc").value},"Đã thêm việc");
    $("#nt-title").value="";$("#nt-desc").value="";$("#nt-due").value="";
  };
  $("#nt-suggest").onclick=async()=>{
    const text=[$("#nt-title").value.trim(),$("#nt-desc").value.trim()].filter(Boolean).join("\n");
    if(!text){toast("Gõ mô tả thô vào ô Việc mới trước");return;}
    const b=$("#nt-suggest");b.disabled=true;b.textContent="Đang phân tích…";
    try{const r=await api("suggest","POST",{text});
      $("#nt-title").value=r.title||$("#nt-title").value;$("#nt-group").value=r.group;$("#nt-who").value=r.who;$("#nt-pri").value=String(r.priority||2);$("#nt-due").value=r.due||"";$("#nt-desc").value=r.desc||"";
      if(r.projectId&&[...$("#nt-project").options].some(o=>o.value===r.projectId))$("#nt-project").value=r.projectId;
      const rs=$("#nt-reason");rs.hidden=false;rs.style.color="";rs.textContent="Gợi ý ("+(r.model||"gemini")+", "+(r.tokens||0)+" token): "+(r.reason||"")+" — kiểm tra rồi bấm Thêm việc.";
    }catch(e){const rs=$("#nt-reason");rs.hidden=false;rs.style.color="var(--bad)";rs.textContent="Gợi ý thất bại: "+e.message;}finally{b.disabled=false;b.textContent="✨ Gợi ý điền";}
  };
  $("#nt-claude").onclick=async()=>{
    const text=[$("#nt-title").value.trim(),$("#nt-desc").value.trim()].filter(Boolean).join("\n");
    if(!text){toast("Gõ mô tả thô vào ô Việc mới trước");return;}
    await write({action:"tasks"},{title:text.split("\n")[0].slice(0,90),desc:"[Claude phân loại] "+text,who:"claude",priority:2,group:$("#nt-group").value,projectId:$("#nt-project").value},"Đã giao Claude quản gia phân loại (phiên sáng)");
    $("#nt-title").value="";$("#nt-desc").value="";
  };
  $("#settings-btn").onclick=async()=>{const p=$("#settings");p.hidden=!p.hidden;if(!p.hidden){try{const r=await api("settings");const g=r.gemini_auto||{};$("#set-model-info").textContent=g.last_ok?"Đang dùng: "+g.last_ok:(g.candidates&&g.candidates.length?"Sẽ thử: "+g.candidates.join(", "):"Chưa chọn (bấm Kiểm tra key).");$("#set-info").textContent=r.gemini_api_key_masked?"Key hiện có: "+r.gemini_api_key_masked:"Chưa có key.";}catch(e){$("#set-info").textContent=e.message}}};
  $("#set-close").onclick=()=>{$("#settings").hidden=true};
  $("#set-test").onclick=async()=>{const o=$("#set-test-out"),b=$("#set-test");o.hidden=false;o.style.color="";o.textContent="Đang gọi thử Gemini…";b.disabled=true;
    try{if($("#set-key").value.trim()){await api("settings","POST",{gemini_api_key:$("#set-key").value});$("#set-key").value="";}
      const r=await api("gemini_test");o.style.color=r.ok?"var(--good)":"var(--bad)";
      o.textContent=(r.ok?"✓ ":"✗ ")+r.message+(r.tried&&r.tried.length>1?" Đã thử: "+r.tried.join(", ")+".":"")+(r.models&&r.models.length?" Key dùng được: "+r.models.join(", ")+".":"");
      if(r.ok)$("#set-model-info").textContent="Đang dùng: "+r.model;
    }catch(e){o.style.color="var(--bad)";o.textContent="✗ "+e.message}finally{b.disabled=false}};
  $("#set-save").onclick=async()=>{try{await api("settings","POST",{gemini_api_key:$("#set-key").value});$("#set-key").value="";toast("Đã lưu cài đặt");$("#settings").hidden=true;}catch(e){toast(e.message)}};
  $("#np-add").onclick=async()=>{
    const name=$("#np-name").value.trim();if(!name){toast("Nhập tên dự án");return;}
    await write({action:"projects"},{name,group:$("#np-group").value,due:$("#np-due").value||"",goal:$("#np-goal").value},"Đã thêm dự án");
    $("#np-name").value="";$("#np-goal").value="";
  };
  document.addEventListener("click",async e=>{
    if(e.target.id!=="u-save")return;
    const data={day:today(),mode:"set",note:$("#u-note").value};
    USAGE_KEYS.forEach(k=>data[k]=+($("#u-"+k).value||0));
    await write({action:"usage"},data,"Đã lưu tiêu hao hôm nay");
  });
}

async function start(){
  fillSelects();wire();
  try{const t=localStorage.getItem("hv-tab");if(!location.hash&&t&&$("#tab-"+t))selectTab(t);}catch{}
  renderAll();
  $("#pw-go").onclick=async()=>{try{await api("login","POST",{password:$("#pw").value});$("#login").hidden=true;$("#app").hidden=false;$("#pw").value="";await refresh();applyHash();}catch(e){$("#pw-msg").textContent=e.message;}};
  $("#pw").addEventListener("keydown",e=>{if(e.key==="Enter")$("#pw-go").click()});
  $("#logout").onclick=async()=>{await api("logout","POST",{});showLogin();};
  const w=await fetch("api.php?action=whoami",{credentials:"same-origin"}).then(r=>r.json()).catch(()=>({}));
  if(!w.loggedIn){showLogin();return;}
  await refresh();
  applyHash();
  setInterval(()=>{if(document.visibilityState==="visible")refresh()},60000);
}
start();
</script>
</div></body></html>
