<?php
// Hộp việc — cài đặt lần đầu: tạo config.php với mật khẩu giao diện + API key + MCP token.
// Chạy NGAY sau khi deploy. Sau khi config.php tồn tại, trang này tự khoá.
declare(strict_types=1);
require __DIR__ . '/lib.php';
header('Content-Type: text/html; charset=utf-8');
if (hv_config()) { http_response_code(403); exit('<p style="font-family:system-ui">Đã cài đặt rồi. Muốn đặt lại: xoá file <code>' . htmlspecialchars(HV_CONFIG) . '</code> trên host rồi mở lại trang này.</p>'); }

$pw = $_POST['password'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strlen($pw) >= 8) {
    $api = bin2hex(random_bytes(24)); $mcp = bin2hex(random_bytes(24));
    $php = "<?php\n// Sinh bởi setup.php " . date('c') . " — KHÔNG đưa file này lên GitHub.\nreturn [\n  'ui_password' => " . var_export($pw, true) . ",\n  'api_key' => '$api',\n  'mcp_token' => '$mcp',\n];\n";
    file_put_contents(HV_CONFIG, $php, LOCK_EX);
    hv_db();
    $base = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/\\');
    echo "<!doctype html><meta charset=utf-8><body style='font-family:system-ui;max-width:720px;margin:40px auto;line-height:1.5'>
    <h2>Đã cài đặt Hộp việc</h2><p>Ghi lại các giá trị sau (chỉ hiện một lần; sau này xem trong <code>" . htmlspecialchars(HV_CONFIG) . "</code> qua File Manager):</p>
    <table border=1 cellpadding=8 style='border-collapse:collapse;word-break:break-all'>
    <tr><td>Giao diện</td><td><a href='$base/'>$base/</a> — mật khẩu anh vừa đặt</td></tr>
    <tr><td>API key (header X-Api-Key)</td><td><code>$api</code></td></tr>
    <tr><td>MCP URL (Claude.ai / Spark / Antigravity / Claude Code)</td><td><code>$base/mcp/$mcp</code></td></tr>
    </table><p>Kiểm tra nhanh MCP: <code>curl -X POST $base/mcp/$mcp -H 'Content-Type: application/json' -d '{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/list\"}'</code></p></body>";
    exit;
}
?><!doctype html><meta charset="utf-8"><title>Cài đặt Hộp việc</title>
<body style="font-family:system-ui;max-width:480px;margin:40px auto;line-height:1.5">
<h2>Cài đặt Hộp việc</h2>
<form method="post">
<label>Mật khẩu đăng nhập giao diện (≥ 8 ký tự)<br><input name="password" type="password" minlength="8" required style="width:100%;padding:8px;margin:6px 0 12px"></label>
<button style="padding:8px 14px">Tạo cấu hình</button>
</form>
<p style="color:#666;font-size:13px">API key và MCP token sẽ được sinh ngẫu nhiên và hiện ở trang kế tiếp.</p>
</body>
