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
 * The proctoring report for one quiz attempt.
 *
 * @package    quizaccess_stenproc
 * @copyright  Stenproc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

$attemptid = required_param('attemptid', PARAM_INT);

$attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);
$quiz    = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);
$cm      = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('quizaccess/stenproc:viewreport', $context);

$student = $DB->get_record('user', ['id' => $attempt->userid], '*', MUST_EXIST);

$PAGE->set_url('/mod/quiz/accessrule/stenproc/report.php', ['attemptid' => $attemptid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('report', 'quizaccess_stenproc'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reportfor', 'quizaccess_stenproc', fullname($student)));

$evidence = null;
try {
    $evidence = \quizaccess_stenproc\api_client::get_evidence($attemptid);
} catch (Exception $e) {
    echo $OUTPUT->notification(
        get_string('evidenceunavailable', 'quizaccess_stenproc', $e->getMessage()),
        \core\output\notification::NOTIFY_ERROR
    );
}

if ($evidence === null) {
    echo $OUTPUT->notification(get_string('nosession', 'quizaccess_stenproc'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    die();
}

$session = isset($evidence['session']) ? $evidence['session'] : [];
if (!empty($session['status'])) {
    echo html_writer::tag('p', get_string('sessionstatus', 'quizaccess_stenproc') . ': ' . s($session['status']));
}

// ── Incidents ───────────────────────────────────────────────────────────────
echo $OUTPUT->heading(get_string('incidents', 'quizaccess_stenproc'), 3);

$incidents = isset($evidence['incidents']) ? $evidence['incidents'] : [];
if (empty($incidents)) {
    echo html_writer::tag('p', get_string('noincidents', 'quizaccess_stenproc'));
} else {
    $table = new html_table();
    $table->head = [
        get_string('time', 'quizaccess_stenproc'),
        get_string('eventtype', 'quizaccess_stenproc'),
        get_string('severity', 'quizaccess_stenproc'),
        get_string('details', 'quizaccess_stenproc'),
    ];
    foreach ($incidents as $incident) {
        $when = empty($incident['timestamp']) ? '' : userdate(strtotime($incident['timestamp']));
        $table->data[] = [
            s($when),
            s(str_replace('_', ' ', isset($incident['eventType']) ? $incident['eventType'] : '')),
            s(isset($incident['severity']) ? $incident['severity'] : ''),
            s(isset($incident['details']) ? $incident['details'] : ''),
        ];
    }
    echo html_writer::table($table);
}

// ── Recordings ──────────────────────────────────────────────────────────────
echo $OUTPUT->heading(get_string('recordings', 'quizaccess_stenproc'), 3);

$recordings = isset($evidence['recordings']) ? $evidence['recordings'] : [];
$playable = [];
foreach ($recordings as $recording) {
    if (!empty($recording['url'])) {
        $playable[] = $recording;
    }
}

if (empty($playable)) {
    echo html_writer::tag('p', get_string('norecordings', 'quizaccess_stenproc'));
} else {
    foreach ($playable as $recording) {
        $label = s(isset($recording['recordingType']) ? $recording['recordingType'] : '');
        echo html_writer::tag('h4', $label);
        // The link is a temporary address for the organisation's own storage.
        echo html_writer::link(
            new moodle_url($recording['url']),
            get_string('watchrecording', 'quizaccess_stenproc'),
            ['class' => 'btn btn-secondary', 'target' => '_blank', 'rel' => 'noreferrer noopener']
        );
    }
}

echo $OUTPUT->footer();
