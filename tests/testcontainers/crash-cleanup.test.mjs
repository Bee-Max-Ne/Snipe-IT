/**
 * TC-INF-01 (Phân vùng 3 – Nguyễn Đình Thạch, 2314506)
 * Kiểm chứng khối `finally { await container.stop(); }` trong run.mjs dọn
 * container MySQL kể cả khi lần chạy bị crash.
 *
 * Ryuk (tiến trình dọn rác dự phòng của Testcontainers) bị tắt bằng
 * TESTCONTAINERS_RYUK_DISABLED=true, nên nếu container vẫn bị hủy thì chắc
 * chắn là nhờ khối finally. Bằng chứng lấy từ `docker events`: container
 * mysql:8.0.36 phải có sự kiện `start` rồi `destroy`, và không còn sót lại.
 *
 * Chạy: npm run test:k16:infra   (cần Docker đang chạy; PHP_BINARY nếu php không có trong PATH)
 * Không chạy song song với một lần chạy K16 khác: container của lần chạy đó
 * sẽ lẫn vào danh sách sự kiện docker trong cùng khoảng thời gian.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const runner = path.join(projectRoot, 'tests', 'testcontainers', 'run.mjs');
const crashFixture = path.join('tests', 'testcontainers', 'fixtures', 'CrashingProcessTest.php');
const testingEnvironment = path.join(projectRoot, '.env.testing');
const IMAGE = 'mysql:8.0.36';
const TEN_MINUTES = 10 * 60 * 1000;

function docker(args) {
    const result = spawnSync('docker', args, { encoding: 'utf8' });
    if (result.error) {
        throw result.error;
    }
    assert.equal(result.status, 0, `docker ${args.join(' ')} thất bại: ${result.stderr}`);
    return result.stdout.trim();
}

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function runProcess(command, args, options) {
    return new Promise((resolve, reject) => {
        const child = spawn(command, args, options);
        let stdout = '';
        let stderr = '';
        child.stdout.on('data', (chunk) => { stdout += chunk; });
        child.stderr.on('data', (chunk) => { stderr += chunk; });
        child.on('error', reject);
        child.on('close', (status) => resolve({ status, stdout, stderr }));
    });
}

async function runRunner(extraEnv, args) {
    // Docker daemon chỉ giữ ~256 sự kiện gần nhất; các lệnh kiểm tra sức khỏe MySQL
    // của Testcontainers (exec_*) sinh hàng trăm sự kiện và đẩy mất sự kiện `start`.
    // Vì vậy phải mở luồng sự kiện TRƯỚC khi chạy runner, chỉ lọc start/destroy.
    const events = spawn('docker', [
        'events',
        '--filter', 'type=container', '--filter', `image=${IMAGE}`,
        '--filter', 'event=start', '--filter', 'event=destroy',
        '--format', '{{.Action}} {{.Actor.ID}}',
    ]);
    let eventLog = '';
    events.stdout.on('data', (chunk) => { eventLog += chunk; });
    await sleep(1000);

    const result = await runProcess(process.execPath, [runner, ...args], {
        cwd: projectRoot,
        env: { ...process.env, TESTCONTAINERS_RYUK_DISABLED: 'true', ...extraEnv },
    });

    await sleep(2000);
    const closed = new Promise((resolve) => events.once('close', resolve));
    events.kill();
    await closed;

    const lines = eventLog.split(/\r?\n/).filter(Boolean);
    const started = new Set(lines.filter((l) => l.startsWith('start ')).map((l) => l.split(' ')[1]));
    const destroyed = new Set(lines.filter((l) => l.startsWith('destroy ')).map((l) => l.split(' ')[1]));

    return { result, started, destroyed };
}

function assertContainersCleanedUp({ started, destroyed }) {
    assert.ok(started.size >= 1, 'Runner phải khởi động ít nhất 1 container MySQL trước khi crash');
    for (const id of started) {
        assert.ok(destroyed.has(id), `Container ${id.slice(0, 12)} không bị hủy sau khi crash`);
        assert.equal(docker(['ps', '-a', '-q', '--filter', `id=${id}`]), '', `Container ${id.slice(0, 12)} vẫn còn tồn tại`);
    }
}

test('TC-INF-01a: PHPUnit chết đột ngột (exit 255) – container vẫn bị dọn', { timeout: TEN_MINUTES }, async () => {
    const envFileExisted = existsSync(testingEnvironment);

    const run = await runRunner({}, [crashFixture]);

    assert.equal(run.result.status, 255, `Runner phải trả về mã thoát của PHPUnit (255). stderr: ${run.result.stderr}`);
    assert.match(run.result.stderr + run.result.stdout, /TC-INF-01: gia lap PHPUnit crash/);
    assertContainersCleanedUp(run);
    assert.equal(existsSync(testingEnvironment), envFileExisted, '.env.testing tạm phải được xóa lại như trước khi chạy');
});

test('TC-INF-01b: không tìm thấy PHP (runner ném lỗi) – container vẫn bị dọn', { timeout: TEN_MINUTES }, async () => {
    const envFileExisted = existsSync(testingEnvironment);

    const run = await runRunner({ PHP_BINARY: path.join(projectRoot, 'khong-ton-tai', 'php.exe') }, [crashFixture]);

    assert.notEqual(run.result.status, 0, 'Runner phải thoát với mã lỗi');
    assert.match(run.result.stderr, /PHP CLI was not found/);
    assertContainersCleanedUp(run);
    assert.equal(existsSync(testingEnvironment), envFileExisted, '.env.testing tạm phải được xóa lại như trước khi chạy');
});
