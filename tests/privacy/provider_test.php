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

namespace quizaccess_stenproc\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use quizaccess_stenproc\api_client;

/**
 * Tests for what this plugin stores about a student and how it forgets them.
 *
 * Moodle keeps only the link between an attempt and its proctoring session.
 * The recordings live in the organisation's own storage.
 *
 * @package    quizaccess_stenproc
 * @copyright  Stenproc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_stenproc\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass the test quiz. */
    protected $quiz;

    /** @var \stdClass the quiz's course module. */
    protected $cm;

    /** @var \context_module the quiz's context. */
    protected $context;

    /** @var \stdClass a student with a proctored attempt. */
    protected $student;

    /** @var \stdClass another student with a proctored attempt. */
    protected $other;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $this->cm = get_coursemodule_from_instance('quiz', $this->quiz->id);
        $this->context = \context_module::instance($this->cm->id);

        $this->student = $this->getDataGenerator()->create_user();
        $this->other = $this->getDataGenerator()->create_user();
        foreach ([$this->student, $this->other] as $user) {
            $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
            $this->add_proctored_attempt($user);
        }
    }

    /**
     * Gives a user an attempt with a proctoring session recorded against it.
     *
     * @param \stdClass $user
     * @return int the attempt id
     */
    protected function add_proctored_attempt(\stdClass $user) {
        global $DB;

        $attemptid = $DB->insert_record('quiz_attempts', (object) [
            'quiz'           => $this->quiz->id,
            'userid'         => $user->id,
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

        $DB->insert_record(api_client::TABLE, (object) [
            'attemptid'    => $attemptid,
            'sessionid'    => '55',
            'status'       => 'closed',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        return $attemptid;
    }

    public function test_it_declares_what_it_stores_and_what_it_sends(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('quizaccess_stenproc'));

        $items = $collection->get_collection();
        $this->assertNotEmpty($items);

        $names = [];
        foreach ($items as $item) {
            $names[] = $item->get_name();
        }
        $this->assertContains('quizaccess_stenproc_session', $names);
        $this->assertContains('stenproc', $names);
    }

    public function test_it_finds_the_quiz_a_student_was_proctored_in(): void {
        $contextlist = provider::get_contexts_for_userid($this->student->id);

        $this->assertEqualsCanonicalizing(
            [$this->context->id],
            $contextlist->get_contextids()
        );
    }

    public function test_a_student_without_a_proctored_attempt_has_no_contexts(): void {
        $stranger = $this->getDataGenerator()->create_user();

        $this->assertEmpty(provider::get_contexts_for_userid($stranger->id)->get_contextids());
    }

    public function test_it_finds_the_students_proctored_in_a_quiz(): void {
        $userlist = new userlist($this->context, 'quizaccess_stenproc');
        provider::get_users_in_context($userlist);

        $this->assertEqualsCanonicalizing(
            [$this->student->id, $this->other->id],
            $userlist->get_userids()
        );
    }

    public function test_it_exports_the_students_own_sessions(): void {
        $contextlist = new approved_contextlist($this->student, 'quizaccess_stenproc', [$this->context->id]);
        provider::export_user_data($contextlist);

        $writer = writer::with_context($this->context);
        $this->assertTrue($writer->has_any_data());

        $data = $writer->get_data([get_string('pluginname', 'quizaccess_stenproc')]);
        $this->assertCount(1, $data->sessions);
        $this->assertSame('55', $data->sessions[0]->sessionid);
        $this->assertSame('closed', $data->sessions[0]->status);
    }

    public function test_deleting_a_quiz_forgets_every_session_in_it(): void {
        global $DB;

        provider::delete_data_for_all_users_in_context($this->context);

        $this->assertEquals(0, $DB->count_records(api_client::TABLE));
    }

    public function test_deleting_one_student_leaves_the_others_alone(): void {
        global $DB;

        $contextlist = new approved_contextlist($this->student, 'quizaccess_stenproc', [$this->context->id]);
        provider::delete_data_for_user($contextlist);

        $remaining = $DB->get_records_sql(
            "SELECT s.id, qa.userid
               FROM {" . api_client::TABLE . "} s
               JOIN {quiz_attempts} qa ON qa.id = s.attemptid"
        );
        $this->assertCount(1, $remaining);
        $this->assertSame((int) $this->other->id, (int) reset($remaining)->userid);
    }

    public function test_deleting_a_named_set_of_students_forgets_just_them(): void {
        global $DB;

        $userlist = new approved_userlist($this->context, 'quizaccess_stenproc', [$this->student->id]);
        provider::delete_data_for_users($userlist);

        $remaining = $DB->get_records_sql(
            "SELECT s.id, qa.userid
               FROM {" . api_client::TABLE . "} s
               JOIN {quiz_attempts} qa ON qa.id = s.attemptid"
        );
        $this->assertCount(1, $remaining);
        $this->assertSame((int) $this->other->id, (int) reset($remaining)->userid);
    }
}
