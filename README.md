# VoltTest PHP SDK

VoltTest is a high-performance PHP performance testing SDK powered by a Golang engine.
It combines PHP’s simplicity with Go’s speed and concurrency, allowing you to define, run, and analyze tests with an intuitive API while leveraging Go for efficient load generation.

## Features
- [x] **Multiple Scenario Support with Weights** – Run different test scenarios with custom weight distributions.
- [x] **Data Provider for Virtual Users** - Assign dynamic data to virtual users for realistic test simulations.
- [x] **Extract Data from Requests** – Capture and reuse response data in subsequent requests.
- [x] **Request Customization & Response Validation** – Modify headers, payloads, and assert results.
- [x] **Think Time & Ramp-Up Configuration** – Simulate real-user behavior.
- [x] **Detailed Reports & Distributed Execution** – Scale tests and analyze results.
- [x] **Debug Requests** - Inspect and troubleshoot request/response payloads easily.
- [ ] **Cloud Execution** – Seamless cloud-based testing in progress.


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

## Requirements

- PHP 8.0 or higher
- ext-json
- ext-pcntl
- ext-curl

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

For more examples and detailed documentation, visit [https://php.volt-test.com](https://php.volt-test.com)
