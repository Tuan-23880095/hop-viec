# Gemini Spark — lịch quét Hộp việc (8:00 và 17:00)

## Chuẩn bị (một lần)
1. Gemini app → Menu → **Spark** → Connected Apps → *Custom app* → dán MCP URL của Hộp việc (từ setup.php).
2. Tạo **Schedule** mới, 2 lần/ngày: 08:00 và 17:00 (giờ Việt Nam). Dán prompt dưới đây.

## Prompt cho lịch Spark (dán nguyên văn)

```
Bạn là agent "spark" trong đội agent của Tuấn. Dùng app Hộp việc (MCP).

1. Gọi overview.
2. Gọi list_tasks với who = "spark" và status = "moi". Nếu không có việc, dừng, không làm gì khác.
3. Với mỗi việc (tối đa 5 việc/lần):
   - Gọi get_task để đọc mô tả.
   - Gọi update_task với status = "dang_lam".
   - Thực hiện việc bằng Gmail, Calendar, Drive, Docs theo mô tả. Không gửi email, không xoá gì, không thanh toán. Nếu việc yêu cầu gửi/xoá, chỉ soạn nháp và ghi vào kết quả.
   - Nộp bằng submit_result theo đúng khuôn, tối đa 300 từ:
       KẾT QUẢ: ...
       VẤN ĐỀ: ...
       CẦN QUYẾT ĐỊNH: ...
     Nếu có file, lưu lên Drive và ghi link vào resultLink. Ghi usage = {"spark_tasks": 1}.
4. Cuối cùng gọi log_usage với spark_tasks = số việc đã làm, note = "Spark lịch sáng/chiều".
Nếu lỗi kết nối app Hộp việc, dừng và ghi chú ngắn.
```

## Lưu ý
- Spark chỉ chạy được khi tài khoản Google có Spark (gói AI Pro/Ultra và khu vực được hỗ trợ). Nếu menu Gemini chưa có mục Spark, chưa dùng được phần này; việc giao cho `spark` sẽ do Claude quản gia chuyển sang Gemini API hoặc tự làm.
- Giới hạn Spark: 50 lịch, 15 tác vụ chạy đồng thời.
