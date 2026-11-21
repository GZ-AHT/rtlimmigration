<?php

namespace App\Console\Commands;

use App\Support\Seo\LinkScanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seo:check-links')]
#[Description('Scan website pages, posts and menus for links that no longer load (REQ §22.3)')]
class CheckBrokenLinks extends Command
{
    public function handle(LinkScanner $scanner): int
    {
        $count = $scanner->run();
        $this->info($count === 0 ? 'No broken links found.' : "{$count} broken link(s) found.");

        return self::SUCCESS;
    }
}
