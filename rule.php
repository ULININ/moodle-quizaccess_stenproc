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
 * The Stenproc proctoring quiz access rule.
 *
 * @package    quizaccess_stenproc
 * @copyright  Stenproc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

// Moodle 4.2 moved the access rule base class into a namespace, so pick
// whichever this site has.
if (class_exists('\mod_quiz\local\access_rule_base')) {
    class_alias('\mod_quiz\local\access_rule_base', 'quizaccess_stenproc_base_class');
} else {
    require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');
    class_alias('quiz_access_rule_base', 'quizaccess_stenproc_base_class');
}

/**
 * Proctors a quiz attempt with Stenproc.
 *
 * Moodle keeps the questions, the timing and the marks. This rule asks
 * Stenproc to open a proctoring session for the attempt, loads the agent that
 * watches and records it in the student's browser, and closes the session when
 * the attempt finishes.
 */
class quizaccess_stenproc extends quizaccess_stenproc_base_class {
    /** @var stdClass proctoring settings for this quiz. */
    protected $proctoring;

    /**
     * Parameter types are left off on purpose: Moodle 4.2 renamed the classes
     * these arguments belong to, and leaving them out works on both.
     *
     * @param object $quizobj
     * @param int $timenow
     * @param bool $canignoretimelimits
     * @return quizaccess_stenproc|null
     */
    public static function make($quizobj, $timenow, $canignoretimelimits) {
        $quiz = $quizobj->get_quiz();
        if (empty($quiz->stenprocenabled)) {
            return null;
        }
        return new self($quizobj, $timenow);
    }

    /**
     * The checks this quiz turned on, as Stenproc names them.
     *
     * @return array
     */
    public function get_checks() {
        $quiz = $this->quizobj->get_quiz();
        return [
            'webcam'        => !empty($quiz->stenprocwebcam),
            'screen'        => !empty($quiz->stenprocscreenshare),
            'recording'     => !empty($quiz->stenprocrecording),
            'faceDetection' => !empty($quiz->stenprocfacedetection),
            'tabSwitch'     => !empty($quiz->stenproctabswitch),
        ];
    }

    // Quiz settings form.

    /**
     * Adds the Stenproc proctoring section to the quiz settings form.
     *
     * @param \mod_quiz_mod_form $quizform
     * @param MoodleQuickForm $mform
     */
    public static function add_settings_form_fields($quizform, MoodleQuickForm $mform) {
        global $OUTPUT;

        $mform->addElement('header', 'stenprocheader', get_string('pluginname', 'quizaccess_stenproc'));

        $mform->addElement('selectyesno', 'stenprocenabled', get_string('enabled', 'quizaccess_stenproc'));
        $mform->addHelpButton('stenprocenabled', 'enabled', 'quizaccess_stenproc');
        $mform->setDefault('stenprocenabled', 0);

        $checks = [
            'stenprocwebcam'        => 'webcam',
            'stenprocscreenshare'   => 'screenshare',
            'stenprocrecording'     => 'recording',
            'stenprocfacedetection' => 'facedetection',
            'stenproctabswitch'     => 'tabswitch',
        ];
        foreach ($checks as $field => $stringid) {
            $mform->addElement('advcheckbox', $field, get_string($stringid, 'quizaccess_stenproc'));
            $mform->addHelpButton($field, $stringid, 'quizaccess_stenproc');
            $mform->hideIf($field, 'stenprocenabled', 'eq', 0);
        }
        $mform->setDefault('stenprocwebcam', 1);
        $mform->setDefault('stenproctabswitch', 1);

        // A page change ends the recording and starts another, because the
        // browser cannot keep recording across it. Shown only when the quiz is
        // actually split over pages, and only when proctoring is on.
        $mform->addElement(
            'static',
            'stenprocpagenote',
            '',
            $OUTPUT->notification(get_string('pagesplitnote', 'quizaccess_stenproc'), 'info', false)
        );
        $mform->hideIf('stenprocpagenote', 'stenprocenabled', 'eq', 0);
        if ($mform->elementExists('questionsperpage')) {
            $mform->hideIf('stenprocpagenote', 'questionsperpage', 'eq', 0);
        }
    }

