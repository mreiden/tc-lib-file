<?php

declare(strict_types=1);

/**
 * FileTest.php
 *
 * @since     2015-07-28
 * @category  Library
 * @package   File
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2015-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-file
 *
 * This file is part of tc-lib-file software library.
 */

namespace Test;

use Com\Tecnick\File\Exception as FileException;
use Com\Tecnick\File\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * File Color class test
 *
 * @since     2015-07-28
 * @category  Library
 * @package   File
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2015-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-file
 */
class FileTest extends TestUtil
{
    /**
     * Port the local PHP built-in HTTP server is listening on (0 = not started).
     */
    private static int $serverPort = 0;

    /**
     * Process handle returned by proc_open() for the built-in server.
     *
     * @var resource|null
     */
    private static mixed $serverProcess = null;

    /**
     * Start a local PHP built-in HTTP server so that cURL tests can reach
     * a real HTTP endpoint without requiring external network access.
     */
    public static function setUpBeforeClass(): void
    {
        // Ensure FORCE_CURL is defined so that getUrlData() proceeds even
        // when allow_url_fopen is enabled in the test environment.
        if (!\defined('FORCE_CURL')) {
            \define('FORCE_CURL', true);
        }

        if (!\function_exists('curl_init')) {
            return;
        }

        // Find a free TCP port by binding to port 0 and reading the assignment.
        $errno = 0;
        $errstr = '';
        $sock = \stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($sock === false) {
            return;
        }

        $name = (string) \stream_socket_get_name($sock, false);
        \fclose($sock);
        $matches = [];
        \preg_match('/(\d+)$/', $name, $matches);
        self::$serverPort = (int) ($matches[1] ?? 0);

        if (self::$serverPort === 0) {
            return;
        }

        $docRoot = __DIR__ . '/http';
        $cmd = \sprintf('php -S 127.0.0.1:%d -t %s', self::$serverPort, \escapeshellarg($docRoot));

        $descriptors = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
        $serverPipes = [];
        $proc = \proc_open($cmd, $descriptors, $serverPipes);
        if ($proc === false) {
            self::$serverPort = 0;
            return;
        }

        foreach ($serverPipes as $pipe) {
            \fclose($pipe);
        }

        self::$serverProcess = $proc;

        // Wait until the server is accepting connections (up to 5 s).
        $ready = false;
        for ($i = 0; $i < 50; ++$i) {
            \set_error_handler(static fn(): bool => true);
            $conn = \fsockopen('127.0.0.1', self::$serverPort, $errno, $errstr, 0.1);
            \restore_error_handler();
            if ($conn !== false) {
                \fclose($conn);
                $ready = true;
                break;
            }

            \usleep(100_000);
        }

        if (!$ready) {
            \proc_terminate($proc);
            \proc_close($proc);
            self::$serverProcess = null;
            self::$serverPort = 0;
        }
    }

