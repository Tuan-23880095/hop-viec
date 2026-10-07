<?php
// Hộp việc — thư viện dùng chung (PHP 8.x + SQLite)
declare(strict_types=1);
mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Ho_Chi_Minh');

define('HV_ROOT', __DIR__);
// Dữ liệu + config nằm NGOÀI thư mục deploy (để Git auto-deploy không xoá mất):
// đi lên từ thư mục app, tìm thư mục 'public_html' cao nhất và dùng <cha của nó>/hopviec-data.
// Ví dụ Hostinger: domains/hopviec.diemdanhsv.com/public_html/... -> domains/hopviec.diemdanhsv.com/hopviec-data
// Có thể ép bằng biến môi trường HV_DATA_DIR. Nếu không ghi được thì dùng <app>/data.
function hv_data_dir(): string {
    static $dir = null; if ($dir) return $dir;
    $cands = [];
    if (!empty($_SERVER['HV_DATA_DIR'])) $cands[] = $_SERVER['HV_DATA_DIR'];
    elseif (getenv('HV_DATA_DIR')) $cands[] = getenv('HV_DATA_DIR');
    $top = null; $d = HV_ROOT;
    for ($i = 0; $i < 6; $i++) { if (basename($d) === 'public_html') $top = $d; $parent = dirname($d); if ($parent === $d) break; $d = $parent; }
    if ($top) $cands[] = dirname($top) . '/hopviec-data';
    $cands[] = HV_ROOT . '/data';
    foreach ($cands as $c) { if (!is_dir($c)) @mkdir($c, 0750, true); if (is_dir($c) && is_writable($c)) return $dir = $c; }
    return $dir = HV_ROOT . '/data';
}
define('HV_DATA', hv_data_dir());
define('HV_CONFIG', HV_DATA . '/config.php');
if (!file_exists(HV_DATA . '/.htaccess')) @file_put_contents(HV_DATA . '/.htaccess', "Require all denied\n");

function hv_config(): ?array {
    if (!file_exists(HV_CONFIG)) return null;
    $c = include HV_CONFIG;
    return is_array($c) ? $c : null;
}

function hv_db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . HV_DATA . '/hopviec.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');
    hv_migrate($pdo);
    return $pdo;
}

