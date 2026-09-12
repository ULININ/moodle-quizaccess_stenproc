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
 * Tests for the Stenproc quiz access rule.
 *
 * @package    quizaccess_stenproc
 * @copyright  Stenproc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_stenproc
 */
final class rule_test extends \advanced_testcase {
    /** @var \stdClass the course the test quiz belongs to. */
    protected $course;

    /** @var \stdClass the test quiz. */
    protected $quiz;

    /** @var \stdClass a student. */
    protected $student;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
        $this->student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, 'student');
    }

    protected function tearDown(): void {
        // The forced user agent is global state, not database state, so
        // resetAfterTest does not clear it.
        \core_useragent::instance(true);
        parent::tearDown();
    }

    /**
     * Builds the quiz object, which Moodle 4.2 moved into a namespace.
     *
     * @param int $userid
     * @return object
     */
    protected function quizobj($userid) {
        global $CFG;

        if (class_exists('\\mod_quiz\\quiz_settings')) {
            return \mod_quiz\quiz_settings::create($this->quiz->id, $userid);
        }
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        return \quiz::create($this->quiz->id, $userid);
    }

    /**
     * The settings a quiz form would submit with proctoring turned on.
     *
     * @param array $overrides
     * @return \stdClass
     */
    protected function form_data(array $overrides = []) {
        return (object) array_merge([
            'id'                     => $this->quiz->id,
            'stenprocenabled'        => 1,
            'stenprocwebcam'         => 1,
            'stenprocscreenshare'    => 0,
            'stenprocrecording'      => 1,
            'stenprocfacedetection'  => 0,
            'stenproctabswitch'      => 1,
        ], $overrides);
    }

    public function test_save_settings_stores_the_choices(): void {
        global $DB;

        \quizaccess_stenproc::save_settings($this->form_data());

        $record = $DB->get_record('quizaccess_stenproc', ['quizid' => $this->quiz->id]);
        $this->assertEquals(1, $record->enabled);
        $this->assertEquals(1, $record->webcam);
        $this->assertEquals(0, $record->screenshare);
        $this->assertEquals(1, $record->recording);
        $this->assertEquals(0, $record->facedetection);
        $this->assertEquals(1, $record->tabswitch);
    }

    public function test_save_settings_updates_rather_than_duplicates(): void {
        global $DB;

        \quizaccess_stenproc::save_settings($this->form_data());
        \quizaccess_stenproc::save_settings($this->form_data(['stenprocscreenshare' => 1]));

        $this->assertEquals(1, $DB->count_records('quizaccess_stenproc', ['quizid' => $this->quiz->id]));
        $record = $DB->get_record('quizaccess_stenproc', ['quizid' => $this->quiz->id]);
        $this->assertEquals(1, $record->screenshare);
    }

    public function test_save_settings_removes_the_row_when_turned_off(): void {
        global $DB;

        \quizaccess_stenproc::save_settings($this->form_data());
        \quizaccess_stenproc::save_settings($this->form_data(['stenprocenabled' => 0]));

        $this->assertFalse($DB->record_exists('quizaccess_stenproc', ['quizid' => $this->quiz->id]));
    }

    public function test_delete_settings_removes_the_row(): void {
        global $DB;

        \quizaccess_stenproc::save_settings($this->form_data());
        \quizaccess_stenproc::delete_settings((object) ['id' => $this->quiz->id]);

        $this->assertFalse($DB->record_exists('quizaccess_stenproc', ['quizid' => $this->quiz->id]));
    }

    public function test_make_returns_nothing_for_an_unproctored_quiz(): void {
        $quizobj = $this->quizobj($this->student->id);

        $this->assertNull(\quizaccess_stenproc::make($quizobj, time(), false));
    }

    public function test_make_returns_the_rule_for_a_proctored_quiz(): void {
        \quizaccess_stenproc::save_settings($this->form_data());

        $rule = \quizaccess_stenproc::make($this->quizobj($this->student->id), time(), false);

        $this->assertInstanceOf(\quizaccess_stenproc::class, $rule);
    }

    public function test_get_checks_reports_what_the_quiz_turned_on(): void {
        \quizaccess_stenproc::save_settings($this->form_data([
            'stenprocscreenshare'   => 1,
            'stenprocfacedetection' => 0,
        ]));

        $rule = \quizaccess_stenproc::make($this->quizobj($this->student->id), time(), false);

        $this->assertSame([
            'webcam'        => true,
            'screen'        => true,
            'recording'     => true,
            'faceDetection' => false,
            'tabSwitch'     => true,
        ], $rule->get_checks());
    }

    public function test_prevent_access_blocks_when_the_site_is_not_set_up(): void {
        \quizaccess_stenproc::save_settings($this->form_data());
        $rule = \quizaccess_stenproc::make($this->quizobj($this->student->id), time(), false);

        $this->assertSame(get_string('notconfigured', 'quizaccess_stenproc'), $rule->prevent_access());
    }

    public function test_prevent_access_allows_the_attempt_once_configured(): void {
        set_config('apibaseurl', 'https://api.example.com', 'quizaccess_stenproc');
        set_config('apikey', 'test-key', 'quizaccess_stenproc');
        \quizaccess_stenproc::save_settings($this->form_data());

        $rule = \quizaccess_stenproc::make($this->quizobj($this->student->id), time(), false);

        $this->assertFalse($rule->prevent_access());
    }

    public function test_the_device_check_is_asked_once_per_quiz_per_session(): void {
        global $SESSION;

        \quizaccess_stenproc::save_settings($this->form_data());
        $rule = \quizaccess_stenproc::make($this->quizobj($this->student->id), time(), false);

        $this->assertTrue($rule->is_preflight_check_required(1));

        $rule->notify_preflight_check_passed(1);
        $this->assertFalse($rule->is_preflight_check_required(1));

        // A later attempt is checked again.
        $rule->current_attempt_finished();
        $this->assertTrue($rule->is_preflight_check_required(2));

        unset($SESSION->quizaccess_stenproc_checked);
    }

    public function test_the_attempt_is_refused_without_consent_and_a_working_device(): void {
        \quizaccess_stenproc::save_settings($this->form_data());
        $rule = \quizaccess_stenproc::make($this->quizobj($this->student->id), time(), false);

        $errors = $rule->validate_preflight_check([], [], [], 1);
        $this->assertArrayHasKey('stenprocconsent', $errors);
        $this->assertArrayHasKey('stenprocready', $errors);

        $errors = $rule->validate_preflight_check(
            ['stenprocconsent' => 1, 'stenprocready' => 1],
            [],
            [],
            1
        );
        $this->assertSame([], $errors);
    }

    public function test_the_moodle_app_cannot_take_a_proctored_quiz(): void {
        set_config('apibaseurl', 'https://api.example.com', 'quizaccess_stenproc');
        set_config('apikey', 'test-key', 'quizaccess_stenproc');
        \quizaccess_stenproc::save_settings($this->form_data());
        \core_useragent::instance(true, 'Mozilla/5.0 (Linux; Android 14) MoodleMobile 4.4.0');

        $rule = \quizaccess_stenproc::make($this->quizobj($this->student->id), time(), false);

        // The agent cannot run in the app, so the attempt is refused outright
        // rather than going ahead unproctored.
        $this->assertSame(get_string('appnotsupported', 'quizaccess_stenproc'), $rule->prevent_access());
    }

    public function test_the_app_is_turned_away_before_the_site_is_even_checked(): void {
        // No API address or key. The student is told to open a browser, which
        // they can act on, rather than that the site is not set up, which they
        // cannot.
        \quizaccess_stenproc::save_settings($this->form_data());
        \core_useragent::instance(true, 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) MoodleMobile');

        $rule = \quizaccess_stenproc::make($this->quizobj($this->student->id), time(), false);

        $this->assertSame(get_string('appnotsupported', 'quizaccess_stenproc'), $rule->prevent_access());
    }

    public function test_a_phone_browser_may_take_a_proctored_quiz(): void {
        set_config('apibaseurl', 'https://api.example.com', 'quizaccess_stenproc');
        set_config('apikey', 'test-key', 'quizaccess_stenproc');
        \quizaccess_stenproc::save_settings($this->form_data());
        // Safari on a current iPhone. Only the app is blocked, not phones.
        \core_useragent::instance(true, 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) '
            . 'AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.5 Mobile/15E148 Safari/604.1');

        $rule = \quizaccess_stenproc::make($this->quizobj($this->student->id), time(), false);

        $this->assertFalse($rule->prevent_access());
    }

    public function test_the_student_is_told_the_quiz_is_proctored(): void {
        \quizaccess_stenproc::save_settings($this->form_data());
        $rule = \quizaccess_stenproc::make($this->quizobj($this->student->id), time(), false);

        $this->assertSame(
            [get_string('preflightintro', 'quizaccess_stenproc')],
            $rule->description()
        );
    }
}
