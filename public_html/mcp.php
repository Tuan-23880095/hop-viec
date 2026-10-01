<?php
// Hộp việc — MCP server (Streamable HTTP, JSON-RPC 2.0)
// URL: https://<host>/mcp/<MCP_TOKEN>   (hoặc /mcp.php?token=<MCP_TOKEN>)
// Dùng cho: Claude.ai (Settings → Connectors → Add custom connector), Gemini Spark (Connected Apps → custom MCP URL),
//           Antigravity (mcp_config.json: "serverUrl"), Claude Code (claude mcp add --transport http ...).
declare(strict_types=1);
require __DIR__ . '/lib.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Mcp-Session-Id, MCP-Protocol-Version, Accept');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$cfg = hv_config();
if (!$cfg) { http_response_code(503); exit('setup required'); }
$token = (string)($_GET['token'] ?? '');
if ($token === '' && preg_match('#^Bearer\s+(.+)$#i', $_SERVER['HTTP_AUTHORIZATION'] ?? '', $m)) $token = trim($m[1]);
if (!hash_equals($cfg['mcp_token'], $token)) { http_response_code(401); header('Content-Type: application/json'); echo '{"error":"unauthorized"}'; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'GET') { http_response_code(405); header('Content-Type: application/json'); echo '{"error":"use POST (Streamable HTTP, JSON responses only)"}'; exit; }
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') { http_response_code(204); exit; }

$raw = file_get_contents('php://input');
$req = json_decode($raw, true);
if (!is_array($req)) rpc_error(null, -32700, 'Parse error');
$batch = array_is_list($req);
$msgs = $batch ? $req : [$req];
$out = [];
foreach ($msgs as $m) { $r = handle($m); if ($r !== null) $out[] = $r; }
if (!$out) { http_response_code(202); exit; }
header('Content-Type: application/json; charset=utf-8');
echo json_encode($batch ? $out : $out[0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;

function rpc_error($id, int $code, string $msg): never {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>$code,'message'=>$msg]], JSON_UNESCAPED_UNICODE); exit;
}
function ok($id, $result): array { return ['jsonrpc'=>'2.0','id'=>$id,'result'=>$result]; }
function text($id, $data, bool $isError = false): array {
    return ok($id, ['content'=>[['type'=>'text','text'=>is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)]], 'isError'=>$isError]);
}

function tools(): array {
    $who = ['type'=>'string','enum'=>HV_WHO,'description'=>'claude | gemini | notebooklm | antigravity | spark | co-van | tuan'];
    $status = ['type'=>'string','enum'=>HV_STATUS,'description'=>'moi | dang_lam | cho_duyet | chan | xong'];
    $usage = ['type'=>'object','description'=>'Tiêu hao CỘNG DỒN vào việc: claude_tokens, opus_tokens, gemini_tokens, agy_credits, nlm_queries, spark_tasks (số nguyên)',
        'properties'=>array_fill_keys(HV_USAGE_KEYS, ['type'=>'integer'])];
    return [
        ['name'=>'overview','description'=>'Tổng quan Hộp việc: số việc mở, quá hạn, chờ duyệt, tiêu hao tháng so với hạn mức. Gọi đầu phiên.','inputSchema'=>['type'=>'object','properties'=>new stdClass()]],
        ['name'=>'list_tasks','description'=>'Liệt kê việc (mặc định bỏ việc đã xong). Lọc theo status/who/projectId/group.','inputSchema'=>['type'=>'object','properties'=>[
            'status'=>$status,'who'=>$who,'projectId'=>['type'=>'string'],'group'=>['type'=>'string','enum'=>HV_GROUPS],'includeDone'=>['type'=>'boolean']]]],
        ['name'=>'get_task','description'=>'Xem chi tiết một việc (mô tả, kết quả, tiêu hao).','inputSchema'=>['type'=>'object','required'=>['id'],'properties'=>['id'=>['type'=>'string']]]],
        ['name'=>'add_task','description'=>'Thêm việc mới vào Hộp việc.','inputSchema'=>['type'=>'object','required'=>['title'],'properties'=>[
            'title'=>['type'=>'string'],'desc'=>['type'=>'string','description'=>'Yêu cầu đầu ra, nguồn, giới hạn'],'who'=>$who,'projectId'=>['type'=>'string'],
            'group'=>['type'=>'string','enum'=>HV_GROUPS],'priority'=>['type'=>'integer','minimum'=>1,'maximum'=>3],'due'=>['type'=>'string','description'=>'YYYY-MM-DD'],'status'=>$status,'actor'=>['type'=>'string']]]],
        ['name'=>'update_task','description'=>'Cập nhật việc: đổi trạng thái, giao lại, sửa hạn/mô tả. usage được cộng dồn.','inputSchema'=>['type'=>'object','required'=>['id'],'properties'=>[
            'id'=>['type'=>'string'],'status'=>$status,'who'=>$who,'priority'=>['type'=>'integer'],'due'=>['type'=>'string'],'desc'=>['type'=>'string'],'title'=>['type'=>'string'],'projectId'=>['type'=>'string'],'usage'=>$usage,'actor'=>['type'=>'string']]]],
        ['name'=>'submit_result','description'=>'Agent nộp kết quả cho một việc theo khuôn KẾT QUẢ / VẤN ĐỀ / CẦN QUYẾT ĐỊNH (≤300 từ), kèm link sản phẩm và tiêu hao; trạng thái chuyển sang cho_duyet (hoặc xong nếu markDone).','inputSchema'=>['type'=>'object','required'=>['id','result'],'properties'=>[
            'id'=>['type'=>'string'],'result'=>['type'=>'string'],'resultLink'=>['type'=>'string'],'usage'=>$usage,'markDone'=>['type'=>'boolean'],'actor'=>['type'=>'string']]]],
        ['name'=>'list_projects','description'=>'Liệt kê dự án kèm số việc đã xong / tổng.','inputSchema'=>['type'=>'object','properties'=>new stdClass()]],
        ['name'=>'add_project','description'=>'Thêm dự án.','inputSchema'=>['type'=>'object','required'=>['name'],'properties'=>['name'=>['type'=>'string'],'group'=>['type'=>'string','enum'=>HV_GROUPS],'due'=>['type'=>'string'],'goal'=>['type'=>'string']]]],
        ['name'=>'log_usage','description'=>'Ghi tiêu hao trong ngày (cộng dồn vào ngày đó). Dùng cuối mỗi phiên agent.','inputSchema'=>['type'=>'object','properties'=>array_merge(
            ['day'=>['type'=>'string','description'=>'YYYY-MM-DD, mặc định hôm nay'],'note'=>['type'=>'string'],'actor'=>['type'=>'string']], array_fill_keys(HV_USAGE_KEYS, ['type'=>'integer']))]],
        ['name'=>'usage_summary','description'=>'Tiêu hao tháng này so với hạn mức, và hôm nay.','inputSchema'=>['type'=>'object','properties'=>new stdClass()]],
        ['name'=>'set_budgets','description'=>'Đặt hạn mức tháng (chỉ Tuấn/quản gia).','inputSchema'=>['type'=>'object','properties'=>array_fill_keys(HV_USAGE_KEYS, ['type'=>'integer'])]],
    ];
}