function hv_migrate(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id TEXT PRIMARY KEY, name TEXT NOT NULL, grp TEXT DEFAULT '', due TEXT DEFAULT '', goal TEXT DEFAULT '',
        status TEXT DEFAULT 'mo', created_at TEXT, updated_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id TEXT PRIMARY KEY, project_id TEXT DEFAULT '', title TEXT NOT NULL, grp TEXT DEFAULT '', who TEXT DEFAULT 'claude',
        priority INTEGER DEFAULT 2, due TEXT DEFAULT '', descr TEXT DEFAULT '', status TEXT DEFAULT 'moi',
        result TEXT DEFAULT '', result_link TEXT DEFAULT '', usage_json TEXT DEFAULT '{}',
        created_at TEXT, created_by TEXT DEFAULT '', updated_at TEXT, updated_by TEXT DEFAULT '')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS usage (
        day TEXT PRIMARY KEY, claude_tokens INTEGER DEFAULT 0, opus_tokens INTEGER DEFAULT 0, gemini_tokens INTEGER DEFAULT 0,
        agy_credits INTEGER DEFAULT 0, nlm_queries INTEGER DEFAULT 0, spark_tasks INTEGER DEFAULT 0,
        note TEXT DEFAULT '', updated_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS budgets (key TEXT PRIMARY KEY, value INTEGER NOT NULL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS log (id INTEGER PRIMARY KEY AUTOINCREMENT, ts TEXT, actor TEXT, action TEXT, task_id TEXT, detail TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)");
    $n = (int)$pdo->query("SELECT COUNT(*) FROM budgets")->fetchColumn();
    if ($n === 0) {
        $st = $pdo->prepare("INSERT INTO budgets(key,value) VALUES(?,?)");
        foreach (['claude_tokens'=>2000000,'opus_tokens'=>300000,'gemini_tokens'=>5000000,'agy_credits'=>1000,'nlm_queries'=>1200,'spark_tasks'=>300] as $k=>$v) $st->execute([$k,$v]);
    }
}

const HV_USAGE_KEYS = ['claude_tokens','opus_tokens','gemini_tokens','agy_credits','nlm_queries','spark_tasks'];
const HV_WHO = ['claude','gemini','notebooklm','antigravity','spark','co-van','tuan'];
const HV_STATUS = ['moi','dang_lam','cho_duyet','chan','xong'];
const HV_GROUPS = ['nghien-cuu','giang-day','edtech','quan-ly'];

function hv_now(): string { return date('c'); }
function hv_today(): string { return date('Y-m-d'); }
function hv_id(string $p): string { return $p . base_convert((string)(int)(microtime(true)*1000), 10, 36) . substr(bin2hex(random_bytes(2)),0,3); }

function hv_json(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function hv_task_out(array $r): array {
    $r['usage'] = json_decode($r['usage_json'] ?: '{}', true) ?: [];
    $r['priority'] = (int)$r['priority'];
    unset($r['usage_json']);
    $r['desc'] = $r['descr']; unset($r['descr']);
    $r['group'] = $r['grp']; unset($r['grp']);
    $r['projectId'] = $r['project_id']; unset($r['project_id']);
    $r['resultLink'] = $r['result_link']; unset($r['result_link']);
    return $r;
}
function hv_project_out(array $r): array { $r['group']=$r['grp']; unset($r['grp']); return $r; }

function hv_log(string $actor, string $action, string $taskId = '', string $detail = ''): void {
    hv_db()->prepare("INSERT INTO log(ts,actor,action,task_id,detail) VALUES(?,?,?,?,?)")
        ->execute([hv_now(), $actor, $action, $taskId, mb_substr($detail,0,500)]);
}

// ---------- Thao tác dữ liệu (dùng chung cho API và MCP) ----------
function hv_list_tasks(array $f = []): array {
    $w = []; $p = [];
    if (!empty($f['status'])) { $w[] = 'status = ?'; $p[] = $f['status']; }
    if (!empty($f['who'])) { $w[] = 'who = ?'; $p[] = $f['who']; }
    if (!empty($f['projectId'])) { $w[] = 'project_id = ?'; $p[] = $f['projectId']; }
    if (!empty($f['group'])) { $w[] = 'grp = ?'; $p[] = $f['group']; }
    if (empty($f['includeDone'])) { $w[] = "status != 'xong'"; }
    $sql = 'SELECT * FROM tasks' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY priority DESC, due ASC, created_at ASC';
    $st = hv_db()->prepare($sql); $st->execute($p);
    return array_map('hv_task_out', $st->fetchAll());
}
function hv_get_task(string $id): ?array {
    $st = hv_db()->prepare('SELECT * FROM tasks WHERE id = ?'); $st->execute([$id]);
    $r = $st->fetch(); return $r ? hv_task_out($r) : null;
}
function hv_add_task(array $d, string $actor): array {
    $id = $d['id'] ?? hv_id('t');
    $who = in_array($d['who'] ?? '', HV_WHO, true) ? $d['who'] : 'claude';
    hv_db()->prepare("INSERT INTO tasks(id,project_id,title,grp,who,priority,due,descr,status,result,result_link,usage_json,created_at,created_by,updated_at,updated_by)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
        $id, (string)($d['projectId'] ?? ''), trim((string)($d['title'] ?? 'Việc chưa đặt tên')), (string)($d['group'] ?? ''), $who,
        max(1, min(3, (int)($d['priority'] ?? 2))), (string)($d['due'] ?? ''), (string)($d['desc'] ?? ''),
        in_array($d['status'] ?? '', HV_STATUS, true) ? $d['status'] : 'moi', (string)($d['result'] ?? ''), (string)($d['resultLink'] ?? ''),
        json_encode($d['usage'] ?? new stdClass()), hv_now(), $actor, hv_now(), $actor]);
    hv_log($actor, 'add_task', $id, (string)($d['title'] ?? ''));
    return hv_get_task($id);
}
function hv_update_task(string $id, array $d, string $actor): ?array {
    $cur = hv_get_task($id); if (!$cur) return null;
    $map = ['title'=>'title','projectId'=>'project_id','group'=>'grp','who'=>'who','priority'=>'priority','due'=>'due','desc'=>'descr','status'=>'status','result'=>'result','resultLink'=>'result_link'];
    $set = []; $p = [];
    foreach ($map as $k => $col) if (array_key_exists($k, $d)) { $set[] = "$col = ?"; $p[] = $k === 'priority' ? max(1,min(3,(int)$d[$k])) : (string)$d[$k]; }
    if (isset($d['usage']) && is_array($d['usage'])) { // cộng dồn tiêu hao
        $u = $cur['usage']; foreach ($d['usage'] as $k => $v) if (in_array($k, HV_USAGE_KEYS, true)) $u[$k] = (int)($u[$k] ?? 0) + (int)$v;
        $set[] = 'usage_json = ?'; $p[] = json_encode($u);
    }
    if (!$set) return $cur;
    $set[] = 'updated_at = ?'; $p[] = hv_now(); $set[] = 'updated_by = ?'; $p[] = $actor; $p[] = $id;
    hv_db()->prepare('UPDATE tasks SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($p);
    hv_log($actor, 'update_task', $id, json_encode(array_keys($d), JSON_UNESCAPED_UNICODE));
    return hv_get_task($id);
}
function hv_delete_task(string $id, string $actor): bool {
    $n = hv_db()->prepare('DELETE FROM tasks WHERE id = ?'); $n->execute([$id]);
    hv_log($actor, 'delete_task', $id); return $n->rowCount() > 0;
}
function hv_list_projects(): array {
    $rows = hv_db()->query('SELECT * FROM projects ORDER BY created_at ASC')->fetchAll();
    $cnt = hv_db()->query("SELECT project_id, COUNT(*) n, SUM(status='xong') done FROM tasks GROUP BY project_id")->fetchAll();
    $m = []; foreach ($cnt as $c) $m[$c['project_id']] = $c;
    return array_map(function($r) use ($m){ $r = hv_project_out($r); $r['tasks']=(int)($m[$r['id']]['n']??0); $r['done']=(int)($m[$r['id']]['done']??0); return $r; }, $rows);
}
function hv_add_project(array $d, string $actor): array {
    $id = $d['id'] ?? hv_id('p');
    hv_db()->prepare("INSERT INTO projects(id,name,grp,due,goal,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)")
        ->execute([$id, trim((string)($d['name'] ?? 'Dự án')), (string)($d['group'] ?? ''), (string)($d['due'] ?? ''), (string)($d['goal'] ?? ''), 'mo', hv_now(), hv_now()]);
    hv_log($actor, 'add_project', $id, (string)($d['name'] ?? ''));
    foreach (hv_list_projects() as $p) if ($p['id'] === $id) return $p;
    return ['id'=>$id];
}
function hv_update_project(string $id, array $d, string $actor): bool {
    $map = ['name'=>'name','group'=>'grp','due'=>'due','goal'=>'goal','status'=>'status'];
    $set=[];$p=[]; foreach ($map as $k=>$c) if (array_key_exists($k,$d)) { $set[]="$c = ?"; $p[]=(string)$d[$k]; }
    if (!$set) return false; $set[]='updated_at = ?'; $p[]=hv_now(); $p[]=$id;
    hv_db()->prepare('UPDATE projects SET '.implode(', ',$set).' WHERE id = ?')->execute($p);
    hv_log($actor,'update_project',$id); return true;
}
function hv_delete_project(string $id, string $actor): bool {
    $st = hv_db()->prepare('DELETE FROM projects WHERE id = ?'); $st->execute([$id]);
    hv_db()->prepare("UPDATE tasks SET project_id = '' WHERE project_id = ?")->execute([$id]);
    hv_log($actor,'delete_project',$id); return $st->rowCount() > 0;
}
function hv_budgets(): array { $o=[]; foreach (hv_db()->query('SELECT key,value FROM budgets') as $r) $o[$r['key']]=(int)$r['value']; return $o; }
function hv_set_budgets(array $d): array {
    $st = hv_db()->prepare('INSERT INTO budgets(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value');
    foreach ($d as $k=>$v) if (in_array($k, HV_USAGE_KEYS, true)) $st->execute([$k,(int)$v]);
    return hv_budgets();
}
function hv_usage_days(int $days = 31): array {
    $st = hv_db()->prepare('SELECT * FROM usage WHERE day >= ? ORDER BY day DESC'); $st->execute([date('Y-m-d', strtotime("-$days days"))]);
    return array_map(function($r){ foreach (HV_USAGE_KEYS as $k) $r[$k]=(int)$r[$k]; return $r; }, $st->fetchAll());
}
function hv_log_usage(string $day, array $d, string $actor, bool $add = true): array {
    $cur = hv_db()->prepare('SELECT * FROM usage WHERE day = ?'); $cur->execute([$day]); $cur = $cur->fetch() ?: [];
    $vals = []; foreach (HV_USAGE_KEYS as $k) $vals[$k] = ($add ? (int)($cur[$k] ?? 0) : 0) + (int)($d[$k] ?? 0);
    if (!$add) foreach (HV_USAGE_KEYS as $k) if (!array_key_exists($k,$d)) $vals[$k] = (int)($cur[$k] ?? 0);
    $note = isset($d['note']) ? ($add && !empty($cur['note']) ? $cur['note'] . ' | ' . $d['note'] : (string)$d['note']) : ($cur['note'] ?? '');
    hv_db()->prepare('INSERT INTO usage(day,claude_tokens,opus_tokens,gemini_tokens,agy_credits,nlm_queries,spark_tasks,note,updated_at) VALUES(?,?,?,?,?,?,?,?,?)
        ON CONFLICT(day) DO UPDATE SET claude_tokens=excluded.claude_tokens,opus_tokens=excluded.opus_tokens,gemini_tokens=excluded.gemini_tokens,agy_credits=excluded.agy_credits,nlm_queries=excluded.nlm_queries,spark_tasks=excluded.spark_tasks,note=excluded.note,updated_at=excluded.updated_at')
        ->execute([$day,$vals['claude_tokens'],$vals['opus_tokens'],$vals['gemini_tokens'],$vals['agy_credits'],$vals['nlm_queries'],$vals['spark_tasks'],$note,hv_now()]);
    hv_log($actor, $add ? 'add_usage' : 'set_usage', '', $day . ' ' . json_encode($d));
    return array_merge(['day'=>$day], $vals, ['note'=>$note]);
}
function hv_usage_summary(): array {
    $ym = date('Y-m'); $b = hv_budgets(); $m = [];
    foreach (HV_USAGE_KEYS as $k) $m[$k] = 0;
    foreach (hv_db()->query("SELECT * FROM usage WHERE day LIKE '$ym%'") as $r) foreach (HV_USAGE_KEYS as $k) $m[$k] += (int)$r[$k];
    $out = ['month'=>$ym, 'used'=>$m, 'budget'=>$b, 'pct'=>[]];
    foreach (HV_USAGE_KEYS as $k) $out['pct'][$k] = !empty($b[$k]) ? round(100*$m[$k]/$b[$k]) : 0;
    $t = hv_db()->prepare('SELECT * FROM usage WHERE day = ?'); $t->execute([hv_today()]); $out['today'] = $t->fetch() ?: null;
    return $out;
}
function hv_overview(): array {
    $open = hv_list_tasks(); $late = 0; foreach ($open as $t) if ($t['due'] && $t['due'] < hv_today()) $late++;
    $review = (int)hv_db()->query("SELECT COUNT(*) FROM tasks WHERE status='cho_duyet'")->fetchColumn();
    return ['open'=>count($open),'late'=>$late,'review'=>$review,'usage'=>hv_usage_summary(),'today'=>hv_today()];
}

// ---------- Cài đặt (lưu trong CSDL, ngoài thư mục deploy) ----------
function hv_setting(string $k, string $default = ''): string {
    $st = hv_db()->prepare('SELECT value FROM settings WHERE key = ?'); $st->execute([$k]);
    $v = $st->fetchColumn(); return $v === false ? $default : (string)$v;
}
function hv_set_setting(string $k, string $v): void {
    hv_db()->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value')->execute([$k, $v]);
}

// ---------- Gợi ý điền việc bằng Gemini API ----------
const HV_TRIAGE_RULES = <<<'TXT'
Bạn là trợ lý phân loại việc cho Tuấn: giảng viên Khoa Địa chất (ĐH KHTN – ĐHQG-HCM), cũng dạy Toán/KHTN phổ thông, đang học văn bằng 2 CNTT, quản lý đề tài NCKH (vd T2025-03 mô hình hình học laser).
Nhiệm vụ: từ mô tả thô của một việc, điền các trường để giao cho đúng agent, tốn ít token Claude nhất.

group (nhóm việc): nghien-cuu (bài báo, literature review, dữ liệu địa chất, đề tài NCKH, hội nghị), giang-day (bài giảng, đề thi, bài tập, thực địa, điểm danh, sinh viên), edtech (code, web app, script, MCP, cài đặt phần mềm, repo), quan-ly (báo cáo, biểu mẫu, email, lịch, tài chính, hồ sơ).
who (giao cho) — chọn tuyến rẻ nhất đủ làm:
- notebooklm: đọc/tra cứu/tóm tắt tài liệu có sẵn trong notebook, trích dẫn, hỏi đáp trên tài liệu.
- gemini: viết nháp, dịch, tóm tắt văn bản gửi kèm, soạn đề/bài tập hàng loạt, việc lặp theo mẫu.
- antigravity: viết/sửa code, xử lý nhiều file, đọc ảnh/PDF scan/video, chạy script trên máy.
- spark: việc theo lịch trong Gmail/Calendar/Drive (gom email, nhắc hạn, cập nhật bảng).
- claude: cần suy luận, lập luận, phản biện, duyệt chất lượng, viết phần khó nhất, quyết định thiết kế.
- co-van: chỉ khi mô tả nói rõ là vấn đề khó lặp lại, mâu thuẫn ưu tiên, cần quyết định chiến lược.
- tuan: việc chỉ Tuấn làm được (ký, họp, thanh toán, đăng nhập, cấp quyền, đi thực địa).
priority: 3 nếu có hạn ≤ 3 ngày, liên quan điểm/thi/nộp hồ sơ/deadline đề tài, hoặc chặn việc khác; 1 nếu "khi rảnh", ý tưởng, không hạn; còn lại 2.
due: YYYY-MM-DD nếu mô tả nêu hạn (hôm nay là {TODAY}, tuần này = thứ Sáu tuần này, cuối tháng = ngày cuối tháng); rỗng nếu không rõ.
title: ≤ 90 ký tự, bắt đầu bằng động từ, giữ mã đề tài/tên lớp nếu có.
desc: 2–5 dòng cho agent: đầu ra mong muốn (định dạng, độ dài), nguồn (notebook/file/link nếu nêu), giới hạn, tiêu chí đạt. Luôn kết bằng "Nộp theo khuôn KẾT QUẢ / VẤN ĐỀ / CẦN QUYẾT ĐỊNH ≤300 từ."
reason: 1 câu giải thích vì sao giao cho who đó.
Trả về JSON đúng schema, tiếng Việt, không thêm chữ ngoài JSON.
TXT;

// Gọi Gemini generateContent cho một model. Trả về [httpCode, json|null, curlErr, rawBody].
function hv_gemini_call(string $key, string $model, array $body, int $timeout = 60): array {
    if (str_contains($model, '2.5-flash')) $body['generationConfig']['thinkingConfig'] = ['thinkingBudget' => 0]; // nhanh, khỏi bị quá giờ
    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent');
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>$timeout, CURLOPT_CONNECTTIMEOUT=>15,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json', 'x-goog-api-key: '.$key],
        CURLOPT_POSTFIELDS=>json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)]);
    $r = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    return [$code, json_decode((string)$r, true), $err, (string)$r];
}
// Xếp hạng tên model văn bản dòng flash (hàm thuần, dễ kiểm thử).
// Giữ: gemini-X.Y-flash, gemini-X.Y-flash-lite, gemini-flash-latest, gemini-flash-lite-latest.
// Loại: preview/exp (hạn mức thấp), omni, image, tts, live, audio, embedding…
// Thứ tự: flash thường bản cao nhất → alias flash-latest → flash-lite bản cao nhất → flash-lite-latest.
function hv_gemini_rank(array $names): array {
    $keep = [];
    foreach ($names as $n) {
        $n = str_replace('models/', '', (string)$n);
        if (preg_match('/omni|image|tts|live|audio|embedding|robotics|learnlm|preview|exp|transcribe|translate/', $n)) continue;
        if (preg_match('/^gemini-(\d+(\.\d+)?)-flash(-lite)?$/', $n) || preg_match('/^gemini-flash(-lite)?-latest$/', $n)) $keep[] = $n;
    }
    $keep = array_values(array_unique($keep));
    usort($keep, function($a, $b) {
        $k = function($n) { $lite = str_contains($n, 'lite') ? 1 : 0; $alias = str_ends_with($n, '-latest') ? 1 : 0;
            $v = preg_match('/^gemini-(\d+(?:\.\d+)?)/', $n, $m) ? (float)$m[1] : 0; return [$lite, $alias, -$v, $n]; };
        return $k($a) <=> $k($b);
    });
    return $keep;
}
// Hỏi Google danh sách model key này gọi được.
function hv_gemini_models(string $key): array {
    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models?pageSize=200');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['x-goog-api-key: '.$key], CURLOPT_TIMEOUT=>20, CURLOPT_CONNECTTIMEOUT=>10]);
    $r = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    $j = json_decode((string)$r, true);
    $names = [];
    foreach ($j['models'] ?? [] as $m) if (in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true)) $names[] = (string)$m['name'];
    return ['code'=>$code, 'err'=>$err, 'error'=>$j['error']['message'] ?? '', 'status'=>$j['error']['status'] ?? '', 'models'=>hv_gemini_rank($names)];
}
const HV_GEMINI_FALLBACK = ['gemini-flash-latest', 'gemini-3.8-flash', 'gemini-3.5-flash', 'gemini-3.5-flash-lite', 'gemini-flash-lite-latest'];
// Tự chọn model: danh sách ứng viên theo thứ tự ưu tiên, lưu đệm trong ngày. Model chạy được lần trước đứng đầu.
function hv_gemini_candidates(string $key, bool $refresh = false): array {
    $cache = json_decode(hv_setting('gemini_auto', '{}'), true) ?: [];
    if (!$refresh && ($cache['day'] ?? '') === hv_today() && !empty($cache['models'])) $list = $cache['models'];
    else {
        $ls = hv_gemini_models($key);
        $list = ($ls['code'] === 200 && $ls['models']) ? $ls['models'] : HV_GEMINI_FALLBACK;
        hv_set_setting('gemini_auto', json_encode(['day'=>hv_today(), 'models'=>$list, 'from'=>($ls['code'] === 200 ? 'google' : 'fallback')]));
    }
    $ok = hv_setting('gemini_last_ok');
    if ($ok !== '' && in_array($ok, $list, true)) $list = array_values(array_unique(array_merge([$ok], $list)));
    return $list;
}
// Lời giải thích dễ hiểu cho lỗi Gemini.
function hv_gemini_explain(int $code, ?array $j, string $err, string $raw): string {
    if ($err !== '') return 'Không kết nối được tới Gemini (' . $err . '). Thử lại sau ít phút.';
    $msg = (string)($j['error']['message'] ?? mb_substr($raw, 0, 200)); $status = (string)($j['error']['status'] ?? '');
    $reason = ''; foreach ($j['error']['details'] ?? [] as $d) if (!empty($d['reason'])) $reason = (string)$d['reason'];
    if ($reason === 'API_KEY_INVALID' || stripos($msg, 'API key not valid') !== false) return 'Gemini API key không hợp lệ (sai, đã xoá hoặc dán thiếu). Tạo key mới ở aistudio.google.com/app/apikey rồi dán vào Cài đặt ⚙.';
    if ($code === 403 || $status === 'PERMISSION_DENIED') return 'Key không có quyền gọi Gemini (dự án Google Cloud chưa bật Generative Language API, hoặc key bị giới hạn IP/referrer). Chi tiết: ' . $msg;
    if ($code === 429 || $status === 'RESOURCE_EXHAUSTED') return 'Hết hạn mức Gemini của key này (gói miễn phí giới hạn theo phút/ngày). Đợi rồi thử lại, hoặc đổi model nhẹ hơn trong Cài đặt. Chi tiết: ' . $msg;
    if ($code === 400 && stripos($msg, 'location') !== false) return 'Gemini API không hỗ trợ vị trí máy chủ gửi yêu cầu. Chi tiết: ' . $msg;
    return 'Gemini trả lỗi ' . $code . ($status ? " ($status)" : '') . ': ' . $msg;
}

