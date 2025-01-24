<?php

namespace Tests\Units;

use PHPUnit\Framework\TestCase;
use VoltTest\Platform;

class PlatformTest extends TestCase
{
    private function cleanupTestEnvironment()
    {
        // Use the actual Platform logic to get vendor directories
        $reflection = new \ReflectionClass(Platform::class);
        $getVendorDirMethod = $reflection->getMethod('getVendorDir');
        $getVendorDirMethod->setAccessible(true);

        $platform = new Platform();
        $vendorDir = $getVendorDirMethod->invoke($platform);

        // Clean up binary directory
        $binaryDir = $vendorDir . '/volt-test/bin';
        if (is_dir($binaryDir)) {
            $files = glob("$binaryDir/*");
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            @rmdir($binaryDir);
        }

        // Clean up vendor/bin
        $vendorBinDir = $vendorDir . '/bin';
        $symlinkPath = $vendorBinDir . '/volt-test';
        if (file_exists($symlinkPath)) {
            unlink($symlinkPath);
        }
    }

    public function testDetectPlatform()
    {
        $reflection = new \ReflectionClass(Platform::class);
        $method = $reflection->getMethod('detectPlatform');
        $method->setAccessible(true);

        $platform = new Platform();
        $result = $method->invoke($platform);

        // Check that the detected platform is one of the supported platforms
        $this->assertContains($result, [
            'linux-amd64',
            'darwin-amd64',
            'darwin-arm64',
            'windows-amd64',
            'windows-AMD64',
        ], "Detected platform $result is not in the supported platforms list");
    }

    public function testGetBinaryPath()
    {
        $binaryPath = Platform::getBinaryPath();

        // Install binary first to ensure it exists
        Platform::installBinary();

        // Assert that the binary path exists
        $this->assertFileExists($binaryPath, "Binary file does not exist at $binaryPath");

        // Assert that the binary is executable
        $this->assertTrue(is_executable($binaryPath), "Binary file is not executable");

        // Assert that the binary path contains the expected directory structure
        $this->assertStringContainsString(
            '/bin',
            $binaryPath,
            "Binary path does not contain the expected directory structure"
        );
    }

    public function testInstallBinary()
    {
        // Clean up before test
        $this->cleanupTestEnvironment();

        // Install the binary
        Platform::installBinary();

        // Get vendor directory
        $reflection = new \ReflectionClass(Platform::class);
        $getVendorDirMethod = $reflection->getMethod('getVendorDir');
        $getVendorDirMethod->setAccessible(true);

        $platform = new Platform();
        $vendorDir = $getVendorDirMethod->invoke($platform);

        // Assert that the binary directory is created
        $binaryDir = $vendorDir . '/bin';
        $this->assertDirectoryExists($binaryDir, "Binary directory was not created");

        // Assert that the binary file exists
        $binaryPath = Platform::getBinaryPath();
        $this->assertFileExists($binaryPath, "Binary file was not created during installation");

        // Assert that the binary is executable
        $this->assertTrue(is_executable($binaryPath), "Installed binary is not executable");

        // Check vendor/bin symlink or copy
        $vendorBinDir = $vendorDir . '/bin';
        $symlinkPath = $vendorBinDir . '/volt-test';
        if (PHP_OS_FAMILY === 'Windows') {
            $symlinkPath .= '.exe';
        }
        $this->assertTrue(
            file_exists($symlinkPath) || is_link($symlinkPath),
            "Symlink or copy in vendor/bin directory was not created"
        );
    }

    public function testUnsupportedPlatform()
    {
        // Expect a RuntimeException when trying to install an unsupported binary
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Platform unsupported-platform is not supported");

        // Use the custom platform class to trigger the exception
        Platform::installBinary(true);
    }

    public function testVendorDirectoryLocation()
    {
        $reflection = new \ReflectionClass(Platform::class);
        $vendorMethod = $reflection->getMethod('getVendorDir');
        $vendorMethod->setAccessible(true);

        $platform = new Platform();
        $vendorDir = $vendorMethod->invoke($platform);

        $this->assertDirectoryExists($vendorDir, "Vendor directory could not be located");
        $this->assertStringEndsWith('/vendor', $vendorDir, "Incorrect vendor directory path");
    }

    protected function tearDown(): void
    {
        $this->cleanupTestEnvironment();
    }
}
