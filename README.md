# Recruiter
[![CI Pipeline](https://github.com/recruiterphp/recruiter/actions/workflows/ci.yml/badge.svg)](https://github.com/recruiterphp/recruiter/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/recruiterphp/recruiter/v/stable)](https://packagist.org/packages/recruiterphp/recruiter)
[![License](https://poser.pugx.org/recruiterphp/recruiter/license)](https://packagist.org/packages/recruiterphp/recruiter)

A **battle-tested** job queue manager for PHP, with **billions of jobs processed** over the years in production environments.

## ✨ Features

- 🚀 **Production Ready** - Billions of jobs processed over the years with proven reliability
- 🔄 **Advanced Retry Policies** - Exponential backoff, custom strategies
- 🏷️ **Multi-Queue Support** - Job tagging and filtering
- 📊 **Full Job History** - Built-in analytics and monitoring
- 🛡️ **Fault Tolerant** - Graceful failure handling and recovery
- ⚡ **High Performance** - Optimized for scale with MongoDB
- 🐳 **Docker Ready** - Complete development environment included
- 🧪 **Fully Tested** - Comprehensive test suite with property-based testing

## Requirements

- PHP 8.4+
- MongoDB Extension >=1.15
- MongoDB Server 4.0+

## Installation

```bash
composer require recruiterphp/recruiter
```

## Quick Start

```php
use Recruiter\Recruiter;
use Recruiter\Workable;
use Recruiter\WorkableBehaviour;
use MongoDB\Client;

// Create a job class
class EmailJob implements Workable
{
    use WorkableBehaviour;

    public function execute(): void
    {
        // Write your logic here
        mail(
            $this->parameters['email'],
            $this->parameters['subject'],
            $this->parameters['body']
        );
    }
}

// Connect to MongoDB
$factory = new Factory();
$db = $factory->getMongoDb(
    MongoURI::fromEnvironment(),
    $options = [],
);

// Set up Recruiter
$recruiter = new Recruiter($db);

// Schedule a job
new EmailJob([
    'email' => 'user@example.com',
    'subject' => 'Welcome!',
    'body' => 'Thanks for joining us!'
])
    ->asJobOf($recruiter)
    ->inBackground()
    ->execute();
```

## 🏢 Production Heritage

Recruiter was born at Onebip in **2014**, a major mobile payment platform processing millions of transactions daily. Later adopted by EasyWelfare in **2018**, it has handled critical operations across both platforms for years:

- ✅ Jobs cannot be lost (payments aren't idempotent)
- 📋 Jobs must be traceable (for customer support)
- ⏰ Failed jobs need smart retry logic (respecting rate limits)

After **billions of jobs processed** over the years in production (2014-2024), we open-sourced this battle-tested solution. Both original platforms have since been replaced due to corporate acquisitions, but Recruiter lives on as a proven, independent solution.

## 🚀 Development

```bash
# Clone and set up development environment
git clone https://github.com/recruiterphp/recruiter.git
cd recruiter

# Start development environment
make build && make up

# Run tests
make test

# Code quality checks
make fix-cs      # Fix code style
make phpstan     # Static analysis
make rector      # Code modernization
```

## 📚 Documentation

- **[Complete Documentation](https://recruiter.readthedocs.io/)** - Comprehensive guides and API reference
- **[Website](https://recruiterphp.org)** - Project overview and quick start (Work in Progress)
- **[Examples](./examples/)** - Ready-to-run code examples

## 🤝 Related Projects

Part of the RecruiterPHP ecosystem:
- **[concurrency](https://github.com/recruiterphp/concurrency)** - MongoDB-based distributed locking
- **[geezer](https://github.com/recruiterphp/geezer)** - Tools for robust long-running processes
- **[clock](https://github.com/recruiterphp/clock)** - Testable time handling and MongoDB integration
- **[precious](https://github.com/recruiterphp/precious)** - Value object library
- **[zeiss](https://github.com/recruiterphp/zeiss)** - Event sourcing projections
