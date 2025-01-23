<?php

namespace VoltTest;

class Platform
{
    private const BINARY_NAME = 'volt-test';

    private const CURRENT_VERSION = 'v0.0.1-beta';
    private const BASE_DOWNLOAD_URL = 'https://github.com/volt-test/binaries/releases/download';
    private const SUPPORTED_PLATFORMS = [
        'linux-amd64' => 'volt-test-linux-amd64',
        'darwin-amd64' => 'volt-test-darwin-amd64',
        'darwin-arm64' => 'volt-test-darwin-arm64',
        'windows-amd64' => 'volt-test-windows-amd64.exe'
    ];

    private static function getVendorDir(): string
    {
        // First try using Composer's environment variable
        if (getenv('COMPOSER_VENDOR_DIR')) {
            return rtrim(getenv('COMPOSER_VENDOR_DIR'), '/\\');
        }

        // Then try using Composer's home directory
        if (getenv('COMPOSER_HOME')) {
            $vendorDir = rtrim(getenv('COMPOSER_HOME'), '/\\') . '/vendor';
            if (is_dir($vendorDir)) {
                return $vendorDir;
            }
        }

        // Try to find vendor directory relative to current file
        $paths = [
            __DIR__ . '/../../../',           // From src/VoltTest to vendor
            __DIR__ . '/vendor/',             // Direct vendor subdirectory
            dirname(__DIR__, 2) . '/vendor/', // Two levels up
            dirname(__DIR__, 3) . '/vendor/', // Three levels up
            dirname(__DIR__) . '/vendor/',
        ];

        foreach ($paths as $path) {
            if (file_exists($path . 'autoload.php')) {
                return rtrim($path, '/\\');
            }
        }

        // If running as a Composer script, use the working directory
        if (getenv('COMPOSER')) {
            $path = getcwd() . '/vendor';
            if (is_dir($path)) {
                return $path;
            }
        }

        throw new \RuntimeException(
            'Could not locate Composer vendor directory. ' .
            'Please ensure you are installing through Composer.'
        );
    }

    private static function getBinaryDir(): string
    {
        return self::getVendorDir() . '/bin';
    }

    private static function getCurrentVersion(): string
    {
        return self::CURRENT_VERSION;
    }

    private static function detectPlatform($testing = false): string
    {
        if ($testing) {
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
        } elseif ($arch === 'arm64' || $arch === 'aarch64') {
            $arch = 'arm64';
        }

        return "$os-$arch";
    }

    public static function installBinary($testing = false): void
    {
        $platform = self::detectPlatform($testing);

        if (!array_key_exists($platform, self::SUPPORTED_PLATFORMS)) {
            throw new \RuntimeException("Platform $platform is not supported");
        }

        $version = self::getCurrentVersion();
        $binaryName = self::SUPPORTED_PLATFORMS[$platform];
        $downloadUrl = sprintf('%s/%s/%s', self::BASE_DOWNLOAD_URL, $version, $binaryName);

        $binDir = self::getBinaryDir();
        $targetFile = $binDir . '/' . self::BINARY_NAME;
        if (PHP_OS_FAMILY === 'Windows') {
            $targetFile .= '.exe';
        }

        if (!is_dir($binDir)) {
            if (!mkdir($binDir, 0755, true)) {
                throw new \RuntimeException("Failed to create directory: $binDir");
            }
        }

        echo "Downloading VoltTest binary $version for platform: $platform\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'volt-test-download-');
        if ($tempFile === false) {
            throw new \RuntimeException("Failed to create temporary file");
        }

        try {
            $ch = curl_init($downloadUrl);
            if ($ch === false) {
                throw new \RuntimeException("Failed to initialize cURL");
            }

            $fp = fopen($tempFile, 'w');
            if ($fp === false) {
                curl_close($ch);
                throw new \RuntimeException("Failed to open temporary file for writing");
            }

            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_FAILONERROR => true,
                CURLOPT_TIMEOUT => 300,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_HTTPHEADER => ['User-Agent: volt-test-php-sdk']
            ]);

            if (curl_exec($ch) === false) {
                throw new \RuntimeException("Download failed: " . curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode !== 200) {
                throw new \RuntimeException("HTTP request failed with status $httpCode");
            }

            curl_close($ch);
            fclose($fp);

            if (!file_exists($tempFile) || filesize($tempFile) === 0) {
                throw new \RuntimeException("Downloaded file is empty or missing");
            }

            if (!rename($tempFile, $targetFile)) {
                throw new \RuntimeException("Failed to move downloaded binary to: $targetFile");
            }

            if (!chmod($targetFile, 0755)) {
                throw new \RuntimeException("Failed to set executable permissions on binary");
            }

            file_put_contents($binDir . '/.volt-test-version', $version);

            echo "Successfully installed VoltTest binary $version to: $targetFile\n";
        } catch (\Exception $e) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            throw $e;
        }
    }

    public static function getBinaryPath(): string
    {
        $binDir = self::getBinaryDir();
        $binaryName = self::BINARY_NAME;
        if (PHP_OS_FAMILY === 'Windows') {
            $binaryName .= '.exe';
        }

        $binaryPath = $binDir . '/' . $binaryName;
        $versionFile = $binDir . '/.volt-test-version';

        $needsInstall = true;

        if (file_exists($binaryPath) && file_exists($versionFile)) {
            try {
                $currentVersion = trim(file_get_contents($versionFile));
                $latestVersion = self::getCurrentVersion();
                $needsInstall = $currentVersion !== $latestVersion;
            } catch (\Exception $e) {
                $needsInstall = true;
            }
        }

        if ($needsInstall) {
            self::installBinary();
        }

        return $binaryPath;
    }
}