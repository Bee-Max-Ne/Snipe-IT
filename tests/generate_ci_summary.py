#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Trích xuất tự động chỉ số kiểm thử chuẩn công nghiệp (Gold Standard) cho Phân vùng 4 (Snipe-IT).
Hỗ trợ:
- Machine-Readable JUnit XML parser (đọc đệ quy mọi testsuite/testcase, kiểm tra fail/error thật)
- Machine-Readable Clover XML coverage parser (kiểm tra ngưỡng >= 80% thật, không gán cứng)
- Đọc bằng chứng môi trường (environment_metadata.json & mysql_container_proof.json)
- Tự động sinh mã băm toàn vẹn SHA-256 (Anti-tamper Checksums)
- Tự động đóng gói ZIP Artifacts nghiệm thu đệ quy
Tác giả: Nguyễn Thành Minh (MSSV: 2312691) - Phân vùng 4 (Snipe-IT)
"""

import sys
import os
import re
import json
import hashlib
import zipfile
import xml.etree.ElementTree as ET
from datetime import datetime, timezone

if hasattr(sys.stdout, 'reconfigure'):
    try:
        sys.stdout.reconfigure(encoding='utf-8')
    except Exception:
        pass


def calculate_sha256(filepath):
    """Tính mã băm SHA-256 cho tệp tin."""
    h = hashlib.sha256()
    with open(filepath, "rb") as f:
        while chunk := f.read(8192):
            h.update(chunk)
    return h.hexdigest()


def load_json_safe(path):
    if path and os.path.exists(path):
        try:
            with open(path, "r", encoding="utf-8") as f:
                return json.load(f)
        except Exception as e:
            print(f"Warning: Failed to read JSON {path}: {e}", file=sys.stderr)
    return None


def generate_summary(log_path, output_summary_path, junit_path=None):
    reports_dir = os.path.dirname(log_path) if log_path else "storage/logs/test-reports"
    if not junit_path:
        junit_candidate = os.path.join(reports_dir, "junit_part4_report.xml")
        if os.path.exists(junit_candidate):
            junit_path = junit_candidate

    total_tests = 0
    passed_tests = 0
    failed_tests = 0
    error_tests = 0
    skipped_tests = 0
    total_assertions = 0
    duration_sec = 0.0
    test_cases_data = []

    # 1. Đọc từ chuẩn máy đọc JUnit XML (tính toán thật từ XML, không fallback gán cứng nếu XML tồn tại)
    if junit_path and os.path.exists(junit_path):
        try:
            tree = ET.parse(junit_path)
            root = tree.getroot()
            suite = root.find("testsuite")
            if suite is not None:
                total_tests = int(suite.attrib.get("tests", 0))
                failed_tests = int(suite.attrib.get("failures", 0))
                error_tests = int(suite.attrib.get("errors", 0))
                skipped_tests = int(suite.attrib.get("skipped", 0))
                passed_tests = max(0, total_tests - failed_tests - error_tests - skipped_tests)
                total_assertions = int(suite.attrib.get("assertions", 0))
                duration_sec = float(suite.attrib.get("time", 0.0))

                for tc in suite.iter("testcase"):
                    raw_name = tc.attrib.get("name", "")
                    tc_name = raw_name.replace("test_", "").replace("_", " ")
                    tc_time = float(tc.attrib.get("time", 0.0))
                    tc_assert = int(tc.attrib.get("assertions", 0))
                    has_failure = tc.find("failure") is not None
                    has_error = tc.find("error") is not None
                    has_skip = tc.find("skipped") is not None
                    if has_failure:
                        status = "❌ FAIL"
                    elif has_error:
                        status = "💥 ERROR"
                    elif has_skip:
                        status = "⏭️ SKIP"
                    else:
                        status = "✅ PASS"
                    is_sec_case = raw_name.startswith("test_tc_sec_")
                    test_cases_data.append((tc_name, f"{tc_time:.2f}s", tc_assert, status, is_sec_case))
        except Exception as e:
            print(f"Warning: Failed to parse JUnit XML: {e}", file=sys.stderr)

    # 2. Đọc file text log để fallback nếu chưa có JUnit XML và lấy PCOV text summary
    log_content = ""
    if log_path and os.path.exists(log_path):
        with open(log_path, "r", encoding="utf-8", errors="ignore") as f:
            log_content = f.read()

    if total_tests == 0 and log_content:
        ok_match = re.search(r'OK\s+\((\d+)\s+tests?,\s+(\d+)\s+assertions?\)', log_content)
        artisan_match = re.search(r'Tests:\s+(\d+)\s+passed\s+\((\d+)\s+assertions\)', log_content)
        time_match = re.search(r'Time:\s+\d+:([\d\.]+)', log_content)
        duration_match = re.search(r'Duration:\s+([\d\.]+)s', log_content)
        if ok_match:
            total_tests = passed_tests = int(ok_match.group(1))
            total_assertions = int(ok_match.group(2))
        elif artisan_match:
            total_tests = passed_tests = int(artisan_match.group(1))
            total_assertions = int(artisan_match.group(2))
        if time_match:
            duration_sec = float(time_match.group(1))
        elif duration_match:
            duration_sec = float(duration_match.group(1))

    pass_rate = (passed_tests / total_tests * 100.0) if total_tests > 0 else 0.0

    # 3. Trích xuất Code Coverage thật từ Clover XML (hoặc text log), đánh giá ngưỡng >= 80% thật
    clover_line_cov = None
    line_coverage_display = "Chưa đo (Không có driver PCOV/Xdebug)"
    methods_cov_val = None
    classes_cov_val = None
    clover_path = os.path.join(reports_dir, "coverage_clover.xml")

    if os.path.exists(clover_path):
        try:
            clover_tree = ET.parse(clover_path)
            clover_root = clover_tree.getroot()
            project = clover_root.find("project")
            if project is not None:
                metrics = project.find("metrics")
                if metrics is not None:
                    stmts = int(metrics.attrib.get("statements", 0))
                    covered_stmts = int(metrics.attrib.get("coveredstatements", 0))
                    methods = int(metrics.attrib.get("methods", 0))
                    covered_methods = int(metrics.attrib.get("coveredmethods", 0))
                    classes = int(metrics.attrib.get("classes", 0))
                    if stmts > 0:
                        clover_line_cov = (covered_stmts / stmts) * 100.0
                        line_coverage_display = f"{clover_line_cov:.1f}% ({covered_stmts}/{stmts} statements)"
                    if methods > 0:
                        clover_method_cov = (covered_methods / methods) * 100.0
                        methods_cov_val = f"{clover_method_cov:.1f}% ({covered_methods}/{methods} methods)"
                    if classes > 0:
                        classes_cov_val = f"{classes} files/classes scoped"
        except Exception as e:
            print(f"Warning: Failed to parse Clover XML: {e}", file=sys.stderr)
    elif log_content:
        lines_cov_match = re.search(r'Lines:\s+([\d\.]+)%', log_content)
        classes_cov_match = re.search(r'Classes:\s+([\d\.]+)%', log_content)
        methods_cov_match = re.search(r'Methods:\s+([\d\.]+)%', log_content)
        if lines_cov_match:
            clover_line_cov = float(lines_cov_match.group(1))
            line_coverage_display = f"{clover_line_cov:.1f}%"
        if classes_cov_match:
            classes_cov_val = classes_cov_match.group(1) + "%"
        if methods_cov_match:
            methods_cov_val = methods_cov_match.group(1) + "%"

    # Đọc metadata môi trường & bằng chứng container MySQL K16
    env_meta = load_json_safe(os.path.join(reports_dir, "environment_metadata.json"))
    mysql_proof = load_json_safe(os.path.join(reports_dir, "mysql_container_proof.json"))

    # Ngưỡng đánh giá động & trung thực (tính chi phí thực tế Docker MySQL 8.0.36 K16)
    is_k16_mysql = mysql_proof is not None
    if total_tests > 30:
        duration_limit = 600.0 if is_k16_mysql else 90.0
    else:
        duration_limit = 300.0 if is_k16_mysql else 20.0
    eval_pass_rate = (
        f"✅ ĐẠT ({pass_rate:.1f}%)"
        if (total_tests > 0 and failed_tests == 0 and error_tests == 0 and pass_rate >= 95.0)
        else f"❌ KHÔNG ĐẠT ({passed_tests}/{total_tests} passed, {failed_tests} fail, {error_tests} error)"
    )
    eval_assertions = (
        f"✅ VƯỢT CHỈ TIÊU ({total_assertions} >= 50)"
        if total_assertions >= 50
        else f"❌ CHƯA ĐẠT ({total_assertions} < 50)"
    )
    eval_duration = (
        f"✅ ĐẠT HIỆU NĂNG ({duration_sec:.2f}s <= {duration_limit:.0f}s)"
        if (duration_sec > 0 and duration_sec <= duration_limit)
        else f"⚠️ VƯỢT NGƯỠNG ({duration_sec:.2f}s > {duration_limit:.0f}s)"
    )
    if clover_line_cov is not None:
        eval_coverage = (
            f"✅ ĐẠT NGƯỠNG ({clover_line_cov:.1f}% >= 80%)"
            if clover_line_cov >= 80.0
            else f"⚠️ DƯỚI NGƯỠNG PHỦ TOÀN FILE ({clover_line_cov:.1f}% < 80%)"
        )
    else:
        eval_coverage = "⚠️ KHÔNG CÓ DRIVER COVERAGE (Chạy trên CI PCOV để đo)"

    open_defects_count = failed_tests + error_tests
    eval_defects = "✅ ZERO FAILURE" if open_defects_count == 0 else f"❌ CÓ {open_defects_count} LỖI CẦN XỬ LÝ"

    now_utc = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S UTC')
    commit_info = f"`{env_meta.get('git_commit_short', 'N/A')}` (Branch: `{env_meta.get('git_branch', 'N/A')}`)" if env_meta else "`N/A`"
    db_info = (
        f"K16 Testcontainers MySQL `{mysql_proof.get('mysql_server_version', '8.0.36')}` (DB: `{mysql_proof.get('db_database')}`, Port: `{mysql_proof.get('db_port')}`)"
        if mysql_proof
        else (env_meta.get('execution_mode', 'Local PHPUnit') if env_meta else 'Local PHPUnit')
    )

    # 4. Xây dựng nội dung Markdown trước khi hash & đóng gói
    markdown = []
    markdown.append("# 🛡️ BÁO CÁO KIỂM THỬ CHUẨN CÔNG NGHIỆP: PHÂN VÙNG 4 (SNIPE-IT)")
    markdown.append(f"**Sinh viên thực hiện:** Nguyễn Thành Minh (MSSV: `2312691`) — Nhóm 05  ")
    markdown.append(f"**Phân vùng:** Module Người dùng (`Users`), Phân quyền RBAC, An ninh `UserPolicy`, Đa công ty FMCS & Auth/API Integration  ")
    markdown.append(f"**Phiên bản mã nguồn (Commit):** {commit_info}  ")
    markdown.append(f"**Môi trường CSDL thực thi:** {db_info}  ")
    markdown.append(f"**Thời điểm đo tự động:** `{now_utc}`  \n")
    markdown.append("---")
    markdown.append("\n## 1. 📊 Bảng Chỉ Số Thực Nghiệm Đo Đạc Tự Động (Auto-Calculated Metrics)")
    markdown.append("\n| Chỉ số đo lường | Ngưỡng cam kết (Threshold) | Giá trị thực tế đo từ Runner | Đánh giá tự động |")
    markdown.append("| :--- | :---: | :---: | :---: |")
    markdown.append(f"| **Tỷ lệ Pass Rate** | $\\ge 95\\%$ (0 fail) | **{pass_rate:.1f}%** ({passed_tests}/{total_tests} tests) | {eval_pass_rate} |")
    markdown.append(f"| **Tổng số Assertions** | $\\ge 50$ assertions | **{total_assertions} assertions** | {eval_assertions} |")
    markdown.append(f"| **Độ bao phủ dòng (PCOV / Clover)** | $\\ge 80\\%$ trên mã nguồn phạm vi | **{line_coverage_display}** | {eval_coverage} |")
    markdown.append(f"| **Thời gian thực thi (Performance)** | $\\le {duration_limit:.0f}.0$ giây | **{duration_sec:.2f} giây** | {eval_duration} |")
    markdown.append(f"| **Thất bại / Lỗi thực thi** | 0 lỗi | **{open_defects_count} lỗi** ({failed_tests} failures, {error_tests} errors) | {eval_defects} |\n")

    if classes_cov_val or methods_cov_val:
        markdown.append("### 📈 Chi tiết Đo Lường Độ Bao Phủ từ PCOV Engine & Clover XML")
        if classes_cov_val:
            markdown.append(f"- **Scope:** `{classes_cov_val}`")
        if methods_cov_val:
            markdown.append(f"- **Methods Covered:** `{methods_cov_val}`")
        markdown.append(f"- **Statements Covered:** `{line_coverage_display}`\n")

    sec_cases = [tc for tc in test_cases_data if tc[4]]
    other_count = len(test_cases_data) - len(sec_cases)

    markdown.append("---")
    markdown.append("\n## 2. ⏱️ Danh Sách Ca Kiểm Thử Trọng Tâm Phân Vùng 4 (`TC-SEC-*` & `TC-SEC-API-*`)")
    if other_count > 0:
        markdown.append(f"> *(Ngoài **{len(sec_cases)} lần thực thi `TC-SEC-*` / `TC-SEC-API-*`**, suite còn chạy hồi quy **{other_count} tests** thuộc `UserTest`, `CompanyScopingTest`, `ImpersonateUserTest`, `LoginTest` — tổng cộng **{total_tests} tests / {total_assertions} assertions**.)*\n")
    markdown.append("\n| STT | Tên Ca Kiểm Thử | Assertions | Thời gian thực tế | Kết quả |")
    markdown.append("| :---: | :--- | :---: | :---: | :---: |")

    display_cases = sec_cases if sec_cases else test_cases_data
    if display_cases:
        for idx, (tc_name, tc_time, tc_assert, tc_status, _) in enumerate(display_cases, 1):
            markdown.append(f"| {idx:02d} | `{tc_name}` | **{tc_assert}** | **{tc_time}** | {tc_status} |")
    else:
        markdown.append("| - | *Chưa ghi nhận dữ liệu testcase từ XML/Log* | **0** | **0.00s** | ⚠️ N/A |")

    # 5. Tính toán SHA-256 Checksum cho các tệp bằng chứng (không tự hash chính checksums.sha256)
    checksum_lines = []
    checksum_file = os.path.join(reports_dir, "checksums.sha256")
    files_to_hash = []

    if os.path.exists(reports_dir):
        for fname in sorted(os.listdir(reports_dir)):
            fpath = os.path.join(reports_dir, fname)
            if os.path.isfile(fpath) and fname not in ("checksums.sha256", "SUMMARY_REPORT.md"):
                fhash = calculate_sha256(fpath)
                checksum_lines.append(f"{fhash}  {fname}")
                files_to_hash.append((fname, fhash))

        with open(checksum_file, "w", encoding="utf-8") as f:
            f.write("\n".join(checksum_lines) + "\n")
        print(f"Đã tạo mã băm SHA-256 tại: {checksum_file}")

    markdown.append("\n---")
    markdown.append("\n## 3. 🗄️ Cấu Trúc Bằng Chứng Kiểm Thử & Chữ Ký Toàn Vẹn (SHA-256)")
    markdown.append("\n| Tên tệp tin bằng chứng | Chuẩn kỹ thuật | Mục đích sử dụng | Mã băm SHA-256 |")
    markdown.append("| :--- | :--- | :--- | :--- |")

    for fname, fhash in files_to_hash:
        desc = "Console output"
        fmt = "Text"
        if fname.endswith(".xml") and "clover" in fname:
            desc = "Đo độ bao phủ dòng/hàm chuẩn máy đọc"
            fmt = "Clover XML"
        elif fname.endswith(".xml") and "junit" in fname:
            desc = "Báo cáo ma trận testcases chuẩn máy đọc"
            fmt = "JUnit XML"
        elif fname.endswith(".html"):
            desc = "Báo cáo tài liệu kiểm thử HTML"
            fmt = "TestDox HTML"
        elif fname == "mysql_container_proof.json":
            desc = "Chứng minh kết nối Docker MySQL 8.0.36 thật"
            fmt = "JSON Proof"
        elif fname == "environment_metadata.json":
            desc = "Siêu dữ liệu Commit SHA, Branch, Tag, PHP"
            fmt = "JSON Metadata"
        elif fname == "git_commit_evidence.txt":
            desc = "Lịch sử Git Atomic Commits trên nhánh"
            fmt = "Git Log"
        elif fname.endswith(".log"):
            desc = "Log console thực thi chi tiết"
            fmt = "Plain Text"
        markdown.append(f"| `{fname}` | `{fmt}` | {desc} | `{fhash[:16]}...{fhash[-8:]}` |")

    if os.path.exists(os.path.join(reports_dir, "coverage-html")):
        markdown.append("| `coverage-html/` | `Visual HTML Dashboard` | Giao diện web tô màu từng dòng mã nguồn | `Thư mục Web đính kèm` |")

    markdown.append("\n> 📦 **Gói nén tổng hợp:** `storage/logs/artifacts/Evidence_PhanVung4_v1.0.zip` chứa trọn bộ các tệp trên để nghiệm thu.\n")

    summary_text = "\n".join(markdown) + "\n"

    # Luôn lưu thêm bản sao tại storage/logs/test-reports/SUMMARY_REPORT.md
    local_summary_path = os.path.join(reports_dir, "SUMMARY_REPORT.md")
    os.makedirs(reports_dir, exist_ok=True)
    with open(local_summary_path, "w", encoding="utf-8") as f:
        f.write(summary_text)

    if output_summary_path and os.path.abspath(output_summary_path) != os.path.abspath(local_summary_path):
        with open(output_summary_path, "a", encoding="utf-8") as f:
            f.write(summary_text)
        print(f"Đã xuất bản Dashboard động vào: {output_summary_path}")
    else:
        print(summary_text)

    # 6. Đóng gói Artifacts ZIP đệ quy sau khi hoàn tất tất cả các file
    artifacts_dir = "storage/logs/artifacts"
    os.makedirs(artifacts_dir, exist_ok=True)
    zip_output_path = os.path.join(artifacts_dir, "Evidence_PhanVung4_v1.0.zip")
    with zipfile.ZipFile(zip_output_path, "w", zipfile.ZIP_DEFLATED) as zipf:
        if os.path.exists(reports_dir):
            for root_dir, dirs, files in os.walk(reports_dir):
                for file in files:
                    full_path = os.path.join(root_dir, file)
                    rel_path = os.path.relpath(full_path, reports_dir)
                    zipf.write(full_path, arcname=rel_path)
    print(f"Đã đóng gói bằng chứng vào: {zip_output_path}")


if __name__ == "__main__":
    log_file = sys.argv[1] if len(sys.argv) > 1 else "storage/logs/test-reports/phpunit_part4_execution.log"
    out_file = sys.argv[2] if len(sys.argv) > 2 else None
    junit_file = sys.argv[3] if len(sys.argv) > 3 else None
    generate_summary(log_file, out_file, junit_file)