    /**
     * Shut down the local HTTP server started in setUpBeforeClass().
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$serverProcess !== null) {
            \proc_terminate(self::$serverProcess);
            \proc_close(self::$serverProcess);
            self::$serverProcess = null;
        }
    }

    protected function getTestObject(): File
    {
        return new File();
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testFopenLocal(): void
    {
        $file = new File([], 52_428_800, [], null, null, ['*']);
        $handle = $file->fopenLocal(__FILE__, 'r');
        $this->assertIsResource($handle);
        \fclose($handle);
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testFopenLocalNonLocal(): void
    {
        $file = $this->getTestObject();
        $this->expectException(FileException::class);
        $file->fopenLocal('http://www.example.com/test.txt', 'r');
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testFopenLocalMissing(): void
    {
        $file = $this->getTestObject();
        $this->expectException(FileException::class);
        $file->fopenLocal('/missing_error.txt', 'r');
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testFopenLocalOpenFailureAfterValidation(): void
    {
        $this->expectException(FileException::class);
        $file = new File([], 52_428_800, [], null, null, ['*']);
        $file->fopenLocal('/definitely-missing-' . \uniqid('', true) . '.txt', 'r');
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testFopenLocalDoubleDot(): void
    {
        $file = $this->getTestObject();
        $this->expectException(FileException::class);
        $file->fopenLocal('/tmp/invalid/../test.txt', 'r');
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testfReadInt(): void
    {
        $file = $this->getTestObject();
        $handle = \fopen(__FILE__, 'r');
        $this->assertNotFalse($handle);
        $res = $file->fReadInt($handle);
        // '<?ph' = 60 63 112 104 = 00111100 00111111 01110000 01101000 = 1010790504
        $this->assertEquals(1_010_790_504, $res);
        \fclose($handle);
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testfReadIntReadFailureException(): void
    {
        $file = $this->getTestObject();

        // Open stream in write-only mode to cause a read failure.
        $tmp = \tempnam(\sys_get_temp_dir(), 'tc');
        $this->assertNotFalse($tmp);
        $handle = \fopen($tmp, 'w');
        $this->assertNotFalse($handle);

        $this->expectException(FileException::class);
        // Reading a write-only handle causes exception.
        $file->fReadInt($handle);
        \fclose($handle);
        \unlink($tmp);
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testRfRead(): void
    {
        $file = $this->getTestObject();
        $handle = \fopen(\dirname(__DIR__) . '/src/File.php', 'rb');
        $this->assertNotFalse($handle);
        $res = $file->rfRead($handle, 2);
        $this->assertEquals('<?', $res);
        $res = $file->rfRead($handle, 3);
        $this->assertEquals('php', $res);
        \fclose($handle);
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testRfReadSlowDrip(): void
    {
        $numBytes = 4;
        $url = \sprintf(
            'https://httpbin.org/drip?duration=%d&numbytes=%d&code=200&delay=0.0',
            0.2 * $numBytes,
            $numBytes,
        );
        // Open url handle
        $file = $this->getTestObject();
        $handle = \fopen($url, 'rb');
        $this->assertNotFalse($handle);
        // Read from url
        $res = $file->rfRead($handle, $numBytes);
        $this->assertEquals($numBytes, \strlen($res));
        $this->assertEquals(\str_repeat('*', $numBytes), $res);
        // Close url handle
        \fclose($handle);
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testRfReadSlowDripFailedLastByte(): void
    {
        $numBytes = 4;
        $url = \sprintf(
            'https://httpbin.org/drip?duration=%d&numbytes=%d&code=200&delay=0.0',
            0.2 * $numBytes,
            $numBytes - 1,
        );

        $file = $this->getTestObject();
        $handle = \fopen($url, 'rb');
        $this->assertNotFalse($handle);

        // Should read numBytes-1
        $res = $file->rfRead($handle, $numBytes);
        $this->assertSame(\str_repeat('*', $numBytes - 1), $res);
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testRfReadException(): void
    {
        $file = $this->getTestObject();

        $this->expectException(FileException::class);
        $file->rfRead(null, 2);
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testRfReadClosedHandleException(): void
    {
        $this->expectException(FileException::class);
        $file = $this->getTestObject();
        $handle = \fopen(__FILE__, 'rb');
        // ensure static analyzers know fopen succeeded
        $this->assertNotFalse($handle);

        \fclose($handle);
        // handle is still typed resource by analyzers even after close
        $file->rfRead($handle, 1);
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testRfReadZeroLength(): void
    {
        $this->expectException(FileException::class);
        $file = $this->getTestObject();
        $handle = \fopen(__FILE__, 'rb');
        $this->assertNotFalse($handle);
        // length 0: the while-loop condition (0 < 0) is immediately false,
        // so $data stays empty and FileException is thrown.
        $rfm = new \ReflectionMethod($file, 'rfRead');
        $rfm->invoke($file, $handle, 0);
        \fclose($handle);
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testRfReadEofShorter(): void
    {
        $file = $this->getTestObject();
        $tmp = \tempnam(\sys_get_temp_dir(), 'tc');
        $this->assertNotFalse($tmp);
        \file_put_contents($tmp, 'xy');
        $handle = \fopen($tmp, 'rb');
        $this->assertNotFalse($handle);
        $res = $file->rfRead($handle, 10);
        $this->assertEquals('xy', $res);
        \fclose($handle);
        \unlink($tmp);
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testRfReadRecursiveBufferedStream(): void
    {
        if (!\in_array('tcreadpartial', \stream_get_wrappers(), true)) {
            \stream_wrapper_register('tcreadpartial', RecursiveReadStreamWrapper::class);
        }

        $file = new RecursiveReadFile();
        $handle = \fopen('tcreadpartial://buffered', 'rb');
        $this->assertNotFalse($handle);

        try {
            $res = $file->rfRead($handle, 5);
            $this->assertSame('abcde', $res);
        } finally {
            \fclose($handle);
            \stream_wrapper_unregister('tcreadpartial');
        }
    }

    #[Test]
    public function testHasUnreadBytes(): void
    {
        $file = $this->getTestObject();
        $handle = \fopen(\dirname(__DIR__) . '/src/File.php', 'rb');
        $this->assertNotFalse($handle);

        try {
            $this->assertSame('<?', \fread($handle, 2));

            $rfm = new \ReflectionMethod($file, 'hasUnreadBytes');
            $this->assertTrue($rfm->invoke($file, $handle) === true);
        } finally {
            \fclose($handle);
        }
    }

    /**
     * @param string                            $file      File path
     * @param array{string, array<int, string>} $expected  Expected result
     */
    #[Test]
    #[DataProvider('getAltFilePathsDataProvider')]
    public function testGetAltFilePaths(string $file, array $expected): void
    {
        $testObj = new File(['localhost']);
        $_SERVER['DOCUMENT_ROOT'] = '/var/www';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SCRIPT_URI'] = 'https://localhost/path/example.php';
        $alt = $testObj->getAltFilePaths($file);
        $this->assertEquals($expected, $alt);
    }

