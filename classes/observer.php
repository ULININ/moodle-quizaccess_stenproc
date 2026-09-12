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
 * Closes proctoring sessions when quiz attempts finish.
 *
 * @package    quizaccess_stenproc
 * @copyright  Stenproc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_stenproc;

/**
 * Moodle owns the attempt, so the session is closed from Moodle's own events
 * rather than from the student's browser, which may already have navigated away.
 */
class observer {
    /**
     * Closes the session when the student submits the attempt.
     *
     * @param \core\event\base $event
     */
    public static function attempt_submitted(\core\event\base $event) {
        self::close($event, 'Attempt submitted in Moodle');
    }

    /**
     * Closes the session when Moodle abandons the attempt.
     *
     * @param \core\event\base $event
     */
    public static function attempt_abandoned(\core\event\base $event) {
        self::close($event, 'Attempt abandoned in Moodle');
    }

    /**
     * Closes the session and forgets the attempt when it is deleted.
     *
     * @param \core\event\base $event
     */
    public static function attempt_deleted(\core\event\base $event) {
        self::close($event, 'Attempt deleted in Moodle');
        api_client::forget_attempt((int) $event->objectid);
    }

    /**
     * A failure here must not stop Moodle finishing the attempt, so it is
     * logged rather than thrown. Stenproc also closes sessions whose media has
     * gone quiet.
     *
     * @param \core\event\base $event
     * @param string $reason
     */
    protected static function close(\core\event\base $event, $reason) {
        try {
            api_client::close_session((int) $event->objectid, $reason);
        } catch (\Exception $e) {
            debugging('Stenproc proctoring session could not be closed: ' . $e->getMessage(), DEBUG_NORMAL);
        }
    }
}
