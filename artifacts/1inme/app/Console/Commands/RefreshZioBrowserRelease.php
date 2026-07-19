<?php

namespace App\Console\Commands;

use App\Modules\Common\Support\ZioBrowserRelease;
use Illuminate\Console\Command;

/**
 * Scheduled refresh of the cached SayZio Browser release (the /download
 * page reads ONLY the cache — see ZioBrowserRelease). A failed fetch logs a
 * warning inside ZioBrowserRelease::refresh() and leaves the previous
 * cached release in place, so the page keeps serving the last-known links.
 */
class RefreshZioBrowserRelease extends Command
{
    protected $signature = 'zio-browser:refresh-release';

    protected $description = 'Refresh the cached SayZio Browser GitHub release powering the /download page.';

    public function handle(): int
    {
        if (ZioBrowserRelease::refresh()) {
            $release = ZioBrowserRelease::current();
            $this->info('Cached zio-browser release v' . ($release['version'] ?? '?'));

            return self::SUCCESS;
        }

        $reason = ZioBrowserRelease::lastRefreshError() ?? 'Release fetch failed';
        $this->error($reason);
        $this->warn('Previous cached release (if any) kept.');

        return self::FAILURE;
    }
}
