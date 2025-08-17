<?php

declare(strict_types=1);

namespace Recruiter\Job;

interface EventListener
{
    public function onEvent(string $channel, Event $ev): void;
}
