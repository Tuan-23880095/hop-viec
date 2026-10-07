<?php
// Hộp việc — REST API (JSON)
// Xác thực: (a) phiên đăng nhập từ giao diện (POST action=login với mật khẩu), hoặc (b) header X-Api-Key.
declare(strict_types=1);
require __DIR__ . '/lib.php';

$cfg = hv_config();
if (!$cfg) hv_json(['error' => 'Chưa cài đặt. Mở /setup.php trước.'], 503);

session_name('hv_sess');
session_set_cookie_params(['lifetime' => 60*60*24*30, 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();

$method = $_SERVER['REQUEST_METHOD'];
$body = [];
if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH' || $method === 'DELETE') {
    $raw = file_get_contents('php://input');
    $body = $raw ? (json_decode($raw, true) ?: []) : $_POST;
}
$action = $_GET['action'] ?? $body['action'] ?? '';

// --- đăng nhập / đăng xuất ---
if ($action === 'login') {
    if (hash_equals($cfg['ui_password'], (string)($body['password'] ?? ''))) { $_SESSION['ok'] = true; session_regenerate_id(true); hv_json(['ok'=>true]); }
    usleep(300000); hv_json(['error'=>'Sai mật khẩu'], 401);
}
if ($action === 'logout') { session_destroy(); hv_json(['ok'=>true]); }

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$viaKey = $apiKey !== '' && hash_equals($cfg['api_key'], $apiKey);
$viaSession = !empty($_SESSION['ok']);
if ($action === 'whoami') hv_json(['loggedIn' => $viaSession || $viaKey, 'actor' => $viaKey ? 'api' : ($viaSession ? 'tuan' : null)]);
if (!$viaKey && !$viaSession) hv_json(['error' => 'Chưa đăng nhập'], 401);
$actor = $viaKey ? (string)($_SERVER['HTTP_X_ACTOR'] ?? $body['actor'] ?? 'api') : 'tuan';
if ($viaSession && !$viaKey && $method !== 'GET') { // chống CSRF đơn giản
    $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    if ($origin && stripos($origin, $_SERVER['HTTP_HOST']) === false) hv_json(['error'=>'Origin không hợp lệ'], 403);
}

try {
    switch ($action) {
        case 'overview':   hv_json(hv_overview());
        case 'tasks':
            if ($method === 'GET') hv_json(hv_list_tasks(['status'=>$_GET['status']??'', 'who'=>$_GET['who']??'', 'projectId'=>$_GET['projectId']??'', 'group'=>$_GET['group']??'', 'includeDone'=>!empty($_GET['includeDone'])]));
            if ($method === 'POST') hv_json(hv_add_task($body, $actor), 201);
            break;
        case 'task':
            $id = (string)($_GET['id'] ?? $body['id'] ?? '');
            if ($method === 'GET') { $t = hv_get_task($id); $t ? hv_json($t) : hv_json(['error'=>'Không có việc này'], 404); }
            if ($method === 'POST' || $method === 'PATCH' || $method === 'PUT') { $t = hv_update_task($id, $body, $actor); $t ? hv_json($t) : hv_json(['error'=>'Không có việc này'], 404); }
            if ($method === 'DELETE') hv_json(['ok' => hv_delete_task($id, $actor)]);
            break;
        case 'projects':
            if ($method === 'GET') hv_json(hv_list_projects());
            if ($method === 'POST') hv_json(hv_add_project($body, $actor), 201);
            break;
        case 'project':
            $id = (string)($_GET['id'] ?? $body['id'] ?? '');
            if ($method === 'POST' || $method === 'PATCH') hv_json(['ok' => hv_update_project($id, $body, $actor)]);
            if ($method === 'DELETE') hv_json(['ok' => hv_delete_project($id, $actor)]);
            break;
        case 'usage':
            if ($method === 'GET') hv_json(['days' => hv_usage_days((int)($_GET['days'] ?? 31)), 'summary' => hv_usage_summary()]);
            if ($method === 'POST') hv_json(hv_log_usage((string)($body['day'] ?? hv_today()), $body, $actor, !isset($body['mode']) || $body['mode'] !== 'set'));
            break;
        case 'budgets':
            if ($method === 'GET') hv_json(hv_budgets());
            if ($method === 'POST') hv_json(hv_set_budgets($body));
            break;
        case 'suggest':
            if ($method !== 'POST') break;
            $text = trim((string)($body['text'] ?? '')); if ($text === '') hv_json(['error'=>'Nhập mô tả việc trước'], 400);
            hv_json(hv_suggest($text, hv_list_projects()));
        case 'review':
            if ($method !== 'POST') break;
            hv_json(hv_review_task((string)($body['id'] ?? ''), (string)($body['decision'] ?? ''), (string)($body['note'] ?? ''), $actor));
        case 'gemini_test':
            if (!$viaSession) hv_json(['error'=>'Chỉ Tuấn (đăng nhập giao diện) mới kiểm tra key'], 403);
            hv_json(hv_gemini_test());
        case 'settings':
            if (!$viaSession) hv_json(['error'=>'Chỉ Tuấn (đăng nhập giao diện) mới đổi cài đặt'], 403);
            if ($method === 'GET') { $k = hv_setting('gemini_api_key'); hv_json(['gemini_api_key_masked'=> $k === '' ? '' : substr($k,0,6).'…'.substr($k,-4), 'gemini_model'=>hv_setting('gemini_model','gemini-flash-latest')]); }
            if ($method === 'POST') { if (isset($body['gemini_api_key']) && $body['gemini_api_key'] !== '') hv_set_setting('gemini_api_key', trim((string)$body['gemini_api_key'])); if (!empty($body['gemini_model'])) hv_set_setting('gemini_model', trim((string)$body['gemini_model'])); hv_json(['ok'=>true]); }
            break;
        case 'log':
            $st = hv_db()->prepare('SELECT * FROM log ORDER BY id DESC LIMIT ?'); $st->bindValue(1, min(500,(int)($_GET['limit'] ?? 100)), PDO::PARAM_INT); $st->execute();
            hv_json($st->fetchAll());
        default: hv_json(['error' => 'action không hợp lệ', 'actions' => ['overview','tasks','task','projects','project','usage','budgets','suggest','review','gemini_test','settings','log','login','logout','whoami']], 400);
    }
    hv_json(['error' => 'Phương thức không hợp lệ'], 405);
} catch (Throwable $e) {
    hv_json(['error' => $e->getMessage()], 500);
}
