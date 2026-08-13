<?php
declare(strict_types=1);

namespace Sunburstfs;

final class DuDiskUsageScanner implements DiskUsageScannerInterface
{
    public function __construct(
        private readonly TimeoutBoundedProcess $process,
        private readonly int $maxDirectoryEntries = SunburstConfig::MAX_DIRECTORY_ENTRIES
    ) {
    }

    public function scan(string $path, int $maxDepth): array
    {
        // -x stays on this filesystem; -b uses apparent bytes, not
        // blocks. Symlinks aren't followed, so their tiny size just
        // folds into the parent's loose-files remainder.
        // `head -n N` caps directory-entry count -- confirmed by a real
        // incident that maxDepth alone doesn't bound memory (a
        // pathologically large directory count exhausted RAM via
        // exec() buffering). `du` prints deepest-first, so truncation
        // always drops the root's own line, which self-detects via
        // SunburstTreeBuilder's "root not present" check.
        $command = sprintf(
            "du -x -b --max-depth=%d %s 2>/dev/null | head -n %d",
            $maxDepth,
            escapeshellarg($path),
            $this->maxDirectoryEntries
        );

        // Exit code isn't checked: a permission-denied subdirectory
        // makes `du` exit non-zero even though stdout is otherwise
        // complete (verified). An unreadable $path itself yields empty
        // output, rejected by the tree builder's own boundary check.
        $output = $this->process->run($command);

        $sizesByPath = [];
        foreach ($output as $line) {
            if (preg_match('/^(\d+)\t(.+)$/', $line, $matches) !== 1) {
                continue;
            }
            $sizesByPath[$matches[2]] = (int) $matches[1];
        }

        return $sizesByPath;
    }
}