function handle(array $m): ?array {
    $id = $m['id'] ?? null; $method = $m['method'] ?? ''; $p = $m['params'] ?? [];
    if (str_starts_with($method, 'notifications/')) return null;
    switch ($method) {
        case 'initialize':
            return ok($id, ['protocolVersion'=>$p['protocolVersion'] ?? '2025-06-18','capabilities'=>['tools'=>new stdClass()],
                'serverInfo'=>['name'=>'hop-viec','version'=>'1.0.0'],
                'instructions'=>"Hộp việc của Tuấn — kho việc dùng chung cho đội agent. Quy tắc: (1) gọi overview đầu phiên; (2) chỉ nhận việc có who = tên của bạn; (3) nộp kết quả bằng submit_result theo khuôn KẾT QUẢ / VẤN ĐỀ / CẦN QUYẾT ĐỊNH, tối đa 300 từ, file sản phẩm để trên Drive và ghi link; (4) ghi tiêu hao bằng log_usage cuối phiên; (5) không tự đánh dấu xong — Tuấn duyệt."]);
        case 'ping': return ok($id, new stdClass());
        case 'tools/list': return ok($id, ['tools'=>tools()]);
        case 'tools/call':
            $name = $p['name'] ?? ''; $a = $p['arguments'] ?? []; $actor = (string)($a['actor'] ?? 'mcp');
            try {
                switch ($name) {
                    case 'overview': return text($id, hv_overview());
                    case 'list_tasks': return text($id, hv_list_tasks($a));
                    case 'get_task': $t = hv_get_task((string)$a['id']); return $t ? text($id, $t) : text($id, 'Không có việc '.$a['id'], true);
                    case 'add_task': return text($id, hv_add_task($a, $actor));
                    case 'update_task': $t = hv_update_task((string)$a['id'], $a, $actor); return $t ? text($id, $t) : text($id, 'Không có việc '.$a['id'], true);
                    case 'submit_result':
                        $d = ['result'=>(string)$a['result'], 'status'=>!empty($a['markDone']) ? 'xong' : 'cho_duyet'];
                        if (isset($a['resultLink'])) $d['resultLink'] = (string)$a['resultLink'];
                        if (isset($a['usage'])) $d['usage'] = $a['usage'];
                        $t = hv_update_task((string)$a['id'], $d, $actor); return $t ? text($id, $t) : text($id, 'Không có việc '.$a['id'], true);
                    case 'list_projects': return text($id, hv_list_projects());
                    case 'add_project': return text($id, hv_add_project($a, $actor));
                    case 'log_usage': return text($id, hv_log_usage((string)($a['day'] ?? hv_today()), $a, $actor, true));
                    case 'usage_summary': return text($id, hv_usage_summary());
                    case 'set_budgets': return text($id, hv_set_budgets($a));
                    default: return ['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32602,'message'=>"Unknown tool: $name"]];
                }
            } catch (Throwable $e) { return text($id, 'Lỗi: '.$e->getMessage(), true); }
        default:
            if ($id === null) return null;
            return ['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32601,'message'=>"Method not found: $method"]];
    }
}