    /**
     * Data provider for testGetAltFilePaths
     *
     * @return array<array{string, array<int, string>}>
     */
    public static function getAltFilePathsDataProvider(): array
    {
        return [
            [
                'http://www.example.com/test.txt',
                [
                    0 => 'http://www.example.com/test.txt',
                ],
            ],
            [
                'https://localhost/path/test.txt',
                [
                    0 => 'https://localhost/path/test.txt',
                    3 => '/var/www/path/test.txt',
                ],
            ],
            [
                '//www.example.com/space test.txt',
                [
                    0 => '//www.example.com/space test.txt',
                    2 => 'https://www.example.com/space%20test.txt',
                ],
            ],
            [
                '/path/test.txt',
                [
                    0 => '/path/test.txt',
                    1 => '/var/www/path/test.txt',
                    4 => 'https://localhost/path/test.txt',
                ],
            ],
            [
                'https://localhost/path/test.php?a=0&b=1&amp;c=2;&amp;d="a+b%20c"',
                [
                    0 => 'https://localhost/path/test.php?a=0&b=1&amp;c=2;&amp;d="a+b%20c"',
                    2 => 'https://localhost/path/test.php?a=0&b=1&c=2;&d="a+b%20c"',
                ],
            ],
            [
                'path/test.txt',
                [
                    0 => 'path/test.txt',
                    4 => 'https://localhost/path/test.txt',
                ],
            ],
        ];
    }

