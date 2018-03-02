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
 * Privacy Subsystem implementation for mod_choice.
 *
 * @package    mod_choice
 * @copyright  2018 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_choice\privacy;

use core_privacy\metadata\item_collection;
use core_privacy\request\approved_contextlist;
use core_privacy\request\contextlist;
use core_privacy\request\deletion_criteria;
use core_privacy\request\helper;
use core_privacy\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Implementation of the privacy subsystem plugin provider for the choice activity module.
 *
 * @copyright  2018 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // This plugin stores personal data.
    \core_privacy\metadata\provider,

    // This plugin is a core_user_data_provider.
    \core_privacy\request\plugin\provider
{
    /**
     * {@inheritdoc}
     */
    public static function get_metadata(item_collection $items) : item_collection {
        $items->add_database_table(
            'choice_answers',
            [
                'choiceid' => 'privacy:metadata:choice_answers:choiceid',
                'optionid' => 'privacy:metadata:choice_answers:optionid',
                'userid' => 'privacy:metadata:choice_answers:userid',
                'timemodified' => 'privacy:metadata:choice_answers:timemodified',
            ],
            'privacy:metadata:choice_answers'
        );

        return $items;
    }

    /**
     * {@inheritdoc}
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        // Fetch all choice answers.
        $sql = "SELECT c.id
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {choice} ch ON ch.id = cm.instance
             LEFT JOIN {choice_options} co ON co.choiceid = ch.id
             LEFT JOIN {choice_answers} ca ON ca.optionid = co.id AND ca.choiceid = ch.id
                 WHERE ca.userid = :userid";

        $params = [
            'modname'       => 'choice',
            'contextlevel'  => CONTEXT_MODULE,
            'userid'        => $userid,
        ];
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * {@inheritdoc}
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT cm.id AS cmid,
                       co.text as answer,
                       ca.timemodified
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid
            INNER JOIN {choice} ch ON ch.id = cm.instance
             LEFT JOIN {choice_options} co ON co.choiceid = ch.id
             LEFT JOIN {choice_answers} ca ON ca.optionid = co.id AND ca.choiceid = ch.id
                 WHERE c.id {$contextsql}
                       AND ca.userid = :userid";

        $params = ['userid' => $user->id] + $contextparams;

        // Create an array of the user's choice instances, supporting multiple answers per choice instance.
        $choiceinstances = [];
        $choiceanswers = $DB->get_recordset_sql($sql, $params);
        foreach ($choiceanswers as $choiceanswer) {
            if (empty($choiceinstances[$choiceanswer->cmid])) {
                $data = (object) [
                    'answer' => [$choiceanswer->answer],
                    'timemodified' => \core_privacy\request\transform::datetime($choiceanswer->timemodified),
                ];
                $choiceinstances[$choiceanswer->cmid] = $data;
            } else {
                // Instance exists, just add the additional answer.
                $choiceinstances[$choiceanswer->cmid]->answer[] = $choiceanswer->answer;
            }
        }
        $choiceanswers->close();

        // Now export the data.
        foreach ($choiceinstances as $cmid => $choicedata) {
            $context = \context_module::instance($cmid);

            // Fetch the generic module data for the choice.
            $contextdata = helper::get_context_data($context, $user);

            // Merge with choice data and write it.
            $contextdata = (object) array_merge((array) $contextdata, (array) $choicedata);
            writer::with_context($context)
                ->export_data([], $contextdata);

            // Write generic module intro files.
            helper::export_context_files($context, $user);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function delete_for_context(deletion_criteria $criteria) {
        global $DB;

        $context = $criteria->get_context();
        if (empty($context)) {
            return;
        }
        $instanceid = $DB->get_field('course_modules', 'instance', ['id' => $context->instanceid], MUST_EXIST);
        $DB->delete_records('choice_answers', ['choiceid' => $instanceid]);
    }

    /**
     * {@inheritdoc}
     */
    public static function delete_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $instanceid = $DB->get_field('course_modules', 'instance', ['id' => $context->instanceid], MUST_EXIST);
            $DB->delete_records('choice_answers', ['choiceid' => $instanceid, 'userid' => $userid]);
        }
    }
}
