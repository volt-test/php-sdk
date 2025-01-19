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
git clone git@github.com:volt-test/php-sdk.git
cd php-sdk
composer install
```

### Basic Usage
```bash
touch test.php
```
```php
<?php
require 'vendor/autoload.php';

use VoltTest\DataSourceConfiguration;
use VoltTest\VoltTest;

// Create a new test
$test = new VoltTest(
    'User Login Flow',
    'Tests user authentication process'
);
// Configure test parameters
$test
    ->setVirtualUsers(1) // number of VUs (Virtual Users)
//    ->setDuration('1s') // to run the test for 1 second
    ->setHttpDebug(true); // to enable http debug mode remove it if you don't want to see the http debug

// Create login scenario
$loginScenario = $test->scenario('User Login')
    ->autoHandleCookies() // this will save cookies with all requests in this scenario
    ->setDataSourceConfiguration(new DataSourceConfiguration(__DIR__ .'/data-file.csv', 'sequential', true));


$loginScenario->step('Register')
    ->get('http://localhost:8001/register')
    ->extractFromRegex('csrf_token_register', 'name="_token" value="(.+?)"') // Extract the csrf token to submit a form
    ->header('Accept', 'text/html');

$loginScenario->step('Submit Register')
    ->post(
        'http://localhost:8001/register',
        '_token=${csrf_token_register}&name=Test-v&email=${email}&password=${password}&password_confirmation=${password}') // send data with extracted data and source file
    ->header('Content-Type', 'application/x-www-form-urlencoded')
    ->validateStatus('status validator from php',302);


// Add first step - Get login page
$loginScenario->step('get_login_page')
    ->get('http://localhost:8001/login')
    ->header('Accept', 'text/html')
    ->extractFromRegex('csrf_token', 'name="_token" value="(.+?)"')
    ->validateStatus('status validator from php',200);

// Add second step - Submit login
$loginScenario->step('submit_login')
    ->post(
        'http://localhost:8001/login',
        '_token=${csrf_token}&email=${email}&password=${password}')
    ->header('Content-Type', 'application/x-www-form-urlencoded')
->validateStatus('status validator from php',302);


// Add third step - Visit dashboard
$loginScenario->step('visit_dashboard')
    ->get('http://localhost:8001/dashboard')
    ->header('Accept', 'text/html')
    ->validateStatus('status_code', 200);
// Run the test 
// This will start the test and block until it completes
// pass true to run() to run the test with progress and real time results
$result = $test->run();
// OR $test->run(true);  to run the test with progress and real time results

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