    /**
     * Stores this quiz's proctoring settings, or removes them when it is off.
     *
     * @param \stdClass $quiz
     */
    public static function save_settings($quiz) {
        global $DB;

        if (empty($quiz->stenprocenabled)) {
            $DB->delete_records('quizaccess_stenproc', ['quizid' => $quiz->id]);
            return;
        }

        $record = (object) [
            'quizid'        => $quiz->id,
            'enabled'       => 1,
            'webcam'        => empty($quiz->stenprocwebcam) ? 0 : 1,
            'screenshare'   => empty($quiz->stenprocscreenshare) ? 0 : 1,
            'recording'     => empty($quiz->stenprocrecording) ? 0 : 1,
            'facedetection' => empty($quiz->stenprocfacedetection) ? 0 : 1,
            'tabswitch'     => empty($quiz->stenproctabswitch) ? 0 : 1,
        ];

        $existing = $DB->get_record('quizaccess_stenproc', ['quizid' => $quiz->id]);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('quizaccess_stenproc', $record);
        } else {
            $DB->insert_record('quizaccess_stenproc', $record);
        }
    }

    /**
     * Removes this quiz's proctoring settings when the quiz is deleted.
     *
     * @param \stdClass $quiz
     */
    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records('quizaccess_stenproc', ['quizid' => $quiz->id]);
    }

    /**
     * Tells Moodle how to load this rule's settings alongside the quiz.
     *
     * @param int $quizid
     * @return array the fields, joins and parameters to add to the quiz query
     */
    public static function get_settings_sql($quizid) {
        return [
            'stenproc.enabled AS stenprocenabled, '
                . 'stenproc.webcam AS stenprocwebcam, '
                . 'stenproc.screenshare AS stenprocscreenshare, '
                . 'stenproc.recording AS stenprocrecording, '
                . 'stenproc.facedetection AS stenprocfacedetection, '
                . 'stenproc.tabswitch AS stenproctabswitch',
            'LEFT JOIN {quizaccess_stenproc} stenproc ON stenproc.quizid = quiz.id',
            [],
        ];
    }

    // What students see and can do.

    /**
     * What the student is told about proctoring before they begin.
     *
     * @return array of strings
     */
    public function description() {
        return [get_string('preflightintro', 'quizaccess_stenproc')];
    }

    /**
     * Blocks the attempt when it cannot be proctored at all.
     *
     * @return string|false the reason, or false to allow the attempt
     */
    public function prevent_access() {
        // The agent can't run in the Moodle app, so a proctored attempt has to
        // be taken in a web browser.
        if (self::is_mobile_app_request()) {
            return get_string('appnotsupported', 'quizaccess_stenproc');
        }

        if (!\quizaccess_stenproc\api_client::is_configured()) {
            return get_string('notconfigured', 'quizaccess_stenproc');
        }

        return false;
    }

    /**
     * True when the request comes from the Moodle app rather than a browser.
     *
     * @return bool
     */
    protected static function is_mobile_app_request() {
        if (defined('WS_SERVER') && WS_SERVER) {
            return true;
        }
        $useragent = \core_useragent::get_user_agent_string();
        return $useragent !== false && strpos($useragent, 'MoodleMobile') !== false;
    }

    // The check before an attempt starts.

    /**
     * Whether the student still has to pass the device check.
     *
     * @param int $attemptid
     * @return bool
     */
    public function is_preflight_check_required($attemptid) {
        global $SESSION;

        // Asked once per quiz per session. Without this, Moodle sends the
        // student back to the check on every page of the attempt.
        return empty($SESSION->quizaccess_stenproc_checked[$this->quiz->id]);
    }

    /**
     * Moodle calls this once the student has passed the check.
     *
     * @param int $attemptid
     */
    public function notify_preflight_check_passed($attemptid) {
        global $SESSION;
        $SESSION->quizaccess_stenproc_checked[$this->quiz->id] = true;
    }

    /**
     * The next attempt is checked again.
     */
    public function current_attempt_finished() {
        global $SESSION;
        unset($SESSION->quizaccess_stenproc_checked[$this->quiz->id]);
    }

    /**
     * Builds the device check the student sees before the attempt starts.
     *
     * @param \mod_quiz\form\preflight_check_form $quizform
     * @param MoodleQuickForm $mform
     * @param int $attemptid
     */
    public function add_preflight_check_form_fields($quizform, MoodleQuickForm $mform, $attemptid) {
        global $PAGE;

        $mform->addElement('header', 'stenprocpreflight', get_string('preflightheading', 'quizaccess_stenproc'));
        $mform->addElement('static', 'stenprocintro', '', get_string('preflightintro', 'quizaccess_stenproc'));

        $mform->addElement('html', html_writer::div(
            get_string('preflightchecking', 'quizaccess_stenproc'),
            'alert alert-info',
            ['id' => 'stenproc-preflight-status']
        ));

        $mform->addElement('checkbox', 'stenprocconsent', '', get_string('cameraconsent', 'quizaccess_stenproc'));

        // The agent sets this to 1 once the device passes its checks.
        $mform->addElement('hidden', 'stenprocready', 0);
        $mform->setType('stenprocready', PARAM_INT);

        $PAGE->requires->js_call_amd('quizaccess_stenproc/proctoring', 'initPreflight', [
            \quizaccess_stenproc\api_client::get_browser_config($this->get_checks()),
        ]);
    }

    /**
     * Refuses the attempt until the student consents and the device passes.
     *
     * @param array $data
     * @param array $files
     * @param array $errors
     * @param int $attemptid
     * @return array the errors, with any of this rule's own added
     */
    public function validate_preflight_check($data, $files, $errors, $attemptid) {
        if (empty($data['stenprocconsent'])) {
            $errors['stenprocconsent'] = get_string('cameraconsentrequired', 'quizaccess_stenproc');
        }
        if (empty($data['stenprocready'])) {
            $errors['stenprocready'] = get_string('systemchecknotpassed', 'quizaccess_stenproc');
        }
        return $errors;
    }

    // During the attempt.

    /**
     * Opens or resumes the proctoring session for this attempt and loads the
     * agent. Moodle calls this each time an attempt page is shown, and
     * reopening the same attempt resumes the same session.
     *
     * @param moodle_page $page
     */
    public function setup_attempt_page($page) {
        global $DB, $USER;

        $attemptid = optional_param('attempt', 0, PARAM_INT);
        if (!$attemptid) {
            return;
        }

        // Only a live attempt is proctored. Moodle also loads this rule for the
        // summary and review pages, where the session is finished or belongs to
        // someone else's attempt.
        $attempt = $DB->get_record('quiz_attempts', [
            'id' => $attemptid,
            'quiz' => $this->quiz->id,
            'userid' => $USER->id,
        ]);
        if (!$attempt || $attempt->state !== 'inprogress') {
            return;
        }

        try {
            $session = \quizaccess_stenproc\api_client::open_session(
                $attemptid,
                $this->quizobj,
                $USER,
                $this->get_checks()
            );
        } catch (Exception $e) {
            debugging('Stenproc proctoring could not start: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new moodle_exception('sessionfailed', 'quizaccess_stenproc');
        }

        $page->requires->js_call_amd('quizaccess_stenproc/proctoring', 'initAttempt', [$session]);
    }
}
