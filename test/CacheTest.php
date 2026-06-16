<?php

declare(strict_types=1);

/**
 * CacheTest.php
 *
 * @since     2011-05-23
 * @category  Library
 * @package   File
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-filecache
 *
 * This file is part of tc-lib-pdf-filecache software library.
 */

namespace Test;

use Com\Tecnick\File\Cache;
use Com\Tecnick\File\Exception as FileException;
use PHPUnit\Framework\Attributes\Test;
use Random\RandomException;

/**
 * Unit Test
 *
 * @since     2011-05-23
 * @category  Library
 * @package   File
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-filecache
 */
class CacheTest extends TestUtil
{
    /**
     * @throws FileException
     * @throws RandomException
     */
    protected function getTestObject(): Cache
    {
        return new Cache('1_2-a+B/c');
    }

    /*
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCacheDirFromConstant(): void
    {
        $cachePath = sys_get_temp_dir() . '/tcpdf-' . hash('SHA256', \random_bytes(1024)) . '/';
        \mkdir($cachePath, 0777, true);
        $cachePath = \realpath($cachePath) . '/';
        \define('K_PATH_CACHE', $cachePath);

        $this->assertSame($cachePath, K_PATH_CACHE);

        $cache = new Cache();
        $this->assertSame($cachePath, $cache->getCachePath());
        \rmdir($cachePath);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCacheDirNotAvailable(): void
    {
        $cachePath = sys_get_temp_dir() . '/tcpdf-' . hash('SHA256', \random_bytes(1024)) . '/';
        \mkdir($cachePath, 0777, true);
        $cachePath = \realpath($cachePath) . '/';
        \define('K_PATH_CACHE', $cachePath);
        \rmdir($cachePath);

        $this->assertSame($cachePath, K_PATH_CACHE);
        $this->expectException(FileException::class);
        new Cache();
    }
    */

    /**
     * @throws FileException
     * @throws RandomException
     */
    #[Test]
    public function testAutoPrefix(): void
    {
        $cache = new Cache();
        $this->assertNotEmpty($cache->getFilePrefix());
    }

    /**
     * @throws FileException
     * @throws RandomException
     */
    #[Test]
    public function testGetCachePath(): void
    {
        $cache = $this->getTestObject();
        $cachePath = $cache->getCachePath();

        $systemRoot = \realpath('/');
        if ($systemRoot === false) {
            throw new FileException("Cannot find realpath of '/'");
        }
        $this->assertNotSame(false, $systemRoot);
        $this->assertSame($systemRoot, \substr($cachePath, 0, \strlen($systemRoot)));
        $this->assertSame('/', \substr($cachePath, -1));

        $cache->setCachePath();
        $this->assertEquals($cachePath, $cache->getCachePath());

        // Test mandatory trailing slash added
        $path = \sys_get_temp_dir();
        $cache->setCachePath($path);
        $this->assertEquals($path . '/', $cache->getCachePath());

        // Test mandatory trailing slash not added if already exists
        $path .= '/';
        $cache->setCachePath($path);
        $this->assertEquals($path, $cache->getCachePath());
    }

    /**
     * @throws FileException
     * @throws RandomException
     */
    #[Test]
    public function testGetFilePrefix(): void
    {
        $cache = $this->getTestObject();
        $filePrefix = $cache->getFilePrefix();
        $this->assertEquals('_1_2-a-B_c_', $filePrefix);
    }

    /**
     * @throws FileException
     * @throws RandomException
     */
    #[Test]
    public function testGetNewFileName(): void
    {
        $cache = $this->getTestObject();

        // Test ability to get new file
        $val = $cache->getNewFileName('tst', '0123');
        $this->assertNotFalse($val);

        $this->assertMatchesRegularExpression('/_1_2-a-B_c_tst_0123_/', $val);
    }

