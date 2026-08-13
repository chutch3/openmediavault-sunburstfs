<?php
declare(strict_types=1);

namespace Sunburstfs;

/**
 * The abstraction we own around shelling out with a hard time bound
 * -- DuDiskUsageScanner's dependency, not `du` itself. Separated out
 * specifically so the kill-on-timeout behaviour is testable against a
 * fast, controllable command (see TimeoutBoundedProcessTest, which
 * uses `sleep`), rather than needing real `du` to actually hang.
 */
final class TimeoutBoundedProcess
{
    public function __construct(
        private readonly int $timeoutSeconds,
        private readonly int $killAfterSeconds = 30
    ) {
    }

    /**
     * Runs $command (must already be fully shell-escaped by the
     * caller) under `timeout`, killing it if it exceeds the
     * configured limit. Known, unavoidable limitation: a process
     * stuck in an uninterruptible I/O wait (D state) on truly broken
     * hardware can't be killed by any signal until the underlying I/O
     * itself resolves -- nothing in userspace can fix that, so this
     * bounds the common cases, not literally every case.
     *
     * @return array<int, string>
     */
    public function run(string $command): array
    {
        $wrapped = sprintf(
            "timeout --kill-after=%ds %ds %s",
            $this->killAfterSeconds,
            $this->timeoutSeconds,
            $command
        );

        exec($wrapped, $output);

        return $output;
    }
}
