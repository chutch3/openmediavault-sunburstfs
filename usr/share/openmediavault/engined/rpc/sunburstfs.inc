<?php
declare(strict_types=1);

/**
 * This file is part of openmediavault-sunburstfs.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 */
require_once("openmediavault/globals.inc");
require_once("openmediavault/functions.inc");

require_once __DIR__ . "/sunburstfs/SunburstConfig.php";

use Sunburstfs\SunburstConfig;

class OMVRpcServiceSunburstfs extends \OMV\Rpc\ServiceAbstract
{
    public function getName()
    {
        return "Sunburstfs";
    }

    public function initialize()
    {
        $this->registerMethod("getPage");
    }

    // Cache read only, never scans -- a synchronous scan here can run
    // long enough to get omv-engined SIGKILLed by its own watchdog.
    // Generation only happens in the background refresh script/timer.
    public function getPage($params, $context)
    {
        $this->validateMethodContext($context, ["role" => OMV_ROLE_ADMINISTRATOR]);

        if (empty($params["fsUuid"])) {
            // Caller error, not a NAS-health concern -- throw directly.
            throw new \OMV\Exception("Missing required parameter 'fsUuid'.");
        }

        // Everything below degrades to a placeholder instead of a raw
        // RPC error -- this page should never be what breaks.
        try {
            $cachePath = SunburstConfig::cachePathFor($params["fsUuid"]);
            if (file_exists($cachePath)) {
                return $this->pageResponse($cachePath, FALSE);
            }

            $message = "Chart not generated yet -- check back shortly";
        } catch (\Throwable $e) {
            $message = "Chart unavailable";
        }

        // Needs a real .html extension, not just tempnam()'s bare name:
        // confirmed by testing that download.php's Content-Type
        // detection keys off the file extension, not only our headers
        // below -- without it the browser got this same body as
        // application/json and refused to render it.
        $tempPath = tempnam(sys_get_temp_dir(), "sunburstfs-");
        $filePath = $tempPath . ".html";
        rename($tempPath, $filePath);
        file_put_contents($filePath, $this->placeholderHtml($message));
        chmod($filePath, 0644);

        return $this->pageResponse($filePath, TRUE);
    }

    private function placeholderHtml(string $message)
    {
        $escaped = htmlspecialchars($message, ENT_QUOTES);

        return "<!doctype html><html><body style=\"background:#1b1b1b;color:#e8e8e8;"
            . "font-family:sans-serif;display:flex;align-items:center;justify-content:center;"
            . "height:100vh;margin:0;\">{$escaped}</body></html>";
    }

    private function pageResponse(string $filePath, bool $unlink)
    {
        return [
            "filepath" => $filePath,
            "unlink" => $unlink,
            "headers" => [
                "Content-Type" => "text/html",
                "Cache-Control" => "no-store",
            ],
        ];
    }
}
