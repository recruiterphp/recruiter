<?php

namespace Recruiter\Command;

use Recruiter\Recruiter;
use Recruiter\Workable\ShellCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RecruiterJobCommand extends Command
{
    public function __construct(private readonly Recruiter $recruiter)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('recruiter:command')
            ->setDescription('Runs a shell command inside the recruiter')
            ->addArgument(
                'shell_command',
                InputArgument::REQUIRED,
                'The command to run',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ShellCommand::fromCommandLine($input->getArgument('shell_command'))
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute()
        ;

        return self::SUCCESS;
    }
}
