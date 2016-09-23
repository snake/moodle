<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Contains a simple class providing some useful internet protocol-related functions.
 *
 * @package   core
 * @copyright 2016 Jake Dallimore
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jake Dallimore <jrhdallimore@gmail.com>
 */

namespace core;

defined('MOODLE_INTERNAL') || exit();

/**
 * Static helper class providing some useful IP-related functions.
 *
 * @package   core
 * @copyright 2016 Jake Dallimore
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jake Dallimore <jrhdallimore@gmail.com>
 */
final class ip_utils {
    /**
     * Syntax checking for domain names, including fully qualified domain names (host name included).
     * This function does not verify the existence of the domain name. It only verifies syntactic correctness.
     * This is loosely based on RFC1034/1035, however, due to relaxed TLD restrictions, it should not be considered fully compliant.
     *
     * @param string $domainname the input string to check.
     * @return bool true if the string has valid syntax, false otherwise.
     */
    public static function is_domain_name($domainname) {
        // The entire name cannot exceed 253 characters.
        if (strlen($domainname) > 253) {
            return false;
        }
        // Tertiary domain labels can have 63 octets max, and must not have begin or end with a hyphen. Number = unlimited.
        // The TLD label cannot begin with a number, but otherwise, is only loosely restricted (as per lib/validateurlsyntax.php).
        $alphanum    = '[a-zA-Z0-9]';
        $domaintertiary   = '(' . $alphanum . '(([a-zA-Z0-9-]{0,61})' . $alphanum . ')?\.)*';
        $domaintoplevel   = '([a-zA-Z](([a-zA-Z0-9-]*)[a-zA-Z0-9])?)';
        $address       = '(' . $domaintertiary .  $domaintoplevel . ')';
        $regexp = '#^' . $address . '$#i'; // Case insensitive matching.
        return preg_match($regexp, $domainname, $match) == true; // False for error, 0 for no match - we treat the same.
    }

    /**
     * Syntax checker for wildcard domain names.
     * This function only confirms syntactic correctness. It does not check for the existence of the domain/subdomains.
     *
     * @param string $domainname the string to check.
     * @return bool true if the input string is a valid wildcard domain name, false otherwise.
     */
    public static function is_wildcard_domain_name($domainname) {
        // The entire name cannot exceed 253 characters.
        if (strlen($domainname) > 253) {
            return false;
        }
        // A wildcard domain name must have a left-positioned wildcard symbol (*) to be considered valid.
        // Tertiary domain labels can have 63 chars max, and must not have begin or end with a hyphen. Number = unlimited.
        // The TLD label cannot begin with a number, but otherwise, is only loosely restricted (as per lib/validateurlsyntax.php).
        $alphanum    = '[a-zA-Z0-9]';
        $domainwildcard = '((\*)\.){1}';
        $domaintertiary   = '(' . $alphanum . '(([a-zA-Z0-9-]{0,61})' . $alphanum . ')?\.)*';
        $domaintoplevel   = '([a-zA-Z](([a-zA-Z0-9-]*)[a-zA-Z0-9])?)';
        $address       = '(' . $domainwildcard . $domaintertiary .  $domaintoplevel . ')';
        $regexp = '#^' . $address . '$#i'; // Case insensitive matching.
        return preg_match($regexp, $domainname, $match) == true; // False for error, 0 for no match - we treat the same.
    }

    /**
     * Syntax validation for IPv4 addresses.
     *
     * @param string $address the address to check.
     * @return bool true if the address is a valid IPv4 address or range, false otherwise.
     */
    public static function is_ipv4_address($address) {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * Syntax checking for IPv4 address ranges.
     * Supports CIDR notation and last-group ranges.
     * Eg. 127.0.0.0/24 or 127.0.0.80-255
     *
     * @param string $addressrange the address range to check.
     * @return bool true if the string is a valid range representation, false otherwise.
     */
    public static function is_ipv4_range($addressrange) {
        // Check CIDR notation.
        if (preg_match('#^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\/(\d{1,2})$#', $addressrange, $match)) {
            $address = "{$match[1]}.{$match[2]}.{$match[3]}.{$match[4]}";
            return self::is_ipv4_address($address) && $match[5] <= 32;
        }
        // Check last-group notation.
        if (preg_match('#^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})-(\d{1,3})$#', $addressrange, $match)) {
            $address = "{$match[1]}.{$match[2]}.{$match[3]}.{$match[4]}";
            return self::is_ipv4_address($address) && $match[5] <= 255 && $match[5] >= $match[4];
        }
        return false;
    }

    /**
     * Syntax validation for IPv6 addresses.
     * This function does not check whether the address is assigned, only its syntactical correctness.
     *
     * @param string $address the address to check.
     * @return bool true if the address has correct syntax, false otherwise.
     */
    public static function is_ipv6_address($address) {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /**
     * Syntax validation for IPv6 address ranges.
     * Supports CIDR notation and last-group ranges.
     * Eg. fe80::d98c/64 or fe80::d98c-ffee
     *
     * @param string $addressrange the IPv6 address range to check.
     * @return bool true if the string is a valid range representation, false otherwise.
     */
    public static function is_ipv6_range($addressrange) {
        // Check CIDR notation.
        $ipv6parts = explode('/', $addressrange);
        if (count($ipv6parts) == 2) {
            $range = (int)$ipv6parts[1];
            return self::is_ipv6_address($ipv6parts[0]) && (string)$range === $ipv6parts[1] && $range >= 0 && $range <= 128;
        }
        // Check last-group notation.
        $ipv6parts = explode('-', $addressrange);
        if (count($ipv6parts) == 2) {
            $addressparts = explode(':', $ipv6parts[0]);
            $rangestart = $addressparts[count($addressparts) - 1];
            $rangeend = $ipv6parts[1];
            return self::is_ipv6_address($ipv6parts[0]) && ctype_xdigit($rangestart) && ctype_xdigit($rangeend)
                   && strlen($rangeend) <= 4 && strlen($rangestart) <= 4 && hexdec($rangeend) >= hexdec($rangestart);
        }
        return false;
    }
}