    /**
     * @throws FileException
     * @throws RandomException
     */
    #[Test]
    public function testNormalizePathInvalid(): void
    {
        $cache = $this->getTestObject();

        $invalid = \sys_get_temp_dir() . '/nonexistent_' . \uniqid('', true);
        $this->assertFalse(\file_exists($invalid), 'Sanity check: path should not exist');

        // invoke protected normalizePath via reflection so we can test
        // the branch where realpath() returns false
        $ref = new \ReflectionMethod($cache, 'normalizePath');

        $invalid = \sys_get_temp_dir() . '/nonexistent_' . \uniqid('', true);
        $this->assertFalse(\file_exists($invalid), 'Sanity check: path should not exist');

        $this->assertSame('', $ref->invoke($cache, $invalid));
    }

    /**
     * @throws FileException
     * @throws RandomException
     */
    #[Test]
    public function testExceptionWindowsTooLongFileName(): void
    {
        $reflectionClass = new \ReflectionClass(Cache::class);

        $cache = $this->getTestObject();
        $reflectionClass->getProperty('isWindows')->setValue($cache, true);

        $lengthUsed = \strlen($cache->getCachePath() . $cache->getFilePrefix() . 'long__' . '.tmp');
        $this->expectException(FileException::class);
        $cache->getNewFileName('long', \str_repeat('x', 259 - $lengthUsed));
    }

    /**
     * @throws FileException
     * @throws RandomException
     */
    #[Test]
    public function testDelete(): void
    {
        $cache = $this->getTestObject();

        $idk = 0;
        /** @var array<int, string> $file */
        $file = [];
        for ($idx = 1; $idx <= 2; ++$idx) {
            for ($idy = 1; $idy <= 2; ++$idy) {
                $file[$idk] = $cache->getNewFileName((string)$idx, (string)$idy);
                $this->assertNotFalse($file[$idk]);
                \file_put_contents($file[$idk], '');
                $this->assertTrue(\file_exists($file[$idk]));
                ++$idk;
            }
        }

        // Test deleting a non-existent cache item (for code coverage)
        $cache->delete('5', '0');

        $f0 = $file[0] ?? '';
        $f1 = $file[1] ?? '';
        $f2 = $file[2] ?? '';
        $f3 = $file[3] ?? '';

        // delete a specific type/key pair
        $cache->delete('2', '1');
        $this->assertNotFalse($f2);
        $this->assertFalse(\file_exists($f2));

        // delete all entries for type "1"
        $cache->delete('1');
        $this->assertNotFalse($f0);
        $this->assertFalse(\file_exists($f0));
        $this->assertNotFalse($f1);
        $this->assertFalse(\file_exists($f1));
        $this->assertNotFalse($f3);
        $this->assertTrue(\file_exists($f3));

        // delete everything
        $cache->delete();
        $this->assertFalse(\file_exists($f3));
    }

    /**
     * @throws FileException
     * @throws RandomException
     */
    public function testKeyOnlyDeletesAll(): void
    {
        $cache = $this->getTestObject();
        $file = $cache->getNewFileName('foo', 'bar');
        $this->assertNotFalse($file);
        \file_put_contents($file, '');
        $this->assertTrue(\file_exists($file));

        // key-only call should treat as delete all
        $cache->delete(null, 'bar');
        $this->assertFalse(\file_exists($file));
    }

    /**
     * @throws FileException
     * @throws RandomException
     */
    public function testDeleteNonExistingPatterns(): void
    {
        $cache = $this->getTestObject();
        $file = $cache->getNewFileName('foo', 'bar');
        $this->assertNotFalse($file);
        \file_put_contents($file, '');
        $this->assertTrue(\file_exists($file));

        // deleting a type that does not exist should leave the file in place
        $cache->delete('no-such-type');
        $this->assertTrue(\file_exists($file));

        // deleting a non-existent key under existing type
        $cache->delete('foo', 'no-such-key');
        $this->assertTrue(\file_exists($file));

        \unlink($file);
    }

    /**
     * @throws FileException
     * @throws RandomException
     */
    public function testEachInstanceHasOwnPrefix(): void
    {
        // Each instance should have its own prefix
        $cache1 = new Cache('pfx1');
        $cache2 = new Cache('pfx2');

        $prefix1 = $cache1->getFilePrefix();
        $prefix2 = $cache2->getFilePrefix();

        // Prefixes should be different since each instance is independent
        $this->assertNotSame($prefix1, $prefix2);
        $this->assertStringContainsString('pfx1', $prefix1);
        $this->assertStringContainsString('pfx2', $prefix2);
    }

