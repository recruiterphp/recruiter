<?php

declare(strict_types=1);

namespace Recruiter\Infrastructure\Persistence\Mongodb;

final readonly class URI implements \Stringable
{
    public const string DEFAULT_URI = 'mongodb://127.0.0.1:27017/recruiter';

    public function __construct(private string $uri)
    {
    }

    public static function fromEnvironment(): self
    {
        return self::from(getenv('MONGODB_URI'));
    }

    public static function from(string|self|null $uri): self
    {
        if ($uri instanceof self) {
            return $uri;
        }

        if (!$uri) {
            $uri = self::DEFAULT_URI;
        }

        return new self($uri);
    }

    public function database(): string
    {
        $parsed = parse_url($this->uri);
        if (!$parsed) {
            throw new \InvalidArgumentException("$this->uri is not a valid mongo uri");
        }

        return substr($parsed['path'], 1);
    }

    public function __toString(): string
    {
        return $this->uri;
    }
}
