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

namespace quizaccess_stenproc;

/**
 * Tests for the event observers that close proctoring sessions.
 *
 * Moodle owns the attempt, so the session is closed from Moodle's own events
 * rather than from the student's browser.
 *
 * @package    quizaccess_stenproc
 * @copyright  Stenproc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_stenproc\observer
 */
final class observer_test extends \advanced_testcase {
    /** @var \stdClass the test quiz. */
    protected $quiz;

    /** @var \stdClass the quiz's course module. */
    protected $cm;

    /** @var \stdClass a student. */
    protected $student;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $this->cm = get_coursemodule_from_instance('quiz', $this->quiz->id);
        $this->student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->student->id, $course->id, 'student');
    }

    /**
     * Inserts an attempt row directly, which is all these observers look at.
     *
     * @return int the attempt id
     */
    protected function add_attempt() {
        global $DB;

        return $DB->insert_record('quiz_attempts', (object) [
            'quiz'           => $this->quiz->id,
            'userid'         => $this->student->id,
            'attempt'        => 1,
            'uniqueid'       => $DB->count_records('quiz_attempts') + 1,
            'layout'         => '1,0',
            'currentpage'    => 0,
            'preview'        => 0,
            'state'          => 'finished',
            'timestart'      => time(),
            'timefinish'     => time(),
            'timemodified'   => time(),
            'timecheckstate' => null,
            'sumgrades'      => null,
        ]);
    }

    /**
     * Records a closed proctoring session, so closing it again is a no-op and
     * the observer never reaches for the network.
     *
     * @param int $attemptid
     */
    protected function add_closed_session($attemptid) {
        global $DB;

        $DB->insert_record(api_client::TABLE, (object) [
            'attemptid'    => $attemptid,
            'sessionid'    => '55',
            'status'       => 'closed',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    public function test_deleting_an_attempt_forgets_its_proctoring_session(): void {
        global $DB;

        $attemptid = $this->add_attempt();
        $this->add_closed_session($attemptid);

        $event = \mod_quiz\event\attempt_deleted::create([
            'objectid'      => $attemptid,
            'relateduserid' => $this->student->id,
            'context'       => \context_module::instance($this->cm->id),
            'other'         => ['quizid' => $this->quiz->id],
        ]);
        observer::attempt_deleted($event);

        $this->assertFalse($DB->record_exists(api_client::TABLE, ['attemptid' => $attemptid]));
    }

    public function test_submitting_an_attempt_leaves_a_closed_session_alone(): void {
        global $DB;

        $attemptid = $this->add_attempt();
        $this->add_closed_session($attemptid);

        $event = \mod_quiz\event\attempt_submitted::create([
            'objectid'      => $attemptid,
            'relateduserid' => $this->student->id,
            'context'       => \context_module::instance($this->cm->id),
            'other'         => ['quizid' => $this->quiz->id, 'submitterid' => $this->student->id],
        ]);
        observer::attempt_submitted($event);

        // Still recorded, and still closed: submitting does not forget the link.
        $record = $DB->get_record(api_client::TABLE, ['attemptid' => $attemptid]);
        $this->assertSame('closed', $record->status);
    }

    public function test_a_failure_reaching_stenproc_does_not_stop_moodle(): void {
        global $DB;

        // An open session on an unconfigured site: closing it throws inside the
        // observer, which must swallow the failure so the attempt still finishes.
        $attemptid = $this->add_attempt();
        $DB->insert_record(api_client::TABLE, (object) [
            'attemptid'    => $attemptid,
            'sessionid'    => '55',
            'status'       => 'open',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $event = \mod_quiz\event\attempt_submitted::create([
            'objectid'      => $attemptid,
            'relateduserid' => $this->student->id,
            'context'       => \context_module::instance($this->cm->id),
            'other'         => ['quizid' => $this->quiz->id, 'submitterid' => $this->student->id],
        ]);
        observer::attempt_submitted($event);

        $this->assertDebuggingCalled();
    }
}
