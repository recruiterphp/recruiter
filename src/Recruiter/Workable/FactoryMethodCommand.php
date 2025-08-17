<?php

declare(strict_types=1);

namespace Recruiter\Workable;

use Recruiter\JobToSchedule;
use Recruiter\Recruiter;
use Recruiter\Workable;

class FactoryMethodCommand implements Workable
{
    /**
     * @param callable-string $callable
     */
    public static function from(string $callable, mixed ...$arguments): self
    {
        /** @var class-string $class */
        [$class, $method] = explode('::', $callable);

        return self::singleStep(self::stepFor($class, $method, $arguments));
    }

    /**
     * @param array{class?: class-string, method: string, arguments?: array<mixed>} $step
     */
    private static function singleStep(array $step): self
    {
        return new self([
            $step,
        ]);
    }

    /**
     * @param class-string $class
     * @param array<mixed> $arguments
     *
     * @return array{class: class-string, method: string, arguments?: array<mixed>}
     */
    private static function stepFor(string $class, string $method, array $arguments): array
    {
        $step = [
            'class' => $class,
            'method' => $method,
        ];
        if ($arguments) {
            $step['arguments'] = $arguments;
        }

        return $step;
    }

    /**
     * @param array<array{class?: class-string, method: string, arguments?: array<mixed>}> $steps
     */
    private function __construct(private array $steps = [])
    {
    }

    public function asJobOf(Recruiter $recruiter): JobToSchedule
    {
        return $recruiter->jobOf($this);
    }

    public function execute(mixed $retryOptions = null): mixed
    {
        $result = null;
        $lastStepIndex = count($this->steps) - 1;
        foreach ($this->steps as $index => $step) {
            if (isset($step['class'])) {
                $callable = $step['class'] . '::' . $step['method'];
            } else {
                $callable = [$result, $step['method']];
            }
            if (!is_callable($callable)) {
                $message = 'The following step does not result in a callable: ' . var_export($step, true) . '.';
                if (is_object($result)) {
                    $message .= ' Reached object: ' . $result::class;
                } else {
                    $message .= ' Reached value: ' . var_export($result, true);
                }
                throw new \BadMethodCallException($message);
            }
            $arguments = $this->arguments($step);
            if ($index === $lastStepIndex) {
                $arguments[] = $retryOptions;
            }
            $result = call_user_func_array(
                $callable,
                $arguments,
            );
        }

        return $result;
    }

    /**
     * @param array{arguments?: array<mixed>} $step
     *
     * @return array<mixed>
     */
    private function arguments(array $step): array
    {
        return $step['arguments'] ?? [];
    }

    /**
     * @param array<mixed> $arguments
     *
     * @return $this
     */
    public function __call(string $method, array $arguments): self
    {
        $step = [
            'method' => $method,
        ];
        if ($arguments) {
            $step['arguments'] = $arguments;
        }
        $this->steps[] = $step;

        return $this;
    }

    /**
     * @return array{steps: array<array<string, mixed>>}
     */
    public function export(): array
    {
        return [
            'steps' => $this->steps,
        ];
    }

    /**
     * @param array{steps: array<array<string, mixed>>} $parameters
     */
    public static function import(array $parameters): static
    {
        return new static($parameters['steps']);
    }
}