// Gọi Gemini, tự thử lần lượt các model ứng viên (model bị gỡ/không cấp cho key, hết hạn mức, quá tải → model kế tiếp).
function hv_gemini_generate(string $key, array $body, int $maxTries = 4, int $budgetSec = 50): array {
    $cands = hv_gemini_candidates($key); $tried = []; $last = null; $t0 = microtime(true); $refreshed = false;
    for ($i = 0; $i < count($cands) && count($tried) < $maxTries && microtime(true) - $t0 < $budgetSec; $i++) {
        $m = $cands[$i]; $tried[] = $m;
        [$code, $j, $err, $raw] = hv_gemini_call($key, $m, $body);
        $last = [$code, $j, $err, $raw];
        if ($code === 200) { hv_set_setting('gemini_last_ok', $m); return ['ok'=>true, 'model'=>$m, 'json'=>$j, 'tried'=>$tried]; }
        $msg = (string)($j['error']['message'] ?? '');
        $reason = ''; foreach ($j['error']['details'] ?? [] as $d) if (!empty($d['reason'])) $reason = (string)$d['reason'];
        if ($reason === 'API_KEY_INVALID' || stripos($msg, 'API key not valid') !== false || $code === 401) break; // key hỏng: đổi model vô ích
        if ($m === hv_setting('gemini_last_ok')) hv_set_setting('gemini_last_ok', '');
        $modelGone = $code === 404 || ($code === 400 && preg_match('/model|not found|not supported|no longer available|new users/i', $msg));
        if ($modelGone && !$refreshed) { $refreshed = true; // danh sách cũ: hỏi lại Google rồi chèn ứng viên mới
            foreach (hv_gemini_candidates($key, true) as $n) if (!in_array($n, $cands, true)) $cands[] = $n; }
        if ($err !== '' && stripos($err, 'timed out') !== false) break; // quá giờ: dừng kẻo trang treo
        if (!($modelGone || $code === 429 || $code === 403 || $code >= 500 || $err !== '')) break; // lỗi khác: dừng
    }
    return ['ok'=>false, 'last'=>$last, 'tried'=>$tried];
}

