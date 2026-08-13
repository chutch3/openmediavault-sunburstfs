<?php
declare(strict_types=1);

namespace Sunburstfs;

interface DiskUsageScannerInterface
{
    /**
     * Returns the aggregate size in bytes of $path and every directory
     * beneath it up to $maxDepth levels down, keyed by absolute path.
     * A directory that could not be read (e.g. permission denied) is
     * still present in the result, reported as size 0 -- its parent's
     * own total already excludes whatever is actually inside it, since
     * the scan couldn't see it either. Verified against real `du`
     * behaviour, not assumed: a permission-denied subdirectory still
     * gets a result line at size 0, and the command's own exit code is
     * non-zero even though the output for everything readable is
     * complete and correct, so a non-zero exit must not be treated as
     * failure on its own.
     *
     * @return array<string, int>
     */
    public function scan(string $path, int $maxDepth): array;
}
