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

// Incidents.
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

// Recordings.
echo $OUTPUT->heading(get_string('recordings', 'quizaccess_stenproc'), 3);

$recordings = isset($evidence['recordings']) ? $evidence['recordings'] : [];
$timeline = \quizaccess_stenproc\recording_timeline::build($recordings);

if (empty($timeline)) {
    echo html_writer::tag('p', get_string('norecordings', 'quizaccess_stenproc'));
} else {
    $clock = get_string('strftimetime', 'langconfig');

    foreach ($timeline as $group) {
        echo html_writer::tag('h4', $group['type'] === 'screen'
            ? get_string('recordingscreen', 'quizaccess_stenproc')
            : get_string('recordingwebcam', 'quizaccess_stenproc'));

        $summary = (object) [
            'count' => count($group['parts']),
            'recorded' => \quizaccess_stenproc\recording_timeline::readable($group['recorded']),
        ];
        echo html_writer::tag('p', count($group['parts']) === 1
            ? get_string('recordingsummaryone', 'quizaccess_stenproc', $summary)
            : get_string('recordingsummary', 'quizaccess_stenproc', $summary));

        $table = new html_table();
        $table->head = [
            get_string('partnumber', 'quizaccess_stenproc'),
            get_string('time', 'quizaccess_stenproc'),
            get_string('partlength', 'quizaccess_stenproc'),
            '',
        ];

        foreach ($group['parts'] as $part) {
            // Nothing is recorded while the browser loads the next page. The gap
            // is stated rather than smoothed over: a proctor needs to know which
            // seconds of the attempt nobody saw.
            if ($part['gapbefore'] > 0) {
                $gap = new html_table_row([
                    new html_table_cell(get_string(
                        'gapnotrecorded',
                        'quizaccess_stenproc',
                        \quizaccess_stenproc\recording_timeline::readable($part['gapbefore'])
                    )),
                ]);
                $gap->cells[0]->colspan = 4;
                $gap->attributes['class'] = 'text-muted';
                $table->data[] = $gap;
            }

            $when = '';
            if ($part['startedat'] !== null) {
                $when = userdate($part['startedat'], $clock);
                if ($part['endedat'] !== null) {
                    $when .= ' - ' . userdate($part['endedat'], $clock);
                }
            }

            // A part that never finished uploading has no link. Saying so beats
            // leaving it out of the list, which is what used to happen.
            $action = $part['playable']
                ? html_writer::link(
                    new moodle_url($part['url']),
                    get_string('watchrecording', 'quizaccess_stenproc'),
                    ['class' => 'btn btn-secondary btn-sm', 'target' => '_blank', 'rel' => 'noreferrer noopener']
                )
                : html_writer::tag(
                    'span',
                    get_string('partnotready', 'quizaccess_stenproc'),
                    ['class' => 'text-muted']
                );

            $table->data[] = [
                $part['number'],
                s($when),
                s(\quizaccess_stenproc\recording_timeline::readable($part['length'])),
                $action,
            ];
        }

        echo html_writer::table($table);
    }
}

echo $OUTPUT->footer();
