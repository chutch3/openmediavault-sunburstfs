<?php
declare(strict_types=1);

namespace Sunburstfs;

/**
 * Shared between the interactive RPC (sunburstfs.inc, cache-read
 * only) and the background refresh script (the only thing that
 * actually generates a chart) so the cache path and render parameters
 * can't drift out of sync between the two -- one is meaningless
 * without matching the other.
 */
final class SunburstConfig
{
    // Not a settings page yet, deliberately (YAGNI).
    public const CACHE_DIR = "/var/cache/openmediavault/sunburstfs";
    public const MAX_DEPTH = 4;
    public const TOP_N = 12;
    // Rendering is a standalone HTML+D3 page (SunburstPageGenerator);
    // D3 computes layout/color client-side.
    public const TEMPLATE_PATH = __DIR__ . "/templates/chart.html";
    // Kept as its own file (not pre-merged) so its ISC license stays
    // attached to a single unambiguous file in debian/copyright.
    public const D3_SOURCE_PATH = __DIR__ . "/templates/d3.v7.min.js";
    // Hard ceiling on a single `du` run -- bounds "stuck" (a dead NFS
    // mount), not "slow"; unattended, so a long legitimate scan is fine.
    public const SCAN_TIMEOUT_SECONDS = 7200;
    // Caps directory-entry count `du` may report. Confirmed by a real
    // incident: a pathologically large directory count (independent of
    // bytes used) exhausted RAM via exec() buffering. `du` prints
    // deepest-first, so truncation always drops the root's own line,
    // self-detected by SunburstTreeBuilder's "root not present" check.
    public const MAX_DIRECTORY_ENTRIES = 500_000;

    public static function cachePathFor(string $fsUuid): string
    {
        return self::CACHE_DIR . "/" . $fsUuid . ".html";
    }
}