function hv_suggest(string $text, array $projects = []): array {
    @set_time_limit(150);
    $key = trim(hv_setting('gemini_api_key'));
    if ($key === '') throw new RuntimeException('Chưa có Gemini API key. Vào mục Cài đặt (⚙) để dán key.');
    $rules = str_replace('{TODAY}', hv_today(), HV_TRIAGE_RULES);
    if ($projects) $rules .= "\nDự án hiện có (projectId: tên): " . implode('; ', array_map(fn($p)=>$p['id'].': '.$p['name'], $projects)) . ". Chọn projectId nếu việc thuộc dự án nào, không thì rỗng.";
    $schema = ['type'=>'OBJECT','properties'=>[
        'title'=>['type'=>'STRING'],'group'=>['type'=>'STRING','enum'=>HV_GROUPS],'who'=>['type'=>'STRING','enum'=>HV_WHO],
        'priority'=>['type'=>'INTEGER'],'due'=>['type'=>'STRING'],'desc'=>['type'=>'STRING'],'projectId'=>['type'=>'STRING'],'reason'=>['type'=>'STRING']],
        'required'=>['title','group','who','priority','due','desc','reason']];
    $body = ['systemInstruction'=>['parts'=>[['text'=>$rules]]],
        'contents'=>[['role'=>'user','parts'=>[['text'=>"Việc thô: ".$text]]]],
        'generationConfig'=>['responseMimeType'=>'application/json','responseSchema'=>$schema,'temperature'=>0.2]];
    $g = hv_gemini_generate($key, $body);
    if (!$g['ok']) { [$code, $j, $err, $raw] = $g['last']; throw new RuntimeException(hv_gemini_explain($code, $j, $err, $raw) . ' (đã thử: ' . implode(', ', $g['tried']) . ')'); }
    $j = $g['json']; $used = $g['model'];

    $txt = '';
    foreach ($j['candidates'][0]['content']['parts'] ?? [] as $part) if (empty($part['thought']) && isset($part['text'])) $txt .= $part['text'];
    $txt = trim(preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($txt)));
    $out = json_decode($txt, true);
    if (!is_array($out)) {
        $fr = $j['candidates'][0]['finishReason'] ?? ($j['promptFeedback']['blockReason'] ?? '');
        throw new RuntimeException('Gemini không trả về JSON hợp lệ' . ($fr ? " (lý do dừng: $fr)" : '') . '. Thử bấm lại hoặc viết mô tả rõ hơn.');
    }
    $out['priority'] = max(1, min(3, (int)($out['priority'] ?? 2)));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($out['due'] ?? ''))) $out['due'] = '';
    if (!in_array($out['group'] ?? '', HV_GROUPS, true)) $out['group'] = 'quan-ly';
    if (!in_array($out['who'] ?? '', HV_WHO, true)) $out['who'] = 'claude';
    $usage = $j['usageMetadata']['totalTokenCount'] ?? 0;
    if ($usage) hv_log_usage(hv_today(), ['gemini_tokens'=>(int)$usage], 'suggest', true);
    $out['model'] = $used; $out['tokens'] = $usage;
    return $out;
}

