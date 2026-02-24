<?php

/**
 * Byte.php
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
 * Com\Tecnick\File\Byte
 *
 * Function to read byte-level data
 *
 * @since     2015-07-28
 * @category  Library
 * @package   File
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2015-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-file
 */
readonly class Byte
{
    public \SplFixedArray $bytes;

    /**
     * Initialize a new string to be processed
     *
     * @param string $str String (binary) from where to extract values
     */
    public function __construct(string $str)
    {
        // Unpack string into an array of bytes and convert to SplFixedArray to
        // avoid using \substr thousands of times for accessing 1-4 bytes at a time.
        $binary = \unpack('C*', $str);
        $this->bytes = \SplFixedArray::fromArray($binary, false);
    }

    /**
     * Get BYTE from string (8-bit unsigned integer).
     *
     * @param int $offset Point from where to read the data.
     *
     * @return int 8 bit value
     */
    public function getByte(int $offset): int
    {
        return $this->bytes[$offset] & 0xff;
    }

    /**
     * Get USHORT from string (Big Endian 16-bit unsigned integer).
     *
     * @param int $offset Point from where to read the data
     *
     * @return int 16 bit value
     */
    public function getUShort(int $offset): int
    {
        return (($this->bytes[$offset] << 8) & 0xff00) | ($this->bytes[$offset + 1] & 0xff);
    }

    /**
     * Get SHORT from string (Big Endian 16-bit signed integer).
     *
     * @param int $offset Point from where to read the data.
     *
     * @return int 16 bit value
     */
    public function getShort(int $offset): int
    {
        // The uint16 value
        $u_val = (($this->bytes[$offset] << 8) & 0xff00) | ($this->bytes[$offset + 1] & 0xff);
        // Use bitwise two's complement uint16 to int16 formula
        return ($u_val ^ 0x8000) - 0x8000;
    }

    /**
     * Get UFWORD from string (Big Endian 16-bit unsigned integer).
     * Alias for getUShort().
     *
     * @param int $offset Point from where to read the data.
     *
     * @return int 16 bit value
     */
    public function getUFWord(int $offset): int
    {
        return (($this->bytes[$offset] << 8) & 0xff00) | ($this->bytes[$offset + 1] & 0xff);
    }

    /**
     * Get FWORD from string (Big Endian 16-bit signed integer).
     * Alias for getShort().
     *
     * @param int $offset Point from where to read the data.
     *
     * @return int 16 bit value
     */
    public function getFWord(int $offset): int
    {
        // The uint16 value
        $u_val = (($this->bytes[$offset] << 8) & 0xff00) | ($this->bytes[$offset + 1] & 0xff);
        // Use bitwise two's complement uint16 to int16 formula
        return ($u_val ^ 0x8000) - 0x8000;
    }

    /**
     * Get ULONG from string (Big Endian 32-bit unsigned integer).
     *
     * @param int $offset Point from where to read the data
     *
     * @return int 32 bit value
     */
    public function getULong(int $offset): int
    {
        return (($this->bytes[$offset] << 24) & 0xff000000) |
            (($this->bytes[$offset + 1] << 16) & 0xff0000) |
            (($this->bytes[$offset + 2] << 8) & 0xff00) |
            ($this->bytes[$offset + 3] & 0xff);
    }

    /**
     * Get LONG from string (Big Endian 32-bit signed integer).
     *
     * @param int $offset Point from where to read the data
     *
     * @return int 32 bit value
     */
    public function getLong(int $offset): int
    {
        $u_val =
            (($this->bytes[$offset] << 24) & 0xff000000) |
            (($this->bytes[$offset + 1] << 16) & 0xff0000) |
            (($this->bytes[$offset + 2] << 8) & 0xff00) |
            ($this->bytes[$offset + 3] & 0xff);
        // Use bitwise two's complement uint32 to int32 formula
        return ($u_val ^ 0x80000000) - 0x80000000;
    }

    /**
     * Get FIXED from string (Big Endian 32-bit signed fixed-point number (16.16)).
     *
     * A fixed-point 16.16 number is 'int16 + uint16/65536.0' where the divisor 65536=(1<<16).
     * A simplified equivalent version is to read an int32 and divide by 65536.
     *
     * @param int $offset Point from where to read the data.
     */
    public function getFixed(int $offset): float
    {
        $int16 = (((($this->bytes[$offset] << 8) & 0xff00) | ($this->bytes[$offset + 1] & 0xff)) ^ 0x8000) - 0x8000;
        return $int16 + ((($this->bytes[$offset + 2] << 8) & 0xff00) | ($this->bytes[$offset + 3] & 0xff)) / 65536.0;
    }
}
