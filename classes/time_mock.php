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
 * phpUnit mocktesttime class definitions.
 *
 * @package    tool_mocktesttime
 * @category   test
 * @copyright  2025 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mocktesttime;

use core_component;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use stdClass;
use Throwable;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * A way to mock time()
 */
class time_mock {
    /**
     * The time which should be returned.
     *
     * @var int
     */
    private static $mocktime = 0;

    /**
     * Sets the mock time.
     *
     * @param ?int $timestamp
     *
     */
    public static function set_mock_time($timestamp = null) {

        if (empty($timestamp)) {
            $timestamp = time();
        }

        self::$mocktime = $timestamp;
    }

    /**
     * Resets the mock time.
     *
     */
    public static function reset_mock_time() {
        self::$mocktime = strtotime('now');
    }

    /**
     * Returns the mock time.
     *
     * @return int
     *
     */
    public static function get_mock_time() {

        if (empty(self::$mocktime)) {
            self::$mocktime = strtotime('now');
        }

        return self::$mocktime;
    }

    /**
     * Init mock testtime.
     *
     * @return void
     *
     */
    public static function init(): void {

        $namespaces = [];
        $directory = dirname(__DIR__, 4);

        // Make sure the directory exists and is a directory.
        if (!is_dir($directory)) {
            throw new Exception("Provided path is not a valid directory: $directory");
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            };

            $contents = file_get_contents($file->getRealPath());
            if (preg_match('/namespace\s+([a-zA-Z0-9_\\\\]+);/', $contents, $matches)) {
                $namespace = trim($matches[1]);
                if (!in_array($namespace, $namespaces)) {
                    $namespaces[] = $namespace;
                }
            }
        }

        $overridedir = __DIR__ . '/time_overrides/';
        if (!is_dir($overridedir)) {
            mkdir($overridedir, 0777, true);
        }

        foreach ($namespaces as $namespace) {
            $namespace = str_replace('\\\\', '\\', $namespace);
            $filename = $overridedir . str_replace('\\', '_', $namespace) . '_time.php';

            if (file_exists($filename)) {
                continue;
            }
            $overridecode = "<?php
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
 * phpUnit tool_mocktesttime class definitions.
 *
 * @package    tool_mocktesttime
 * @category   test
 * @copyright  2025 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace $namespace;

/**
 * This function will oveerride my namespace
 *
 * @return [type]
 *
 */
function time() {
    return \\tool_mocktesttime\\time_mock::get_mock_time() ?? strtotime('now');
}
";

            file_put_contents($filename, $overridecode);
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($overridedir));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getRealPath();
            try {
                // There might be an error with redeclation of namespaces. We catch it.
                require_once($path);
            } catch (Throwable $e) {
                continue;
            }
        }
    }
}
