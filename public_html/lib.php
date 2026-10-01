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
