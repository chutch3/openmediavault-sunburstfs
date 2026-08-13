<?php
declare(strict_types=1);

namespace Sunburstfs;

/**
 * Picks a single filesystem to refresh -- the one whose cached chart
 * has gone longest without being regenerated, or one that's never
 * been generated at all (treated as more stale than any real mtime).
 * Pure decision logic; the real mtime lookup (filemtime() against the
 * cache directory) is the untested boundary, injected as a callable
 * so this is testable without touching a real filesystem.
 *
 * Confirmed by testing (a real incident) that refreshing every
 * mounted filesystem on a fixed short timer, regardless of how long
 * any individual scan takes, produces sustained background `du`
 * activity rather than an occasional bounded one. One filesystem per
 * tick spreads the same total work out instead of compounding it.
 */
final class StalestFilesystemPicker
{
    /**
     * @param callable(string): ?int $cacheMtime returns the cache
     *   file's mtime for a given fsUuid, or null if it doesn't exist yet
     */
    public function __construct(private $cacheMtime)
    {
    }

    /**
     * @param array<int, array{uuid: string, mountpoint: string}> $filesystems
     * @return array{uuid: string, mountpoint: string}|null null if $filesystems is empty
     */
    public function pick(array $filesystems): ?array
    {
        $stalest = null;
        $stalestRank = null;

        foreach ($filesystems as $filesystem) {
            $mtime = ($this->cacheMtime)($filesystem["uuid"]);
            // Never-generated (null) must rank before every real
            // mtime, however old.
            $rank = $mtime ?? PHP_INT_MIN;

            if ($stalestRank === null || $rank < $stalestRank) {
                $stalest = $filesystem;
                $stalestRank = $rank;
            }
        }

        return $stalest;
    }
}
