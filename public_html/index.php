<?php if(!file_exists(__DIR__."/config.php")){header("Location: setup.php");exit;} ?>
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
    <button role="tab" aria-selected="false" data-tab="projects" id="tab-projects">Dự án</button>
    <button role="tab" aria-selected="false" data-tab="usage" id="tab-usage">Tiêu hao</button>
  </nav>
  <div class="spacer"></div>
  <div class="status" id="conn">Đang tải…</div><button class="ghost" id="logout" title="Đăng xuất">Thoát</button>
</header>

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
      <div class="actions"><button class="primary" id="nt-add">Thêm việc</button></div>
    </div>
    <div class="toolbar">
      <select id="f-who"><option value="">Mọi agent</option></select>
      <select id="f-group"><option value="">Mọi nhóm</option></select>
      <select id="f-project"><option value="">Mọi dự án</option></select>
      <label style="display:flex;gap:6px;align-items:center;font-size:13px;color:var(--fg)"><input type="checkbox" id="f-done" style="width:auto"> hiện việc đã xong</label>
    </div>
    <div class="board" id="board"></div>
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
    ["Chờ Tuấn duyệt",review,"kết quả agent đã trả",""],
    ["Claude tháng này",pct("claude_tokens")+"%",fmt(m.claude_tokens)+" / "+fmt(budgets.claude_tokens)+" token",pct("claude_tokens")>80?"bad":""],
    ["Gemini API",pct("gemini_tokens")+"%",fmt(m.gemini_tokens)+" token",""],
    ["NotebookLM hôm nay",(usage.find(u=>u.id===today())||{}).nlm_queries||0,"/ 50 truy vấn (free)",""]
  ].map(([l,v,s,c])=>`<div class="tile"><div class="eyebrow">${l}</div><div class="v num" style="${c==="bad"?"color:var(--bad)":""}">${v}</div><div class="s">${s}</div></div>`).join("");
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
  <div class="hint num">id: ${esc(t.id)} · tạo ${esc((t.createdAt||"").slice(0,16).replace("T"," "))}${t.updatedAt?" · sửa "+esc(t.updatedAt.slice(0,16).replace("T"," ")):""}</div>
  <div class="actions"><button id="d-del">Xoá</button><button class="primary" id="d-save">Lưu</button></div>`;
  $("#d-close").onclick=()=>{sel=null;renderBoard();renderDrawer()};
  $("#d-save").onclick=async()=>{
    await write({action:"task",qs:{id:t.id}},{status:$("#d-status").value,who:$("#d-who").value,priority:+$("#d-pri").value,due:$("#d-due").value||"",desc:$("#d-desc").value},"Đã lưu");
  };
  $("#d-del").onclick=async()=>{
    if(d.dataset.confirm!==t.id){d.dataset.confirm=t.id;$("#d-del").textContent="Bấm lần nữa để xoá";return;}
    sel=null;await write({action:"task",method:"DELETE",qs:{id:t.id}},{id:t.id},"Đã xoá");
  };
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

function renderAll(){renderStrip();if(tab==="tasks"){renderBoard();renderDrawer()}else{$("#drawer").hidden=true;$("#main").classList.remove("with-drawer")}if(tab==="projects")renderProjects();if(tab==="usage")renderUsage();}

function wire(){
  document.querySelectorAll(".tabs button").forEach(b=>b.onclick=()=>{tab=b.dataset.tab;document.querySelectorAll(".tabs button").forEach(x=>x.setAttribute("aria-selected",x===b));
    ["tasks","projects","usage"].forEach(s=>$("#sec-"+s).hidden=s!==tab);try{localStorage.setItem("hv-tab",tab)}catch{};renderAll()});
  ["#f-who","#f-group","#f-project","#f-done"].forEach(s=>$(s).onchange=renderBoard);
  $("#board").addEventListener("click",e=>{const c=e.target.closest(".card");if(!c)return;sel=c.dataset.id;renderBoard();renderDrawer()});
  $("#board").addEventListener("keydown",e=>{if(e.key==="Enter"){const c=e.target.closest(".card");if(c){sel=c.dataset.id;renderBoard();renderDrawer()}}});
  $("#nt-add").onclick=async()=>{
    const title=$("#nt-title").value.trim();if(!title){toast("Nhập tên việc");return;}
    await write({action:"tasks"},{title,projectId:$("#nt-project").value,group:$("#nt-group").value,who:$("#nt-who").value,priority:+$("#nt-pri").value,due:$("#nt-due").value||"",desc:$("#nt-desc").value},"Đã thêm việc");
    $("#nt-title").value="";$("#nt-desc").value="";$("#nt-due").value="";
  };
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
  try{const t=localStorage.getItem("hv-tab");if(t&&$("#tab-"+t))$("#tab-"+t).click();}catch{}
  renderAll();
  $("#pw-go").onclick=async()=>{try{await api("login","POST",{password:$("#pw").value});$("#login").hidden=true;$("#app").hidden=false;$("#pw").value="";await refresh();}catch(e){$("#pw-msg").textContent=e.message;}};
  $("#pw").addEventListener("keydown",e=>{if(e.key==="Enter")$("#pw-go").click()});
  $("#logout").onclick=async()=>{await api("logout","POST",{});showLogin();};
  const w=await fetch("api.php?action=whoami",{credentials:"same-origin"}).then(r=>r.json()).catch(()=>({}));
  if(!w.loggedIn){showLogin();return;}
  await refresh();
  setInterval(()=>{if(document.visibilityState==="visible")refresh()},60000);
}
start();
</script>
</div></body></html>
