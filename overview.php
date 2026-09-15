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
 * Every proctored attempt at one quiz, so staff can see which are worth
 * opening without visiting each in turn.
 *
 * Moodle's own results table cannot be extended by an access rule, so this
 * stands beside it rather than inside it.
 *
 * @package    quizaccess_stenproc
 * @copyright  Stenproc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

$cmid = required_param('cmid', PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'quiz');
$quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('quizaccess/stenproc:viewreport', $context);

$PAGE->set_url('/mod/quiz/accessrule/stenproc/overview.php', ['cmid' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('overview', 'quizaccess_stenproc'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('overviewfor', 'quizaccess_stenproc', format_string($quiz->name)));

try {
    $attempts = \quizaccess_stenproc\api_client::get_quiz_attempts($quiz->id);
} catch (Exception $e) {
    echo $OUTPUT->notification(
        get_string('evidenceunavailable', 'quizaccess_stenproc', $e->getMessage()),
        \core\output\notification::NOTIFY_ERROR
    );
    echo $OUTPUT->footer();
    die();
}

if (empty($attempts)) {
    echo $OUTPUT->notification(
        get_string('nooverviewattempts', 'quizaccess_stenproc'),
        \core\output\notification::NOTIFY_INFO
    );
    echo $OUTPUT->footer();
    die();
}

// Stenproc knows the attempt by its Moodle id, which is what links a row back
// to the student who sat it.
$attemptids = [];
foreach ($attempts as $attempt) {
    if (!empty($attempt['externalAttemptId'])) {
        $attemptids[] = (int) $attempt['externalAttemptId'];
    }
}

$attemptrecords = [];
$users = [];
if ($attemptids) {
    $attemptrecords = $DB->get_records_list('quiz_attempts', 'id', $attemptids);
    $userids = array_unique(array_map(function($record) {
        return $record->userid;
    }, $attemptrecords));
    if ($userids) {
        $users = $DB->get_records_list('user', 'id', $userids);
    }
}

$table = new html_table();
$table->head = [
    get_string('overviewstudent', 'quizaccess_stenproc'),
    get_string('overviewflags', 'quizaccess_stenproc'),
    get_string('sessionstatus', 'quizaccess_stenproc'),
    '',
];
$table->attributes['class'] = 'generaltable';

foreach ($attempts as $attempt) {
    $attemptid = isset($attempt['externalAttemptId']) ? (int) $attempt['externalAttemptId'] : 0;
    $record = isset($attemptrecords[$attemptid]) ? $attemptrecords[$attemptid] : null;

    // An attempt Moodle no longer has, or one from another quiz, is not this
    // page's to show.
    if (!$record || (int) $record->quiz !== (int) $quiz->id) {
        continue;
    }

    $student = isset($users[$record->userid]) ? fullname($users[$record->userid]) : '';
    $summary = isset($attempt['summary']) ? $attempt['summary'] : [];
    $total = isset($summary['total']) ? (int) $summary['total'] : 0;
    $high = isset($summary['high']) ? (int) $summary['high'] : 0;

    if ($total === 0) {
        $flags = html_writer::span(get_string('overviewclean', 'quizaccess_stenproc'), 'text-muted');
    } else {
        $label = $high > 0
            ? get_string('overviewflagshigh', 'quizaccess_stenproc', (object) ['total' => $total, 'high' => $high])
            : get_string('overviewflagscount', 'quizaccess_stenproc', $total);
        $flags = html_writer::span($label, $high > 0 ? 'badge badge-danger bg-danger text-white' : 'badge badge-warning');
    }

    $link = html_writer::link(
        new moodle_url('/mod/quiz/accessrule/stenproc/report.php', ['attemptid' => $attemptid]),
        get_string('viewreportlink', 'quizaccess_stenproc'),
        ['class' => 'btn btn-secondary btn-sm']
    );

    $table->data[] = [
        $student,
        $flags,
        isset($attempt['status']) ? s($attempt['status']) : '',
        $link,
    ];
}

if (empty($table->data)) {
    echo $OUTPUT->notification(
        get_string('nooverviewattempts', 'quizaccess_stenproc'),
        \core\output\notification::NOTIFY_INFO
    );
} else {
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
