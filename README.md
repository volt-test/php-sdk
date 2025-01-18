# VoltTest PHP SDK

VoltTest is a powerful, easy-to-use performance testing SDK for PHP applications.
Powered by a high-performance Golang engine running behind the scenes,
it combines the ease of use of PHP with the raw power and concurrency capabilities of Go.
This unique architecture enables you to create, run, and analyze performance tests with a fluent,
intuitive API while leveraging Go's superior performance characteristics for the actual load generation.

## Architecture
VoltTest PHP SDK works as a bridge between your PHP application and the VoltTest Engine (written in Go). When you run a test:

Your PHP code defines the test scenarios and configurations
The SDK transforms these into a format the Go engine understands
The Go engine executes the actual load testing
Results are streamed back to your PHP application for analysis

This architecture provides several benefits:

Write tests in PHP while getting Go's performance benefits
True parallel execution of virtual users
Minimal resource footprint during test execution
Accurate timing and metrics collection

## Documentation

For detailed documentation, visit [https://php.volt-test.com](https://php.volt-test.com)

## Quick Start

### Installation

```bash
composer require volt-test/php-sdk
```

### Basic Usage

```php
use VoltTest\VoltTest;

// Create a new test
$test = new VoltTest('API Load Test', 'Testing API endpoints under load');

// Configure the test
$test->setVirtualUsers(10)
    ->setDuration('1m')
    ->setRampUp('10s')
    ->setTarget('https://api.example.com');

// Create a test scenario
$scenario = $test->scenario('Basic API Flow')
    ->setWeight(1)
    ->autoHandleCookies();

// Add test steps
$scenario->step('Get Users')
    ->get('/api/users')
    ->validateStatus('success', 200)
    ->extractFromJson('userId', 'data[0].id');

$scenario->step('Get User Details')
    ->get('/api/users/${userId}')
    ->validateStatus('success', 200);

// Run the test
$result = $test->run();

// Access test results
echo "Success Rate: " . $result->getSuccessRate() . "%\n";
echo "Average Response Time: " . $result->getAvgResponseTime() . "\n";
```

## Features

- Cross-platform support (Windows, Linux, MacOS)
- Data Provider for Virtual Users
- Comprehensive performance metrics
- Multiple scenario support with weights
- Request customization
- Response validation
- Think time simulation
- Ramp-up configuration
- Progress tracking
- Detailed reports
- Debug Requests
- Easy-to-use API

## Requirements

- PHP 8.0 or higher
- ext-json
- ext-pcntl

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

For more examples and detailed documentation, visit [https://php.volt-test.com](https://php.volt-test.com)