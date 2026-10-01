# Hộp việc đội agent

Kho việc dùng chung cho Tuấn và đội AI (Claude, Gemini, NotebookLM, Antigravity, Gemini Spark).
Một nơi duy nhất để: nhập dự án/việc → giao cho từng AI → nhận kết quả → theo dõi token/credit tiêu hao.

```
Tuấn (trình duyệt) ──┐
Claude.ai / Cowork ──┤  MCP (HTTPS)        ┌─ public_html/index.php  giao diện
Gemini Spark ────────┼────────────────────►│  api.php                 REST JSON
Antigravity ─────────┤                     │  mcp.php                 MCP server (Streamable HTTP)
Claude Code ─────────┘                     └─ data/hopviec.sqlite     dữ liệu
```

## Thư mục

| Đường dẫn | Vai trò |
|---|---|
| `public_html/` | Toàn bộ web app (PHP 8 + SQLite, không cần MySQL, không cần Composer) |
| `public_html/setup.php` | Chạy **một lần** sau deploy: đặt mật khẩu, sinh API key + MCP token vào `config.php` |
| `setup/` | Script cài MCP trên máy Windows của Tuấn (Gemini API, Antigravity → Claude Desktop) |
| `web/hop-viec.html` | Bản thử dạng artifact Claude (không dùng khi đã có host) |
| `docs/` | Prompt cho Spark, quy tắc đội agent |

## Deploy lên Hostinger (shared hosting, subdomain `hopviec.diemdanhsv.com`)

1. **Tạo subdomain**: hPanel → Websites → diemdanhsv.com → *Subdomains* → tạo `hopviec`. Hostinger tạo thư mục `public_html/hopviec` (hoặc `domains/hopviec.diemdanhsv.com/public_html`).
2. **Bật SSL** cho subdomain (hPanel → Security → SSL, thường tự động).
3. **Deploy từ GitHub**: hPanel → Advanced → *Git* → Create new repository:
   - Repository: `https://github.com/<user>/hop-viec.git`, branch `main`
   - Install path: thư mục của subdomain (ví dụ `public_html/hopviec`)
   - Sau khi deploy, vì code nằm trong thư mục con `public_html/` của repo, đặt **Document root** của subdomain trỏ vào `.../hopviec/public_html` (hPanel → Subdomains → sửa thư mục), hoặc dùng *Auto deployment* + webhook để mỗi lần push là cập nhật.
4. Mở `https://hopviec.diemdanhsv.com/setup.php` → đặt mật khẩu → **ghi lại API key và MCP URL** (chỉ hiện một lần; sau này xem trong `config.php` bằng File Manager).
5. Kiểm tra: `https://hopviec.diemdanhsv.com/` đăng nhập được; `curl -X POST <MCP URL> -H 'Content-Type: application/json' -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'` trả về danh sách tool.

Yêu cầu PHP ≥ 8.1 với extension `pdo_sqlite` (Hostinger bật sẵn). Đặt phiên bản PHP trong hPanel → PHP Configuration nếu đang < 8.1.

## Nối các AI vào cùng một kho

| AI | Cách nối | Ghi chú |
|---|---|---|
| **Claude.ai / Cowork** | Settings → Connectors → *Add custom connector* → dán MCP URL (không cần OAuth) | Sau đó mọi phiên Claude đều có tool `overview`, `list_tasks`, `submit_result`… |
| **Claude Code** | `claude mcp add --transport http hop-viec <MCP URL>` | |
| **Gemini Spark** | Gemini app → Connected Apps → *Custom app* → dán MCP URL | Spark chỉ nhận việc có `who = spark` |
| **Antigravity** | Thêm vào cả 3 file `mcp_config.json`: `"hop-viec": {"serverUrl": "<MCP URL>"}` | Antigravity dùng khóa `serverUrl`, không phải `url` |
| **Gemini API / script** | REST: header `X-Api-Key: <API key>`, `X-Actor: gemini` | Xem `api.php` |

MCP token nằm trong URL. Ai có URL là ghi được vào Hộp việc, nên không đưa URL lên GitHub hay chat công khai. Muốn đổi token: xoá `config.php` trên host, chạy lại `setup.php`.

## Hợp đồng đầu ra cho mọi agent

Mỗi agent khi nộp việc (`submit_result`) phải theo khuôn, tối đa 300 từ:

```
KẾT QUẢ: <đã làm gì, số liệu chính>
VẤN ĐỀ: <vướng mắc, giả định đã dùng, hoặc "không">
CẦN QUYẾT ĐỊNH: <câu hỏi cho Tuấn, hoặc "không">
```

File sản phẩm để trên Google Drive, ghi link vào `resultLink`. Tiêu hao ghi vào `usage` của việc và `log_usage` cuối phiên. Agent không tự chuyển việc sang `xong`; Tuấn duyệt trên giao diện.

## API nhanh

```
GET  api.php?action=overview
GET  api.php?action=tasks&who=spark&status=moi
POST api.php?action=tasks         {"title":"…","who":"gemini","projectId":"…","due":"2026-10-05"}
POST api.php?action=task&id=<id>  {"status":"cho_duyet","result":"…","usage":{"gemini_tokens":12000}}
POST api.php?action=usage         {"claude_tokens":30000,"note":"phiên sáng"}      (cộng dồn vào hôm nay)
GET  api.php?action=log
```

## Chạy thử trên máy

```
cd public_html && php -S 127.0.0.1:8080
```
Mở http://127.0.0.1:8080/setup.php rồi http://127.0.0.1:8080/.
