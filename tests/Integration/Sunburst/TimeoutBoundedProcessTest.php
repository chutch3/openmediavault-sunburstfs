<?php
declare(strict_types=1);

namespace Sunburstfs\Tests\Integration\Sunburst;

use PHPUnit\Framework\TestCase;
use Sunburstfs\TimeoutBoundedProcess;

/**
 * Real subprocess execution (via the real `timeout` coreutils binary)
 * -- this is exactly the fault-tolerance property that mattered:
 * DuDiskUsageScanner previously had no bound on `du` at all, and a
 * stuck NFS mount or failing drive could hold a filesystem's lock
 * forever. Proving the kill actually happens, fast and
 * deterministically via `sleep` rather than needing real `du` to
 * hang, is the point of this class existing separately.
 */
final class TimeoutBoundedProcessTest extends TestCase
{
    public function testKillsACommandThatExceedsTheTimeout(): void
    {
        $process = new TimeoutBoundedProcess(timeoutSeconds: 1, killAfterSeconds: 1);

        $start = microtime(true);
        $process->run("sleep 30");
        $elapsed = microtime(true) - $start;

        // Proves the process was actually killed rather than run to
        // completion -- if `timeout` weren't wired up correctly, this
        // would take the full 30s and fail this assertion.
        self::assertLessThan(10.0, $elapsed);
    }

    public function testReturnsOutputOfACommandThatFinishesWithinTheTimeout(): void
    {
        $process = new TimeoutBoundedProcess(timeoutSeconds: 5, killAfterSeconds: 1);

        $output = $process->run("echo hello");

        self::assertSame(["hello"], $output);
    }
}
