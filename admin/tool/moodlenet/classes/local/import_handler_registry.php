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
 * Contains the import_handler_registry class.
 *
 * @package tool_moodlenet
 * @copyright 2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_moodlenet\local;

/**
 * The import_handler_registry class.
 *
 * The import_handler_registry objects represent a register of modules handling various file extensions for a given course and user.
 * Only modules which are available to the user in the course are included in the register for that user.
 *
 * @copyright 2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_handler_registry {

    /**
     * @var array array containing the names and messages of all modules handling import of resources as a 'file' type.
     */
    protected $filehandlers = [];

    /**
     * @var \context_course the course context object.
     */
    protected $context;

    /**
     * @var \stdClass a course object.
     */
    protected $course;

    /**
     * @var \stdClass a user object.
     */
    protected $user;

    /**
     * The import_handler_registry constructor.
     *
     * @param \stdClass $course the course, which impacts available handlers.
     * @param \stdClass $user the user, which impacts available handlers.
     */
    public function __construct(\stdClass $course, \stdClass $user) {
        $this->course = $course;
        $this->user = $user;
        $this->context = \context_course::instance($course->id);

        // Generate the full list of handlers for all extensions for this user and course.
        $this->populate_handlers();
    }

    /**
     * Get all handlers for the remote resource, depending on the strategy being used to import the resource.
     *
     * @param remote_resource $resource the remote resource.
     * @param import_strategy $strategy an import_strategy instance.
     * @return import_handler_info[] the array of import_handler_info handlers.
     */
    public function get_resource_handlers_for_strategy(remote_resource $resource, import_strategy $strategy): array {
        return $strategy->get_handlers($this->filehandlers, $resource);
    }

    /**
     * Get a specific handler for the resource, belonging to a specific module and for a specific strategy.
     *
     * @param remote_resource $resource the remote resource.
     * @param string $modname the name of the module, e.g. 'label'.
     * @param import_strategy $strategy a string representing how to treat the resource. e.g. 'file', 'link'.
     * @return import_handler_info|null the import_handler_info object, if found, otherwise null.
     */
    public function get_resource_handler_for_mod_and_strategy(remote_resource $resource, string $modname,
            import_strategy $strategy): ?import_handler_info {
        foreach ($strategy->get_handlers($this->filehandlers, $resource) as $handler) {
            if ($handler->get_module_name() === $modname) {
                return $handler;
            }
        }
        return null;
    }

    /**
     * Shim replacing the use of course_allowed_module with its Moodle 3.9 variant, which includes user support.
     *
     * @param object $course the course settings. Only $course->id is used.
     * @param string $modname the module name. E.g. 'forum' or 'quiz'.
     * @param string $user the user to check.
     * @return bool whether the current user is allowed to add this type of module to this course.
     */
    protected function course_allowed_module($course, $modname, $user) {
        if (is_numeric($modname)) {
            throw new coding_exception('Function course_allowed_module no longer
            supports numeric module ids. Please update your code to pass the module name.');
        }

        $capability = 'mod/' . $modname . ':addinstance';
        if (!get_capability_info($capability)) {
            // Debug warning that the capability does not exist, but no more than once per page.
            static $warned = array();
            $archetype = plugin_supports('mod', $modname, FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER);
            if (!isset($warned[$modname]) && $archetype !== MOD_ARCHETYPE_SYSTEM) {
                debugging('The module ' . $modname . ' does not define the standard capability ' .
                    $capability , DEBUG_DEVELOPER);
                $warned[$modname] = 1;
            }

            // If the capability does not exist, the module can always be added.
            return true;
        }

        $coursecontext = \context_course::instance($course->id);
        return has_capability($capability, $coursecontext, $user);
    }

    /**
     * Build up a list of extension handlers by leveraging the dndupload_register callbacks.
     */
    protected function populate_handlers() {
        // Get the list of mods enabled at site level first. We need to cross check this.
        $pluginman = \core_plugin_manager::instance();
        $sitemods = $pluginman->get_plugins_of_type('mod');
        $sitedisabledmods = array_filter($sitemods, function(\core\plugininfo\mod $modplugininfo){
            return !$modplugininfo->is_enabled();
        });
        $sitedisabledmods = array_map(function($modplugininfo) {
            return $modplugininfo->name;
        }, $sitedisabledmods);

        // Loop through all modules to find the registered handlers.
        $mods = get_plugin_list_with_function('mod', 'dndupload_register');
        foreach ($mods as $component => $funcname) {
            list($modtype, $modname) = \core_component::normalize_component($component);
            if (!empty($sitedisabledmods) && array_key_exists($modname, $sitedisabledmods)) {
                continue; // Module is disabled at the site level.
            }
            if (!$this->course_allowed_module($this->course, $modname, $this->user)) {
                continue; // User does not have permission to add this module to the course.
            }

            if (!$resp = component_callback($component, 'dndupload_register')) {
                continue;
            };

            if (isset($resp['files'])) {
                foreach ($resp['files'] as $file) {
                    $this->register_file_handler($file['extension'], $modname, $file['message']);
                }
            }
        }
    }

    /**
     * Adds a file extension handler to the list.
     *
     * @param string $extension the extension, e.g. 'png'.
     * @param string $module the name of the module handling this extension
     * @param string $message the message describing how the module handles the extension.
     */
    protected function register_file_handler(string $extension, string $module, string $message) {
        $extension = strtolower($extension);
        $this->filehandlers[$extension][] = ['module' => $module, 'message' => $message];
    }
}

