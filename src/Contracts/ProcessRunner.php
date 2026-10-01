<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Contracts;

use JOOservices\CrawlerXNews\Dto\ProcessResultDto;

interface ProcessRunner
{
    /**
     * @param  list<string>  $command
     */
    public function run(array $command, int $timeoutSeconds = 120, ?string $cwd = null): ProcessResultDto;
}
