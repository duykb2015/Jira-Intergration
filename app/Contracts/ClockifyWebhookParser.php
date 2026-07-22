<?php

namespace App\Contracts;

use App\Data\ClockifyTimeEntryData;

interface ClockifyWebhookParser
{
    public function parse(array $payload): ClockifyTimeEntryData;
}
