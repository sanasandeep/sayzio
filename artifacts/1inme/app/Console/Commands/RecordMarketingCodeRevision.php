<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Controllers\SitemapController;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Symfony\Component\Process\Process;

class RecordMarketingCodeRevision extends Command
{
    protected $signature = 'marketing:record-code-revision';

    protected $description = 'Record the homepage source commit date for truthful sitemap modification dates';

    public function handle(): int
    {
        $root = dirname(base_path(), 2);
        $process = new Process(['git', 'log', '-1', '--format=%cI', '--', 'artifacts/1inme/resources/views/home.blade.php', 'artifacts/1inme/resources/views/home', 'artifacts/1inme/app/Modules/Common/Support/MarketingSeo.php', 'artifacts/1inme/app/Modules/Common/Support/HomeFormatCatalogue.php', 'artifacts/1inme/app/Modules/Common/Support/SitePagesContent.php', 'artifacts/1inme/resources/views/public/partials/header.blade.php', 'artifacts/1inme/resources/views/public/partials/footer.blade.php'], $root);
        $process->setTimeout(10);
        $process->run();
        $date = trim($process->getOutput());
        if (! $process->isSuccessful() || $date === '') {
            $this->warn('No Git revision available; keeping the existing sitemap content date.');

            return self::SUCCESS;
        }
        $updated = Carbon::parse($date);
        $previous = AppSetting::get('marketing_home_code_updated_at');
        if (! $previous || $updated->greaterThan(Carbon::parse($previous))) {
            AppSetting::put('marketing_home_code_updated_at', $updated->toIso8601String());
            SitemapController::flushPublicCaches();
        }
        $this->info('Homepage code revision recorded: '.$updated->toIso8601String());

        return self::SUCCESS;
    }
}
