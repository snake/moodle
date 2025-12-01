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
 * Simple error page handling errors during LTI authentication.
 *
 * @package    auth_lti
 * @copyright  2025 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\context\system;
use core\param;
use core\url;

require_once(__DIR__ . '/../../config.php');

global $OUTPUT, $PAGE;
$reason = required_param('reason', param::ALPHA);

$PAGE->set_context(system::instance());
$PAGE->set_url(new url('/auth/lti/error.php'));
$PAGE->set_pagelayout('popup');

echo $OUTPUT->header();
if ($reason === 'invalidlogin') {
    throw new \core\exception\moodle_exception('invalidlogin', 'core');
}
echo $OUTPUT->footer();
exit();