    /**
     * Ensure getAltUrlFromPath returns the input when SCRIPT_URI cannot be parsed
     * (covers the guarded return on line 363 of File.php).
     */
    #[Test]
    public function testGetAltUrlFromPathUnparseableUri(): void
    {
        $testObj = $this->getTestObject();

        // set SCRIPT_URI to something parse_url will handle but without scheme/host
        $_SERVER['SCRIPT_URI'] = 'not-a-url';

        $rfm = new \ReflectionMethod($testObj, 'getAltUrlFromPath');
        $input = 'some/path.txt';
        /** @var mixed $result */
        $result = $rfm->invoke($testObj, $input);
        $this->assertSame($input, $result, 'Expected original path when SCRIPT_URI lacks scheme/host');
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testExceptionNoHost(): void
    {
        // Use scheme other than http, https, ftp and an empty host
        $url = 'gopher:///non-existent';
        $_SERVER['SCRIPT_URI'] = $url;

        $file = $this->getTestObject();
        $this->expectException(FileException::class);
        $file->fileGetContents($url);
    }

    #[Test]
    public function testGetAltUrlFromPathSpoofedScriptUriRejected(): void
    {
        $testObj = $this->getTestObject();
        // No allowedHosts configured → deny SCRIPT_URI host.
        $_SERVER['SCRIPT_URI'] = 'https://attacker.internal/app/script.php';

        $rfm = new \ReflectionMethod($testObj, 'getAltUrlFromPath');
        $input = 'data/file.txt';
        /** @var mixed $result */
        $result = $rfm->invoke($testObj, $input);
        $this->assertSame($input, $result, 'Spoofed SCRIPT_URI host must not be used to build a URL');
    }

    #[Test]
    public function testGetAltUrlFromPathAllowlistedScriptUriAccepted(): void
    {
        $testObj = new File(['myapp.example.com']);
        $_SERVER['SCRIPT_URI'] = 'https://myapp.example.com/app/script.php';

        $rfm = new \ReflectionMethod($testObj, 'getAltUrlFromPath');
        /** @var mixed $result */
        $result = $rfm->invoke($testObj, 'data/file.txt');
        $this->assertSame('https://myapp.example.com/data/file.txt', $result);
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testFileGetContentsMissingException(): void
    {
        $file = $this->getTestObject();
        $this->expectException(FileException::class);
        $file->fileGetContents('missing.txt');
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testFileGetContentsDoubleDotException(): void
    {
        $file = $this->getTestObject();
        $this->expectException(FileException::class);
        $file->fileGetContents('/tmp/something/../test.txt');
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testFileGetContentsForbiddenProtocolException(): void
    {
        $file = $this->getTestObject();
        $this->expectException(FileException::class);
        $file->fileGetContents('phar://test.txt');
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testFileGetContents(): void
    {
        $file = new File([], 52_428_800, [], null, null, ['*']);
        $res = $file->fileGetContents(__FILE__);
        $this->assertEquals('<?php', \substr($res, 0, 5));
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testFileGetContentsCurl(): void
    {
        $file = $this->getTestObject();
        $this->expectException(FileException::class);
        $file->fileGetContents('http://www.example.com/test.txt');
    }

    #[Test]
    public function testHasDoubleDots(): void
    {
        $file = new class() extends File {
            public function hasDoubleDotsProxy(string $path): bool
            {
                return $this->hasDoubleDots($path);
            }
        };

        $res = $file->hasDoubleDotsProxy('/tmp/../test.txt');
        $this->assertTrue($res);
        $res = $file->hasDoubleDotsProxy('/tmp/test.txt');
        $this->assertFalse($res);
    }

    #[Test]
    public function testMaxRemoteSizeDefault(): void
    {
        $file = $this->getTestObject();
        $this->assertSame(52_428_800, $file->getMaxRemoteSize());
    }

    #[Test]
    public function testMaxRemoteSizeConfigurable(): void
    {
        $file = $this->getTestObject();
        $file->setMaxRemoteSize(1_048_576); // 1MB
        $this->assertSame(1_048_576, $file->getMaxRemoteSize());
    }

    #[Test]
    public function testSetAllowedHostsIsFluentAndUsedByValidator(): void
    {
        $file = new class() extends File {
            public function validateHostProxy(string $host): bool
            {
                return $this->isValidHost($host);
            }
        };

        $ret = $file->setAllowedHosts(['trusted.example']);
        $this->assertSame($file, $ret);

        $this->assertTrue($file->validateHostProxy('trusted.example'));
        $this->assertFalse($file->validateHostProxy('other.example'));
        $this->assertFalse($file->validateHostProxy(''));
    }

    #[Test]
    public function testSetAllowedPathsIsFluentAndUsedByValidator(): void
    {
        $file = new File();

        $tmpDir = \sys_get_temp_dir() . '/tc-lib-file-' . \uniqid('', true);
        $this->assertTrue(\mkdir($tmpDir, 0o755, true));

        $allowedPath = $tmpDir . '/file.txt';
        $this->assertSame(\file_put_contents($allowedPath, 'ok'), 2);

        try {
            $ret = $file->setAllowedPaths([$tmpDir]);
            $this->assertSame($file, $ret);

            $this->assertTrue($file->isValidFile($allowedPath));

            $blockedPath = \sys_get_temp_dir() . '/blocked-' . \uniqid('', true) . '.txt';
            $this->assertFalse($file->isValidFile($blockedPath));
        } finally {
            if (\is_file($allowedPath)) {
                \unlink($allowedPath);
            }

            if (\is_dir($tmpDir)) {
                \rmdir($tmpDir);
            }
        }
    }

    #[Test]
    public function testResolveLocalPathUsesExplicitBaseDirs(): void
    {
        $file = new File();

        $baseDir = \sys_get_temp_dir() . '/tc-lib-file-' . \uniqid('', true);
        $imagesDir = $baseDir . '/images';
        $imagePath = $imagesDir . '/tcpdf_logo.jpg';

        $this->assertTrue(\mkdir($imagesDir, 0o755, true));
        $this->assertSame(2, \file_put_contents($imagePath, 'ok'));

        try {
            $resolved = $file->resolveLocalPath('images/tcpdf_logo.jpg', [$baseDir]);

            $this->assertSame(\realpath($imagePath), $resolved);
        } finally {
            if (\is_file($imagePath)) {
                \unlink($imagePath);
            }
            if (\is_dir($imagesDir)) {
                \rmdir($imagesDir);
            }
            if (\is_dir($baseDir)) {
                \rmdir($baseDir);
            }
        }
    }

    #[Test]
    public function testResolveLocalPathLeavesSchemedInputsUntouched(): void
    {
        $file = new File();
        $url = 'https://example.com/logo.jpg';

        $this->assertSame($url, $file->resolveLocalPath($url, [__DIR__]));
    }

    // -------------------------------------------------------------------------
    // Issue 1: CURLOPT_REDIR_PROTOCOLS is present in CURLOPT_DEFAULT
    // -------------------------------------------------------------------------

    #[Test]
    public function testCurlOptRedirProtocolsPresent(): void
    {
        $ref = new \ReflectionClassConstant(File::class, 'CURLOPT_DEFAULT');
        /** @var array<int, mixed> $opts */
        $opts = $ref->getValue();
        $this->assertArrayHasKey(\CURLOPT_REDIR_PROTOCOLS, $opts);
        // Only HTTP/HTTPS allowed for redirects — no FTP.
        $this->assertSame(\CURLPROTO_HTTPS | \CURLPROTO_HTTP, $opts[\CURLOPT_REDIR_PROTOCOLS] ?? null);
    }

    // -------------------------------------------------------------------------
    // Issue 7: TLS verification flags pinned in fixed options
    // -------------------------------------------------------------------------

    #[Test]
    public function testSslVerificationPinnedInFixedOptions(): void
    {
        $ref = new \ReflectionClassConstant(File::class, 'CURLOPT_FIXED');
        /** @var array<int, mixed> $opts */
        $opts = $ref->getValue();
        // SSL verification must be pinned in FIXED to prevent override
        $this->assertArrayHasKey(\CURLOPT_SSL_VERIFYHOST, $opts);
        $this->assertArrayHasKey(\CURLOPT_SSL_VERIFYPEER, $opts);
        // Verify strict verification is enforced
        $this->assertSame(2, $opts[\CURLOPT_SSL_VERIFYHOST] ?? null);
        $this->assertTrue(($opts[\CURLOPT_SSL_VERIFYPEER] ?? null) === true);
    }

    #[Test]
    public function testSslVerificationCannotBeOverriddenByCustomOptions(): void
    {
        $testObj = $this->getTestObject();
        // Set custom curl options that try to disable SSL verification
        $testObj->setCurlOpts([
            \CURLOPT_SSL_VERIFYPEER => false,
            \CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        // Get the fixed options to verify they are unaffected
        $refProperty = new \ReflectionProperty($testObj, 'fixedCurlOpts');
        /** @var array<int, mixed> $fixedOpts */
        $fixedOpts = $refProperty->getValue($testObj);

        // Verify fixed options still have strict verification enabled
        // (they should override any custom options due to merge order in getUrlData)
        $this->assertSame(2, $fixedOpts[\CURLOPT_SSL_VERIFYHOST] ?? null);
        $this->assertTrue(($fixedOpts[\CURLOPT_SSL_VERIFYPEER] ?? null) === true);
    }

    // -------------------------------------------------------------------------
    // Issue 3: validateHost() / HTTP_HOST SSRF protection
    // -------------------------------------------------------------------------

    #[Test]
    public function testSpoofedHttpHostIsRejectedByDefault(): void
    {
        $testObj = $this->getTestObject();
        // No allowedHosts configured → every host is denied.
        $_SERVER['HTTP_HOST'] = 'evil.internal';
        $_SERVER['HTTPS'] = 'on';

        $rfm = new \ReflectionMethod($testObj, 'getAltMissingUrlProtocol');
        $input = '//evil.internal/steal';
        /** @var mixed $result */
        $result = $rfm->invoke($testObj, $input);
        // Without a trusted host the path must come back unchanged (decoded only).
        $this->assertSame(
            \htmlspecialchars_decode($input),
            $result,
            'Spoofed HTTP_HOST must not be used to build a URL',
        );
    }

    #[Test]
    public function testAllowlistedHttpHostIsAccepted(): void
    {
        $testObj = new File(['myapp.example.com']);
        $_SERVER['HTTP_HOST'] = 'myapp.example.com';
        $_SERVER['HTTPS'] = 'on';

        $rfm = new \ReflectionMethod($testObj, 'getAltMissingUrlProtocol');
        /** @var mixed $result */
        $result = $rfm->invoke($testObj, '//myapp.example.com/path/file.txt');
        $this->assertSame('https://myapp.example.com/path/file.txt', $result);
    }

    #[Test]
    public function testGetAltPathFromUrlSpoofedHostRejected(): void
    {
        $testObj = $this->getTestObject();
        // No allowedHosts → deny.
        $_SERVER['HTTP_HOST'] = 'attacker.internal';
        $_SERVER['DOCUMENT_ROOT'] = '/var/www';
        $_SERVER['HTTPS'] = 'off';

        $rfm = new \ReflectionMethod($testObj, 'getAltPathFromUrl');
        $url = 'http://attacker.internal/secret';
        /** @var mixed $result */
        $result = $rfm->invoke($testObj, $url);
        $this->assertSame($url, $result, 'Spoofed host must not be used to build a local path');
    }

    #[Test]
    public function testValidatePathAcceptsAllowedPrefix(): void
    {
        $baseDir = \sys_get_temp_dir() . '/tc-lib-file-' . \uniqid('', true);
        $this->assertTrue(\mkdir($baseDir, 0o755, true));

        $testObj = new File([], 52_428_800, [], null, null, [$baseDir]);

        try {
            $path = $baseDir . '/assets/file.txt';
            $this->assertTrue($testObj->isValidFile($path));
        } finally {
            if (\is_dir($baseDir . '/assets')) {
                \rmdir($baseDir . '/assets');
            }
            if (\is_dir($baseDir)) {
                \rmdir($baseDir);
            }
        }
    }

    #[Test]
    public function testValidatePathRejectsNonMatchingPrefix(): void
    {
        $baseDir = \sys_get_temp_dir() . '/tc-lib-file-' . \uniqid('', true);
        $this->assertTrue(\mkdir($baseDir, 0o755, true));

        $testObj = new File([], 52_428_800, [], null, null, [$baseDir]);

        try {
            $path = \sys_get_temp_dir() . '/tc-lib-file-' . \uniqid('', true) . '/file.txt';
            $this->assertFalse($testObj->isValidFile($path));
        } finally {
            if (\is_dir($baseDir)) {
                \rmdir($baseDir);
            }
        }
    }

    #[Test]
    public function testValidatePathRejectsSiblingPrefixBypass(): void
    {
        $baseDir = \sys_get_temp_dir() . '/tc-lib-file-' . \uniqid('', true);
        $this->assertTrue(\mkdir($baseDir, 0o755, true));

        $testObj = new File([], 52_428_800, [], null, null, [$baseDir]);

        try {
            $path = $baseDir . '_evil/secret.txt';
            $this->assertFalse($testObj->isValidFile($path));
        } finally {
            if (\is_dir($baseDir)) {
                \rmdir($baseDir);
            }
        }
    }

    #[Test]
    public function testIsPathWithinAllowedRootsSkipsEmptyRoots(): void
    {
        $testObj = new class() extends File {
            /**
             * @param array<int, string> $roots
             */
            public function isPathWithinAllowedRootsProxy(string $path, array $roots): bool
            {
                return $this->isPathWithinAllowedRoots($path, $roots);
            }
        };

        $this->assertTrue($testObj->isPathWithinAllowedRootsProxy('/var/www/app/file.txt', ['', '/', '/var/www']));
    }

    #[Test]
    public function testAllowedPathsAreNormalizedInConstructorForWindowsPaths(): void
    {
        $testObj = new class([], 52_428_800, [], null, null, [' C:\\Trusted\\Base\\ ', 'D:', '']) extends
            File {
            public function isPathAllowedProxy(string $path): bool
            {
                return $this->isPathWithinAllowedRoots($path, $this->allowedPaths);
            }

            /**
             * @return array<int, string>
             */
            public function getAllowedPathsProxy(): array
            {
                return \array_values($this->allowedPaths);
            }
        };

        $this->assertSame(['c:/Trusted/Base', 'd:'], $testObj->getAllowedPathsProxy());
        $this->assertTrue($testObj->isPathAllowedProxy('c:/Trusted/Base/file.txt'));
        $this->assertTrue($testObj->isPathAllowedProxy('d:/folder/file.txt'));
    }

    #[Test]
    public function testAllowedPathsAreNormalizedInSetterForWindowsPaths(): void
    {
        $testObj = new class() extends File {
            public function isPathAllowedProxy(string $path): bool
            {
                return $this->isPathWithinAllowedRoots($path, $this->allowedPaths);
            }

            /**
             * @return array<int, string>
             */
            public function getAllowedPathsProxy(): array
            {
                return \array_values($this->allowedPaths);
            }
        };

        $testObj->setAllowedPaths([' E:\\Share\\Root\\ ', 'E:\\Share\\Root\\']);

        $this->assertSame(['e:/Share/Root'], $testObj->getAllowedPathsProxy());
        $this->assertTrue($testObj->isPathAllowedProxy('e:/Share/Root/file.txt'));
        $this->assertFalse($testObj->isPathAllowedProxy('e:/Share/Root_evil/file.txt'));
    }

    #[Test]
    public function testValidatePathReturnsFalseWhenNearestParentCannotBeResolved(): void
    {
        $testObj = new File([], 52_428_800, [], null, null, ['foo']);

        $file = 'foo';
        $this->assertFalse($testObj->isValidFile($file));
    }

    #[Test]
    public function testValidatePathRejectsSymlinkEscape(): void
    {
        if (!\function_exists('symlink')) {
            $this->markTestSkipped('symlink is not available in this environment');
        }

        $withoutWarnings = static function (callable $callback): mixed {
            \set_error_handler(static fn(): bool => true, \E_WARNING | \E_NOTICE | \E_USER_WARNING | \E_USER_NOTICE);

            try {
                return $callback();
            } finally {
                \restore_error_handler();
            }
        };

        $base = \sys_get_temp_dir() . '/tcfile_' . \uniqid('', true);
        $allowedDir = $base . '/allowed';
        \mkdir($allowedDir, 0o777, true);
        $link = $allowedDir . '/passwd-link';

        if (!$withoutWarnings(static fn(): bool => \symlink('/etc/passwd', $link))) {
            $this->markTestSkipped('unable to create symlink in this environment');
        }

        $testObj = new File([], 52_428_800, [], null, null, [$base]);

        try {
            $this->assertFalse($testObj->isValidFile($link));
        } finally {
            if (\is_link($link) || \file_exists($link)) {
                $withoutWarnings(static fn(): bool => \unlink($link));
            }

            if (\is_dir($allowedDir)) {
                $withoutWarnings(static fn(): bool => \rmdir($allowedDir));
            }

            if (\is_dir($base)) {
                $withoutWarnings(static fn(): bool => \rmdir($base));
            }
        }
    }

    #[Test]
    public function testValidatePathRejectsSymlinkDirectoryEscapeForMissingTarget(): void
    {
        if (!\function_exists('symlink')) {
            $this->markTestSkipped('symlink is not available in this environment');
        }

        $withoutWarnings = static function (callable $callback): mixed {
            \set_error_handler(static fn(): bool => true, \E_WARNING | \E_NOTICE | \E_USER_WARNING | \E_USER_NOTICE);

            try {
                return $callback();
            } finally {
                \restore_error_handler();
            }
        };

        $base = \sys_get_temp_dir() . '/tcfile_' . \uniqid('', true);
        $allowedDir = $base . '/allowed';
        $outsideDir = \sys_get_temp_dir() . '/tcfile_outside_' . \uniqid('', true);
        \mkdir($allowedDir, 0o777, true);
        \mkdir($outsideDir, 0o777, true);

        $link = $allowedDir . '/escape-link';
        if (!$withoutWarnings(static fn(): bool => \symlink($outsideDir, $link))) {
            $this->markTestSkipped('unable to create symlink in this environment');
        }

        $testObj = new File([], 52_428_800, [], null, null, [$base]);
        $target = $link . '/new-file.txt';

        try {
            $this->assertFalse($testObj->isValidFile($target));
        } finally {
            if (\is_link($link) || \file_exists($link)) {
                $withoutWarnings(static fn(): bool => \unlink($link));
            }

            if (\is_dir($outsideDir)) {
                $withoutWarnings(static fn(): bool => \rmdir($outsideDir));
            }

            if (\is_dir($allowedDir)) {
                $withoutWarnings(static fn(): bool => \rmdir($allowedDir));
            }

            if (\is_dir($base)) {
                $withoutWarnings(static fn(): bool => \rmdir($base));
            }
        }
    }

    #[Test]
    public function testIsValidUrlReturnsFalseWhenParseFails(): void
    {
        $testObj = new File(['localhost']);
        $url = 'http://:\\';

        $this->assertFalse($testObj->isValidURL($url));
    }

    #[Test]
    public function testIsValidUrlReturnsFalseWhenHostMissing(): void
    {
        $testObj = new File(['localhost']);
        $url = 'http:/path/without/host';

        $this->assertFalse($testObj->isValidURL($url));
    }

    #[Test]
    public function testIsValidUrlReturnsFalseWhenTrimmedUrlIsEmpty(): void
    {
        $testObj = new File(['localhost']);
        $url = " \t\n\r ";

        $this->assertFalse($testObj->isValidURL($url));
    }

    #[Test]
    public function testValidatePathRejectsNonFileScheme(): void
    {
        $file = new File([], 52_428_800, [], null, null, ['*']);

        $ftpPath = 'ftp://example.com/file.txt';
        $this->assertFalse($file->isValidFile($ftpPath));

        $pharPath = 'phar://archive/file.txt';
        $this->assertFalse($file->isValidFile($pharPath));

        $httpPath = 'http://www.example.com/file.txt';
        $this->assertFalse($file->isValidFile($httpPath));

        $localPath = './some/file.txt';
        $this->assertTrue($file->isValidFile($localPath));

        $fileUrl = 'file://some/file.txt';
        $this->assertTrue($file->isValidFile($fileUrl));

        // URL schemes are case-insensitive per RFC 3986
        $fileUrl = 'FILE://some/file.txt';
        $this->assertTrue($file->isValidFile($fileUrl));
    }

    #[Test]
    public function testValidatePathRejectsEmptyFileUrlPathEvenWithWildcardTrust(): void
    {
        $file = new File([], 52_428_800, [], null, null, ['*']);

        $emptyFileUrl = 'file://   ';
        $this->assertFalse($file->isValidFile($emptyFileUrl));
    }

    // -------------------------------------------------------------------------
    // Issue 8: iterative rfRead — single-byte chunk delivery
    // -------------------------------------------------------------------------

    /**
     * @throws FileException
     */
    #[Test]
    public function testRfReadSingleByteChunks(): void
    {
        $wrapperName = 'tcsinglebyte';
        if (!\in_array($wrapperName, \stream_get_wrappers(), true)) {
            \stream_wrapper_register($wrapperName, SingleByteStreamWrapper::class);
        }

        $file = $this->getTestObject();
        $handle = \fopen($wrapperName . '://data', 'rb');
        $this->assertNotFalse($handle);

        try {
            $res = $file->rfRead($handle, 4);
            $this->assertSame('abcd', $res);
        } finally {
            \fclose($handle);
            \stream_wrapper_unregister($wrapperName);
        }
    }

    // -------------------------------------------------------------------------
    // rfRead inner break: fread returns '' before feof signals end-of-stream
    // -------------------------------------------------------------------------

    /**
     * @throws FileException
     */
    #[Test]
    public function testRfReadBreakOnEmptyChunk(): void
    {
        $wrapperName = 'tcemptyread';
        if (!\in_array($wrapperName, \stream_get_wrappers(), true)) {
            \stream_wrapper_register($wrapperName, EmptyReadStreamWrapper::class);
        }

        $file = $this->getTestObject();
        $handle = \fopen($wrapperName . '://data', 'rb');
        $this->assertNotFalse($handle);

        try {
            // The wrapper returns 'ab' on the first read then '' forever while
            // stream_eof() never returns true, so rfRead() hits the inner break
            // (File.php line 194) after the second fread() call.
            $res = $file->rfRead($handle, 10);
            $this->assertSame('ab', $res);
        } finally {
            \fclose($handle);
            \stream_wrapper_unregister($wrapperName);
        }
    }

    // -------------------------------------------------------------------------
    // Progress callback direct-invocation tests (cURL size-limit logic)
    // -------------------------------------------------------------------------

    #[Test]
    public function testProgressCallbackReturnsZeroBelowLimit(): void
    {
        $file = $this->getTestObject();
        $file->setMaxRemoteSize(100);

        $rfm = new \ReflectionMethod($file, 'createProgressCallback');

        $bytesRead = 0;
        $args = [&$bytesRead];
        /** @var callable $callback */
        $callback = $rfm->invokeArgs($file, $args);

        // no remote interaction; just a valid CurlHandle
        $ch = \curl_init();

        // 50 bytes downloaded — well below the 100-byte limit → return 0
        /** @var mixed $result */
        $result = $callback($ch, 50, 50, 0, 0);
        $this->assertSame(0, $result);
    }

    #[Test]
    public function testProgressCallbackReturnsAbortSignalAboveLimit(): void
    {
        $file = $this->getTestObject();
        $file->setMaxRemoteSize(100);

        $rfm = new \ReflectionMethod($file, 'createProgressCallback');

        $bytesRead = 0;
        $args = [&$bytesRead];
        /** @var callable $callback */
        $callback = $rfm->invokeArgs($file, $args);

        // no remote interaction; just a valid CurlHandle
        $ch = \curl_init();

        // 200 bytes downloaded — exceeds the 100-byte limit → return 1 (abort)
        /** @var mixed $result */
        $result = $callback($ch, 200, 200, 0, 0);
        $this->assertSame(1, $result);
    }

    #[Test]
    public function testBuildRedirectUrlCoversUnsupportedAndRelativeForms(): void
    {
        $file = $this->getTestObject();

        $buildRedirectUrl = \Closure::bind(
            static fn(
                File $obj,
                string $location,
                string $baseUrl,
            ): string|false => $obj->buildRedirectUrl($location, $baseUrl),
            null,
            File::class,
        );

        $this->assertFalse($buildRedirectUrl($file, '   ', 'https://example.test/base'));
        $this->assertFalse($buildRedirectUrl($file, '/next', 'http://:\\'));
        $this->assertFalse($buildRedirectUrl($file, '/next', 'ftp://example.test/base'));
        $this->assertSame('https://cdn.example.test/file.txt', $buildRedirectUrl(
            $file,
            '//cdn.example.test/file.txt',
            'https://example.test/base',
        ));
        $this->assertSame('https://example.test/path/next.txt', $buildRedirectUrl(
            $file,
            'next.txt',
            'https://example.test/path/current.php',
        ));
    }

    #[Test]
    public function testRedirectValidationCallbackRejectsEmptyAndNonCurlLocationHeaders(): void
    {
        $file = new File(['allowed.example']);

        $rfm = new \ReflectionMethod($file, 'createRedirectValidationCallback');

        $invalidRedirect = false;
        $args = [&$invalidRedirect, 'https://allowed.example/start'];
        /** @var callable $callback */
        $callback = $rfm->invokeArgs($file, $args);
        $this->assertSame(0, $callback(null, "Location:   \r\n"));

        $invalidRedirect = false;
        $args = [&$invalidRedirect, 'https://allowed.example/start'];
        /** @var callable $callback */
        $callback = $rfm->invokeArgs($file, $args);
        $this->assertSame(0, $callback(null, "Location: /next\r\n"));
    }

    // -------------------------------------------------------------------------
    // Local HTTP server tests — cURL size-limit enforcement and return value
    // -------------------------------------------------------------------------

    /**
     * @throws FileException
     */
    #[Test]
    public function testGetUrlDataWithValidRedirectWhenMaxRedirsEnabled(): void
    {
        if (self::$serverPort === 0 || !\function_exists('curl_init')) {
            $this->markTestSkipped('Local HTTP server not available');
        }

        if ((string) \ini_get('open_basedir') !== '') {
            $this->markTestSkipped('Redirect-follow tests require open_basedir to be disabled');
        }

        $file = new File(['127.0.0.1']);
        $file->setCurlOpts([\CURLOPT_MAXREDIRS => 3]);

        $result = $file->getUrlData('http://127.0.0.1:' . self::$serverPort . '/redirect.php?to=/empty.php');
        $this->assertSame('', $result);
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testGetUrlDataReturnsFalseOnInvalidRedirectWhenMaxRedirsEnabled(): void
    {
        if (self::$serverPort === 0 || !\function_exists('curl_init')) {
            $this->markTestSkipped('Local HTTP server not available');
        }

        if ((string) \ini_get('open_basedir') !== '') {
            $this->markTestSkipped('Redirect-follow tests require open_basedir to be disabled');
        }

        $file = new File(['127.0.0.1']);
        $file->setCurlOpts([\CURLOPT_MAXREDIRS => 3]);

        $result = $file->getUrlData('http://127.0.0.1:' . self::$serverPort . '/redirect.php?to=http://example.com/');
        $this->assertFalse($result);
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testGetUrlDataSizeExceeded(): void
    {
        if (self::$serverPort === 0 || !\function_exists('curl_init')) {
            $this->markTestSkipped('Local HTTP server not available');
        }

        $file = new File(['127.0.0.1']);
        // Set a very small limit so the 1 000-byte response from large.php
        // triggers CURLE_ABORTED_BY_CALLBACK (errno 42).
        $file->setMaxRemoteSize(10);

        $this->expectException(FileException::class);
        $file->getUrlData('http://127.0.0.1:' . self::$serverPort . '/large.php');
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testGetUrlDataReturnTrue(): void
    {
        if (self::$serverPort === 0 || !\function_exists('curl_init')) {
            $this->markTestSkipped('Local HTTP server not available');
        }

        // Create a File instance with no fixed cURL options so that
        // CURLOPT_RETURNTRANSFER is not set.  curl_exec() then returns true
        // on success, exercising the `$ret === true ? '' : $ret` branch.
        $file = new File(['127.0.0.1'], 52_428_800, [], [], []);

        \ob_start();
        $result = $file->getUrlData('http://127.0.0.1:' . self::$serverPort . '/empty.php');
        \ob_end_clean();

        $this->assertSame('', $result);
    }

    /**
     * @throws FileException
     */
    #[Test]
    public function testGetUrlDataCurlExecFailureReturnsFalse(): void
    {
        if (!\function_exists('curl_init')) {
            $this->markTestSkipped('cURL extension not available');
        }

        $file = new File(['127.0.0.1']);
        $result = $file->getUrlData('http://127.0.0.1:1/unreachable.txt');
        $this->assertFalse($result);
    }

}
