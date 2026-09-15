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
 * English strings for the Stenproc proctoring quiz access rule.
 *
 * @package    quizaccess_stenproc
 * @copyright  Stenproc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['agenturl'] = 'Proctoring agent address';
$string['agenturl_desc'] = 'The address of the Stenproc proctoring script that runs in the student\'s browser.';
$string['apibaseurl'] = 'Stenproc API address';
$string['apibaseurl_desc'] = 'The address of the Stenproc service, for example https://api.stenproc.com';
$string['apikey'] = 'Organisation API key';
$string['apikey_desc'] = 'The key for your organisation, created in Stenproc under API keys. Only the site administrator can see it.';
$string['appnotsupported'] = 'This quiz is proctored and cannot be taken in the Moodle app. Please open it in a web browser.';
$string['cameraconsent'] = 'I understand that my camera, and my screen where shared, will be recorded during this quiz.';
$string['cameraconsentrequired'] = 'You must agree before starting a proctored quiz.';
$string['details'] = 'Details';
$string['enabled'] = 'Proctor this quiz with Stenproc';
$string['enabled_help'] = 'Students are asked for their camera before the attempt starts. Their session is recorded and monitored according to the checks below, and staff can review it afterwards.';
$string['eventtype'] = 'What happened';
$string['evidenceunavailable'] = 'The proctoring report could not be loaded from Stenproc: {$a}';
$string['facedetection'] = 'Check for a missing or extra face';
$string['facedetection_help'] = 'Reports when the student\'s face isn\'t visible, or when more than one face is. This downloads extra software to the student\'s browser the first time.';
$string['gapnotrecorded'] = '{$a} not recorded, while the page changed';
$string['incidents'] = 'Incidents';
$string['maxviolations'] = 'Violations before the attempt is submitted';
$string['maxviolations_help'] = 'How many times a candidate may be caught breaking the rules before the attempt is submitted for them. Each warning is shown to the candidate as it happens, and every one is recorded for the report. Set this to 0 to warn the candidate every time but never submit the attempt automatically, leaving the decision to staff.';
$string['noincidents'] = 'Nothing was reported during this attempt.';
$string['nooverviewattempts'] = 'No proctored attempt has been made at this quiz yet.';
$string['norecordings'] = 'No recordings are available for this attempt.';
$string['nosession'] = 'This attempt has no proctoring session.';
$string['notconfigured'] = 'Stenproc proctoring is not set up on this site yet. Ask an administrator to add the API address and key.';
$string['overview'] = 'Proctoring overview';
$string['overviewclean'] = 'Nothing reported';
$string['overviewflags'] = 'Flags';
$string['overviewflagscount'] = '{$a} flagged';
$string['overviewflagshigh'] = '{$a->total} flagged, {$a->high} serious';
$string['overviewfor'] = 'Proctoring overview: {$a}';
$string['overviewlink'] = 'View the proctoring overview for this quiz';
$string['overviewstudent'] = 'Student';
$string['pagesplitnote'] = 'This quiz is split over more than one page. The recording stops and starts again at every page change, because a browser cannot keep recording across one, and the few seconds in between are not recorded. Putting every question on one page records the attempt in a single piece.';
$string['partlength'] = 'Length';
$string['partnotready'] = 'This part did not finish uploading, so it cannot be played.';
$string['partnumber'] = 'Part';
$string['playercannotplay'] = 'This part could not be played.';
$string['playergap'] = '{$a} not recorded';
$string['playerlinkexpired'] = 'The link to this part has run out. Reload the page to get a fresh one.';
$string['playerpart'] = 'Part {$a}';
$string['playerplaying'] = 'Part {$a->number} of {$a->total}, starting {$a->when}';
$string['pluginname'] = 'Stenproc proctoring';
$string['preflightchecking'] = 'Checking your device...';
$string['preflightfailed'] = 'Your device is not ready for a proctored quiz.';
$string['preflightheading'] = 'Proctoring check';
$string['preflightintro'] = 'This quiz is proctored. Your camera is checked before you begin.';
$string['preflightready'] = 'Your device is ready. You can start the quiz.';
$string['privacy:metadata:quizaccess_stenproc_session'] = 'Links a quiz attempt to its proctoring session in Stenproc.';
$string['privacy:metadata:quizaccess_stenproc_session:attemptid'] = 'The quiz attempt being proctored.';
$string['privacy:metadata:quizaccess_stenproc_session:sessionid'] = 'The identifier of the proctoring session in Stenproc.';
$string['privacy:metadata:quizaccess_stenproc_session:status'] = 'Whether the proctoring session is open or closed.';
$string['privacy:metadata:quizaccess_stenproc_session:timecreated'] = 'When the proctoring session was started.';
$string['privacy:metadata:stenproc'] = 'Proctoring data sent to Stenproc, an external service, so the attempt can be monitored and reviewed.';
$string['privacy:metadata:stenproc:attemptid'] = 'The quiz attempt identifier, so the session belongs to one attempt.';
$string['privacy:metadata:stenproc:events'] = 'Proctoring events during the attempt, such as leaving the quiz or a face not being visible.';
$string['privacy:metadata:stenproc:fullname'] = 'The user\'s name, so staff can recognise them while proctoring.';
$string['privacy:metadata:stenproc:userid'] = 'The user\'s Moodle identifier, so the attempt can be matched to the right person.';
$string['privacy:metadata:stenproc:video'] = 'Camera video, and screen video where screen sharing is on, recorded during the attempt.';
$string['proctoringrequired'] = 'This quiz is proctored. Start proctoring to answer the questions.';
$string['proctoringrequiredhelp'] = 'The questions stay locked until proctoring is running.';
$string['recording'] = 'Record the session';
$string['recording_help'] = 'The camera, and the screen where shared, are recorded and saved in your organisation\'s own storage. Your organisation must connect its storage in Stenproc first.';
$string['recordings'] = 'Recordings';
$string['recordingscreen'] = 'Screen';
$string['recordingsummary'] = '{$a->count} parts, {$a->recorded} recorded';
$string['recordingsummaryone'] = 'One part, {$a->recorded} recorded';
$string['recordingwebcam'] = 'Camera';
$string['report'] = 'Proctoring report';
$string['reportfor'] = 'Proctoring report for {$a}';
$string['screenshare'] = 'Screen sharing';
$string['screenshare_help'] = 'The student shares their whole screen. Not available on phones and tablets, where the attempt continues with camera only.';
$string['sessionfailed'] = 'Proctoring could not be started, so this attempt cannot continue. Please tell your teacher.';
$string['sessionstatus'] = 'Session status';
$string['severity'] = 'Severity';
$string['socketurl'] = 'Live video address';
$string['socketurl_desc'] = 'Where students\' browsers send live video for proctors to watch. Leave this empty to use the Stenproc API address, which is usually what you want.';
$string['startbutton'] = 'Start proctoring';
$string['startproctoring'] = 'Proctoring has not started yet for this quiz.';
$string['stenproc:viewreport'] = 'View the Stenproc proctoring report for an attempt';
$string['systemchecknotpassed'] = 'Finish the proctoring check before starting the quiz.';
$string['tabswitch'] = 'Report leaving the quiz';
$string['tabswitch_help'] = 'Reports when the student leaves the quiz tab, leaves fullscreen, or opens developer tools. Phones are given longer before leaving counts, because notifications hide the page.';
$string['time'] = 'Time';
$string['viewreportlink'] = 'View proctoring report';
$string['violation:devtools_detected'] = 'Developer tools appear to be open';
$string['violation:face_absent'] = 'No face was visible on camera';
$string['violation:fullscreen_exit'] = 'You left fullscreen';
$string['violation:multiple_faces'] = 'More than one face was visible on camera';
$string['violation:screen_share_stopped'] = 'You stopped sharing your screen';
$string['violation:tab_switch'] = 'You left the quiz tab';
$string['violationsubmitted'] = 'The attempt was submitted automatically after {$a} violations.';
$string['violationwarning'] = 'Warning {$a->count} of {$a->max}: {$a->reason}. The attempt is submitted automatically at {$a->max}.';
$string['violationwarningonly'] = 'Recorded: {$a->reason}. This has been reported to your teacher.';
$string['watchrecording'] = 'Watch recording';
$string['webcam'] = 'Camera monitoring';
$string['webcam_help'] = 'Proctors can watch the student live while the attempt is in progress.';
