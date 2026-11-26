<?php

namespace Tests\Testcontainers\Fixtures;

use PHPUnit\Framework\TestCase;

/**
 * Fixture cho TC-INF-01 (Phân vùng 3 – Nguyễn Đình Thạch, 2314506).
 *
 * KHÔNG phải test nghiệp vụ. File nằm ngoài tests/Unit và tests/Feature nên
 * không thuộc suite nào trong phpunit.xml; chỉ được gọi trực tiếp bởi
 * tests/testcontainers/crash-cleanup.test.mjs để giả lập tiến trình PHPUnit
 * chết đột ngột (exit 255) trong khi container MySQL của K16 đang chạy.
 */
final class CrashingProcessTest extends TestCase
{
    public function test_simulated_crash(): void
    {
        fwrite(STDERR, "TC-INF-01: gia lap PHPUnit crash (exit 255)\n");
        exit(255);
    }
}
