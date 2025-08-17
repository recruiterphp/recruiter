<?php

declare(strict_types=1);

namespace Recruiter\Infrastructure\Command;

use Symfony\Component\Console\Input\InputInterface;

trait OptionTools
{
    protected function getIntOption(InputInterface $input, string $name): int
    {
        return $this->validateNumericOption($input->getOption($name), $name, 'intval');
    }

    protected function getFloatOption(InputInterface $input, string $name): float
    {
        return $this->validateNumericOption($input->getOption($name), $name, 'floatval');
    }

    protected function getStringOption(InputInterface $input, string $name): string
    {
        return $this->validateStringOption($input->getOption($name), $name);
    }

    protected function getIntOptionOrNull(InputInterface $input, string $name): ?int
    {
        $option = $input->getOption($name);

        return null === $option ? null : $this->validateNumericOption($option, $name, 'intval');
    }

    protected function getFloatOptionOrNull(InputInterface $input, string $name): ?float
    {
        $option = $input->getOption($name);

        return null === $option ? null : $this->validateNumericOption($option, $name, 'floatval');
    }

    protected function getStringOptionOrNull(InputInterface $input, string $name): ?string
    {
        $option = $input->getOption($name);

        return null === $option ? null : $this->validateStringOption($option, $name);
    }

    /**
     * @param 'intval'|'floatval' $converter
     *
     * @return ($converter is 'intval' ? int : float)
     */
    private function validateNumericOption(mixed $option, string $name, string $converter): int|float
    {
        assert(is_numeric($option), new \InvalidArgumentException('Option "' . $name . '" is not a number'));

        return $converter($option);
    }

    private function validateStringOption(mixed $option, string $name): string
    {
        assert(
            is_scalar($option) || $option instanceof \Stringable,
            new \InvalidArgumentException('Option "' . $name . '" cannot be interpreted as a string'),
        );

        return (string) $option;
    }

    protected function getStringArgument(InputInterface $input, string $name): string
    {
        $argument = $input->getArgument($name);

        assert(
            is_scalar($argument) || $argument instanceof \Stringable,
            new \InvalidArgumentException('Argument "' . $name . '" cannot be interpreted as a string'),
        );

        return (string) $argument;
    }
}
