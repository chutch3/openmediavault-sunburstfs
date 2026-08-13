<?php
declare(strict_types=1);

namespace Sunburstfs;

/**
 * The framework-dependent half: calls core's own FileSystemMgmt RPC
 * rather than querying the config database directly, specifically
 * because that RPC's response already carries (uuid, mountpoint)
 * pairs in the same uuid namespace the picker page's rows use --
 * avoiding the wrong-primary-key assumption that broke the first
 * version of this lookup. Not unit tested: like DuDiskUsageScanner's
 * `exec()` call, this only talks to something that doesn't exist
 * outside a real OMV install, so it's verified live instead.
 *
 * Rpc::call() defaults to MODE_LOCAL, which only works called from
 * inside omv-engined itself, where every RPC service class is already
 * registered. This class is only ever used from the standalone
 * refresh CLI script, a separate process with no such registry --
 * confirmed by testing ("RPC service 'FileSystemMgmt' not found") and
 * by reading OMV's own omv-rpc CLI tool, which always passes
 * MODE_REMOTE explicitly for exactly this reason: it goes over the
 * socket to the actual running omv-engined daemon instead.
 */
final class OmvFilesystemDirectory implements FilesystemDirectoryInterface
{
    public function __construct(private readonly array $context)
    {
    }

    public function listMounted(): array
    {
        $filesystems = \OMV\Rpc\Rpc::call(
            "FileSystemMgmt",
            "enumerateMountedFilesystems",
            [],
            $this->context,
            \OMV\Rpc\Rpc::MODE_REMOTE
        );

        return array_map(
            static fn (array $fs): array => ["uuid" => $fs["uuid"], "mountpoint" => $fs["mountpoint"]],
            $filesystems
        );
    }
}
