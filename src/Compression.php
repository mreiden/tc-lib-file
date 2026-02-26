<?php

declare(strict_types=1);

/**
 * Compression.php
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

namespace Com\Tecnick\File;

use Com\Tecnick\File\Exception as FileException;

/**
 * Com\Tecnick\File\Compression
 *
 * Class to handle compression and decompression.
 *
 * @since     2026-02-25
 * @category  Library
 * @package   File
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2026-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-file
 */
class Compression
{
    private static int $level = 6;

    /**
     * @param int<0,9> $level
     *
     * @return int
     *
     * @throws FileException
     */
    public static function setCompressionLevel(int $level): int
    {
        if ($level < 0 || $level > 9) {
            throw new FileException('Compression level must be between 0 and 9');
        }
        $oldLevel = self::$level;
        self::$level = $level;

        return $oldLevel;
    }

    /**
     * @param string         $data
     * @param bool           $doCompress
     *
     * @return string
     *
     * @throws FileException
     */
    public static function compress(string $data, bool $doCompress = true): string
    {
        if (!$doCompress) {
            return $data;
        }

        $data = \gzcompress($data, self::$level);
        if ($data === false) {
            throw new FileException('Unable to compress data');
        }

        return $data;
    }

    /**
     * Uncompress a binary string compressed using gzcompress.
     *
     * @param string    $data     The binary string compressed using gzcompress.
     *
     * @return string             The uncompressed string or the original string if gzuncompress fails.
     */
    public static function uncompress(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $uncompressedData = @\gzuncompress($data);
        if ($uncompressedData !== false) {
            return $uncompressedData;
        }

        return $data;
    }
}