    /**
     * @throws FileException
     * @throws RandomException
     */
    public function testEachInstanceHasOwnCachePath(): void
    {
        $cache1 = new Cache();
        $path1 = $cache1->getCachePath();

        $tempdir = \sys_get_temp_dir() . '/cache_test_' . \uniqid();
        \mkdir($tempdir);

        $cache2 = new Cache();
        $cache2->setCachePath($tempdir);
        $path2 = $cache2->getCachePath();

        // Paths should be different for each instance
        $this->assertNotSame($path1, $path2);
        $this->assertStringContainsString('cache_test_', $path2);

        \rmdir($tempdir);
    }

    // -------------------------------------------------------------------------
    // Issue 4: glob-injection sanitization in delete()
    // -------------------------------------------------------------------------

    /**
     * @throws FileException
     * @throws RandomException
     */
    public function testDeleteGlobCharsInTypeSanitised(): void
    {
        $cache = new Cache('safepfx');

        // Create a real file we do NOT want deleted.
        $real = $cache->getNewFileName('safe', '1');
        \file_put_contents($real, '');
        $this->assertTrue(\file_exists($real));

        // Call delete() with glob metacharacters in $type — must not expand.
        $cache->delete('*', null);

        // The real file must still exist because '*' was stripped to '' and
        // the resulting pattern matched nothing (or only unrelated files).
        // If glob injection were possible every file would be gone.
        $this->assertTrue(\file_exists($real), 'Glob injection via $type must not delete unrelated files');
        \unlink($real);
    }

    /**
     * @throws FileException
     * @throws RandomException
     */
    public function testDeleteGlobCharsInKeySanitised(): void
    {
        $cache = new Cache('safepfx2');

        $real = $cache->getNewFileName('mytype', 'goodkey');
        \file_put_contents($real, '');
        $this->assertTrue(\file_exists($real));

        // Inject glob metacharacter in $key — must be stripped.
        $cache->delete('mytype', '?');

        $this->assertTrue(\file_exists($real), 'Glob injection via $key must not delete unrelated files');
        \unlink($real);
    }

    // -------------------------------------------------------------------------
    // Issue 6: per-instance cache properties
    // -------------------------------------------------------------------------

    // Testing covered by testEachInstanceHasOwnPrefix() and testEachInstanceHasOwnCachePath()

    // -------------------------------------------------------------------------
    // Issue 9: createNewFileName()
    // -------------------------------------------------------------------------

    // Testing the exception path of getNewFileName() requires tempnam() to return false,
    // which is not deterministic in this environment because tempnam() can fall back
    // to the system temporary directory.

    // -------------------------------------------------------------------------
    // Issue 10: deleteOlderThan()
    // -------------------------------------------------------------------------

    /**
     * @throws FileException
     * @throws RandomException
     */
    public function testDeleteOlderThanNoFiles(): void
    {
        // Call deleteOlderThan() when no files exist for this cache prefix.
        // glob() returns [] so the early-return on line 171 is exercised.
        $cache = new Cache('emptyprefix_' . \uniqid('', true));
        $cache->deleteOlderThan(3600);
        // No exception thrown is the expected outcome.
        $this->expectNotToPerformAssertions();
    }

    /**
     * @throws FileException
     * @throws RandomException
     */
    public function testDeleteOlderThan(): void
    {
        $cache = new Cache('ttl');

        $old = $cache->getNewFileName('aged', '1');
        $fresh = $cache->getNewFileName('aged', '2');
        \file_put_contents($old, '');
        \file_put_contents($fresh, '');

        // Back-date the "old" file to 2 hours ago.
        \touch($old, \time() - 7200);

        // Delete files older than 1 hour.
        $cache->deleteOlderThan(3600);

        $this->assertFalse(\file_exists($old), 'Expired file must be deleted');
        $this->assertTrue(\file_exists($fresh), 'Fresh file must be kept');

        \unlink($fresh);
    }
}