// Kiểm tra key: hỏi lại danh sách model của key và gọi thử (tự chọn model).
function hv_gemini_test(): array {
    @set_time_limit(120);
    $key = trim(hv_setting('gemini_api_key'));
    if ($key === '') return ['ok'=>false, 'message'=>'Chưa có key. Dán key rồi bấm Lưu cài đặt trước.'];
    $ls = hv_gemini_models($key);
    if ($ls['code'] !== 200) return ['ok'=>false, 'step'=>'list', 'message'=>hv_gemini_explain($ls['code'], ['error'=>['message'=>$ls['error'],'status'=>$ls['status']]], $ls['err'], '')];
    hv_set_setting('gemini_auto', json_encode(['day'=>hv_today(), 'models'=>$ls['models'] ?: HV_GEMINI_FALLBACK, 'from'=>'google']));
    $g = hv_gemini_generate($key, ['contents'=>[['role'=>'user','parts'=>[['text'=>'Trả lời đúng một chữ: OK']]]]], 5, 80);
    $models = array_slice($ls['models'], 0, 8);
    if (!$g['ok']) { [$code, $j, $err, $raw] = $g['last']; return ['ok'=>false, 'step'=>'generate', 'models'=>$models, 'tried'=>$g['tried'], 'message'=>hv_gemini_explain($code, $j, $err, $raw)]; }
    return ['ok'=>true, 'model'=>$g['model'], 'models'=>$models, 'tried'=>$g['tried'], 'message'=>'Key hoạt động. Hộp việc tự chọn model ' . $g['model'] . '.'];
}
// Thông tin model tự chọn để hiện trong Cài đặt.
function hv_gemini_auto_info(): array {
    $c = json_decode(hv_setting('gemini_auto', '{}'), true) ?: [];
    return ['last_ok'=>hv_setting('gemini_last_ok'), 'candidates'=>array_slice($c['models'] ?? [], 0, 5), 'day'=>$c['day'] ?? ''];
}

// ---------- Duyệt kết quả agent ----------
// approve → xong; return → dang_lam, chèn ghi chú của Tuấn lên đầu mô tả để agent đọc ở phiên sau.
function hv_review_task(string $id, string $decision, string $note, string $actor): array {
    $t = hv_get_task($id);
    if (!$t) throw new RuntimeException('Không có việc này');
    $note = trim($note);
    if ($decision === 'approve') {
        $d = ['status'=>'xong'];
        if ($note !== '') $d['desc'] = '[Tuấn duyệt ' . date('d/m') . ': ' . $note . "]\n" . $t['desc'];
        $r = hv_update_task($id, $d, $actor); hv_log($actor, 'approve', $id, $note); return $r;
    }
    if ($decision === 'return') {
        if ($note === '') throw new RuntimeException('Ghi chú cần sửa gì trước khi trả lại');
        $r = hv_update_task($id, ['status'=>'dang_lam', 'desc'=>'[Tuấn trả lại ' . date('d/m') . ': ' . $note . "]\n" . $t['desc']], $actor);
        hv_log($actor, 'return', $id, $note); return $r;
    }
    throw new RuntimeException('decision phải là approve hoặc return');
}
