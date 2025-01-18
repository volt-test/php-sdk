<?php

namespace VoltTest;

class Platform
{
    private const BINARY_NAME = 'volt-test';
    private const SUPPORTED_PLATFORMS = [
        'linux-amd64' => 'linux-amd64/volt-test',
        'darwin-amd64' => 'darwin-amd64/volt-test',
        'darwin-arm64' => 'darwin-arm64/volt-test',
        'windows-amd64' => 'windows-amd64/volt-test.exe',
        'windows-AMD64' => 'windows-amd64/volt-test.exe',
    ];

    public static function installBinary($testing = false): void
    {
        $platform = self::detectPlatform($testing);

        if (! array_key_exists($platform, self::SUPPORTED_PLATFORMS)) {
            throw new \RuntimeException("Platform $platform is not supported");
        }

        $sourceFile = __DIR__ . '/../bin/platforms/' . self::SUPPORTED_PLATFORMS[$platform];
        $targetDir = self::getBinaryDir();
        $targetFile = $targetDir . '/' . basename(self::SUPPORTED_PLATFORMS[$platform]);

        if (! file_exists($sourceFile)) {
            throw new \RuntimeException("Binary not found for platform: $platform");
        }

        if (! is_dir($targetDir)) {
            if (! mkdir($targetDir, 0755, true)) {
                throw new \RuntimeException("Failed to create directory: $targetDir");
            }
        }

        if (! copy($sourceFile, $targetFile)) {
            throw new \RuntimeException("Failed to copy binary to: $targetFile");
        }

        chmod($targetFile, 0755);

        // Create symlink in vendor/bin
        $vendorBinDir = self::getVendorBinDir();
        if (! is_dir($vendorBinDir)) {
            if (! mkdir($vendorBinDir, 0755, true)) {
                throw new \RuntimeException("Failed to create vendor bin directory: $vendorBinDir");
            }
        }

        $symlinkPath = $vendorBinDir . '/' . self::BINARY_NAME;
        if (file_exists($symlinkPath)) {
            unlink($symlinkPath);
        }

        if (PHP_OS_FAMILY === 'Windows') {
            // Windows doesn't support symlinks by default, so we'll copy the file
            if (! copy($targetFile, $symlinkPath)) {
                throw new \RuntimeException("Failed to copy binary to vendor/bin: $symlinkPath");
            }
        } else {
            if (! symlink($targetFile, $symlinkPath)) {
                throw new \RuntimeException("Failed to create symlink in vendor/bin: $symlinkPath");
            }
        }

    }

    private static function detectPlatform($testing = false): string
    {
        if ($testing === true) {
            return 'unsupported-platform';
        }
        $os = strtolower(PHP_OS);
        $arch = php_uname('m');

        if (strpos($os, 'win') === 0) {
            $os = 'windows';
        } elseif (strpos($os, 'darwin') === 0) {
            $os = 'darwin';
        } elseif (strpos($os, 'linux') === 0) {
            $os = 'linux';
        }
        if ($arch === 'x86_64') {
            $arch = 'amd64';
        }

        return "$os-$arch";
    }

    private static function getBinaryDir(): string
    {
        return self::getVendorDir() . '/volt-test/bin';
    }

    private static function getVendorBinDir(): string
    {
        return self::getVendorDir() . '/bin';
    }

    private static function getVendorDir(): string
    {
        // Traverse up from current directory until we find vendor directory
        $dir = __DIR__;
        while ($dir !== '/' && ! is_dir($dir . '/vendor')) {
            $dir = dirname($dir);
        }

        if (! is_dir($dir . '/vendor')) {
            throw new \RuntimeException('Could not locate vendor directory');
        }

        return $dir . '/vendor';
    }

    public static function getBinaryPath(): string
    {
        return self::getBinaryDir() . '/' . basename(self::SUPPORTED_PLATFORMS[self::detectPlatform()]);
    }
}
