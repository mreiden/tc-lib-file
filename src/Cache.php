<?php

declare(strict_types=1);

/**
 * Cache.php
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

namespace Com\Tecnick\File;

use Com\Tecnick\File\Exception as FileException;

/**
 * Com\Tecnick\Pdf\File\Cache
 *
 * @since     2011-05-23
 * @category  Library
 * @package   File
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-filecache
 */
class Cache
{
    protected bool $isWindows;

    /**
     * Cache path
     *
     * @var string
     */
    protected static $path = '';

    /**
     * File prefix
     */
    protected static string $prefix;

    /**
     * Set the file prefix (common name)
     *
     * @param ?string $prefix Common prefix to be used for all cache files
     *
     * @throws FileException
     * @throws \Random\RandomException
     */
    public function __construct(?string $prefix = null)
    {
        $this->isWindows = \PHP_OS_FAMILY === 'Windows';

        $this->defineSystemCachePath();
        $this->setCachePath();
        $prefix ??= \rtrim(
            \base64_encode(\pack('H*', \md5(\uniqid((string) \random_int(0, \PHP_INT_MAX), true)))),
            '=',
        );

        self::$prefix = '_' . \preg_replace('/[^a-zA-Z0-9_\-]/', '', \strtr($prefix, '+/', '-_')) . '_';
    }

    /**
     * Get the cache directory path
     */
    public function getCachePath(): string
    {
        return self::$path;
    }

    /**
     * Set the default cache directory path
     *
     * @param ?string $path Cache directory path; if null use the K_PATH_CACHE value
     *
     * @throws FileException
     */
    public function setCachePath(?string $path = null): void
    {
        if ($path === null || str_contains($path, '://') || !\is_writable($path)) {
            if (\defined('K_PATH_CACHE') && \is_string(K_PATH_CACHE) && $path !== K_PATH_CACHE) {
                $this->setCachePath(K_PATH_CACHE);
                return;
            }
            throw new FileException('Cache path is not writable.');
        }
        self::$path = $this->normalizePath($path);
    }

    /**
     * Get the file prefix
     */
    public function getFilePrefix(): string
    {
        return self::$prefix;
    }

    /**
     * Returns a temporary filename for caching files
     *
     * @param string $type Type of file
     * @param string $key  File key (used to retrieve file from cache)
     *
     * @return string|false filename
     *
     * @throws FileException
     * @throws \Random\RandomException
     */
    public function getNewFileName(string $type = 'tmp', string $key = '0'): string|bool
    {
        $filepath = self::$path . self::$prefix . "{$type}_{$key}_";
        $length = \strlen($filepath);

        // Windows limits the whole filepath to 258 chars (254 before adding '.tmp' suffix)
        if ($this->isWindows && $length > 254) {
            throw new FileException('Cache filepath exceeds maximum length of 258 on Windows.');
        }
        $numBytes = \max(0, \min(15, (int) \floor((254 - $length) / 2)));
        if ($numBytes > 0) {
            $filepath .= \bin2hex(\random_bytes($numBytes));
        }
        $filepath .= '.tmp';

        return \file_exists($filepath) ? $this->getNewFileName($type, $key) : $filepath;
    }

    /**
     * Delete cached files
     *
     * @param ?string $type Type of files to delete
     * @param ?string $key  Specific file key to delete
     */
    public function delete(?string $type = null, ?string $key = null): void
    {
        $path = self::$path . self::$prefix;
        if ($type !== null) {
            $path .= $type . '_';
            if ($key !== null) {
                $path .= $key . '_';
            }
        }

        $files = \glob($path . '*');
        if (empty($files)) {
            return;
        }

        foreach ($files as $file) {
            \unlink($file);
        }
    }

    /**
     * Set the K_PATH_CACHE constant (if not set) to the default system directory for temporary files
     */
    protected function defineSystemCachePath(): void
    {
        if (\defined('K_PATH_CACHE')) {
            return;
        }

        $kPathCache = \ini_get('upload_tmp_dir') ?: \sys_get_temp_dir();
        \define('K_PATH_CACHE', $this->normalizePath($kPathCache));
    }

    /**
     * Normalize cache path
     *
     * @param string $path Path to normalize
     */
    protected function normalizePath(string $path): string
    {
        $rpath = \realpath($path);
        if ($rpath === false) {
            return '';
        }

        if (!\str_ends_with($rpath, '/')) {
            $rpath .= '/';
        }

        return $rpath;
    }
}
