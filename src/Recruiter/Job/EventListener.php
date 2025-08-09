<?php

namespace Recruiter\Job;

interface EventListener
{
    public function onEvent(string $channel, Event $ev): void;
}
