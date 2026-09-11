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
 * Privacy information for Stenproc proctoring.
 *
 * @package    quizaccess_stenproc
 * @copyright  Stenproc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_stenproc\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Moodle stores only the link between an attempt and its proctoring session.
 * The recordings and incident log are held by Stenproc, in the organisation's
 * own storage, and are removed there under that organisation's retention rules.
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('quizaccess_stenproc_session', [
            'attemptid'   => 'privacy:metadata:quizaccess_stenproc_session:attemptid',
            'sessionid'   => 'privacy:metadata:quizaccess_stenproc_session:sessionid',
            'status'      => 'privacy:metadata:quizaccess_stenproc_session:status',
            'timecreated' => 'privacy:metadata:quizaccess_stenproc_session:timecreated',
        ], 'privacy:metadata:quizaccess_stenproc_session');

        $collection->add_external_location_link('stenproc', [
            'userid'    => 'privacy:metadata:stenproc:userid',
            'fullname'  => 'privacy:metadata:stenproc:fullname',
            'attemptid' => 'privacy:metadata:stenproc:attemptid',
            'video'     => 'privacy:metadata:stenproc:video',
            'events'    => 'privacy:metadata:stenproc:events',
        ], 'privacy:metadata:stenproc');

        return $collection;
    }

    /**
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {quizaccess_stenproc_session} s
                  JOIN {quiz_attempts} qa ON qa.id = s.attemptid
                  JOIN {modules} m ON m.name = :modname
                  JOIN {course_modules} cm ON cm.instance = qa.quiz AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE qa.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'modname'      => 'quiz',
            'contextlevel' => CONTEXT_MODULE,
            'userid'       => $userid,
        ]);

        return $contextlist;
    }

    /**
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $sql = "SELECT qa.userid
                  FROM {quizaccess_stenproc_session} s
                  JOIN {quiz_attempts} qa ON qa.id = s.attemptid
                  JOIN {modules} m ON m.name = :modname
                  JOIN {course_modules} cm ON cm.instance = qa.quiz AND cm.module = m.id
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, ['modname' => 'quiz', 'cmid' => $context->instanceid]);
    }

    /**
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('quiz', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $sessions = $DB->get_records_sql(
                "SELECT s.id, s.attemptid, s.sessionid, s.status, s.timecreated
                   FROM {quizaccess_stenproc_session} s
                   JOIN {quiz_attempts} qa ON qa.id = s.attemptid
                  WHERE qa.userid = :userid AND qa.quiz = :quizid",
                ['userid' => $user->id, 'quizid' => $cm->instance]
            );
            if (!$sessions) {
                continue;
            }

            $exported = [];
            foreach ($sessions as $session) {
                $exported[] = (object) [
                    'attemptid'   => $session->attemptid,
                    'sessionid'   => $session->sessionid,
                    'status'      => $session->status,
                    'timecreated' => transform::datetime($session->timecreated),
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'quizaccess_stenproc')],
                (object) ['sessions' => $exported]
            );
        }
    }

    /**
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('quiz', $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records_subquery('quizaccess_stenproc_session', 'attemptid', 'id',
            "SELECT id FROM {quiz_attempts} WHERE quiz = :quizid", ['quizid' => $cm->instance]);
    }

    /**
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('quiz', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $DB->delete_records_subquery('quizaccess_stenproc_session', 'attemptid', 'id',
                "SELECT id FROM {quiz_attempts} WHERE quiz = :quizid AND userid = :userid",
                ['quizid' => $cm->instance, 'userid' => $userid]);
        }
    }

    /**
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('quiz', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
        $params['quizid'] = $cm->instance;

        $DB->delete_records_subquery('quizaccess_stenproc_session', 'attemptid', 'id',
            "SELECT id FROM {quiz_attempts} WHERE quiz = :quizid AND userid $insql", $params);
    }
}
