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
 * Talks to the Stenproc proctoring service.
 *
 * @package    quizaccess_stenproc
 * @copyright  Stenproc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_stenproc;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Opens, closes and reads proctoring sessions, using this site's organisation
 * API key. Video never passes through Moodle: the student's browser sends it
 * straight to Stenproc and to the organisation's own storage.
 */
class api_client {
    /** @var string table linking an attempt to its proctoring session. */
    public const TABLE = 'quizaccess_stenproc_session';

    /** @var int seconds to wait for the Stenproc API. */
    public const TIMEOUT = 10;

    /**
     * True when an administrator has entered the API address and key.
     *
     * @return bool
     */
    public static function is_configured() {
        $config = get_config('quizaccess_stenproc');
        return !empty($config->apibaseurl) && !empty($config->apikey);
    }

    /**
     * Calls the Stenproc API and returns the "data" part of its reply.
     *
     * @param string $method GET or POST
     * @param string $path path below the API address
     * @param array|null $payload request body
     * @return array
     * @throws \Exception when the service is unreachable or refuses the request
     */
    protected static function request($method, $path, array $payload = null) {
        $config = get_config('quizaccess_stenproc');
        if (empty($config->apibaseurl) || empty($config->apikey)) {
            throw new \Exception(get_string('notconfigured', 'quizaccess_stenproc'));
        }

        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/json', 'X-Api-Key: ' . $config->apikey]);
        $options = [
            'CURLOPT_TIMEOUT' => self::TIMEOUT,
            'CURLOPT_CONNECTTIMEOUT' => self::TIMEOUT,
            'CURLOPT_FOLLOWLOCATION' => 0,
        ];

        $url = rtrim($config->apibaseurl, '/') . $path;
        if ($method === 'GET') {
            $response = $curl->get($url, [], $options);
        } else {
            $response = $curl->post($url, $payload === null ? '' : json_encode($payload), $options);
        }

        if ($curl->get_errno()) {
            throw new \Exception('Stenproc could not be reached: ' . $curl->error);
        }

        $info = $curl->get_info();
        $status = isset($info['http_code']) ? (int) $info['http_code'] : 0;
        $decoded = json_decode($response, true);

        if ($status < 200 || $status >= 300) {
            $message = isset($decoded['error']['message']) ? $decoded['error']['message'] : 'HTTP ' . $status;
            throw new \Exception($message);
        }

        return isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];
    }

    /**
     * Settings the agent needs in the student's browser. Holds no secrets: the
     * API key stays on the server, and the browser uses a session token.
     *
     * @param array $checks
     * @return array
     */
    public static function get_browser_config(array $checks) {
        $config = get_config('quizaccess_stenproc');
        $apibaseurl = rtrim(isset($config->apibaseurl) ? $config->apibaseurl : '', '/');

        return [
            'apiBaseUrl' => $apibaseurl,
            'socketUrl'  => !empty($config->socketurl) ? rtrim($config->socketurl, '/') : $apibaseurl,
            'agentUrl'   => isset($config->agenturl) ? $config->agenturl : '',
            'checks'     => $checks,
            'strings'    => [
                'ready'           => get_string('preflightready', 'quizaccess_stenproc'),
                'failed'          => get_string('preflightfailed', 'quizaccess_stenproc'),
                'startproctoring' => get_string('startproctoring', 'quizaccess_stenproc'),
                'startbutton'     => get_string('startbutton', 'quizaccess_stenproc'),
                'required'        => get_string('proctoringrequired', 'quizaccess_stenproc'),
                // Sent as patterns the page fills in: the counts are only
                // known in the browser, and this keeps the wording translatable.
                'warning'         => get_string('violationwarning', 'quizaccess_stenproc', (object) [
                    'count'  => '{count}',
                    'max'    => '{max}',
                    'reason' => '{reason}',
                ]),
                'warningonly'     => get_string('violationwarningonly', 'quizaccess_stenproc', (object) [
                    'reason' => '{reason}',
                ]),
                'submitted'       => get_string('violationsubmitted', 'quizaccess_stenproc', '{count}'),
                'violations'      => [
                    'tab_switch'          => get_string('violation:tab_switch', 'quizaccess_stenproc'),
                    'fullscreen_exit'     => get_string('violation:fullscreen_exit', 'quizaccess_stenproc'),
                    'devtools_detected'   => get_string('violation:devtools_detected', 'quizaccess_stenproc'),
                    'face_absent'         => get_string('violation:face_absent', 'quizaccess_stenproc'),
                    'multiple_faces'      => get_string('violation:multiple_faces', 'quizaccess_stenproc'),
                    'screen_share_stopped' => get_string('violation:screen_share_stopped', 'quizaccess_stenproc'),
                ],
            ],
        ];
    }

    /**
     * Opens the proctoring session for an attempt, or resumes it when the page
     * is loaded again, and remembers it against the attempt.
     *
     * @param int $attemptid
     * @param object $quizobj
     * @param \stdClass $user
     * @param array $checks
     * @return array settings for the agent, including its session token
     */
    public static function open_session($attemptid, $quizobj, \stdClass $user, array $checks) {
        global $DB;

        $quiz = $quizobj->get_quiz();
        $data = self::request('POST', '/proctoring/sessions', [
            'platform'          => 'moodle',
            'externalUserId'    => (string) $user->id,
            'externalCourseId'  => (string) $quiz->course,
            'externalQuizId'    => (string) $quiz->id,
            'externalAttemptId' => (string) $attemptid,
            'quizTitle'         => $quiz->name,
            'timeLimitMinutes'  => empty($quiz->timelimit) ? 0 : (int) round($quiz->timelimit / 60),
            'firstName'         => $user->firstname,
            'lastName'          => $user->lastname,
            'checks'            => $checks,
        ]);

        if (empty($data['sessionId']) || empty($data['token'])) {
            throw new \Exception('Stenproc did not return a proctoring session');
        }

        $now = time();
        $record = $DB->get_record(self::TABLE, ['attemptid' => $attemptid]);
        if ($record) {
            $record->sessionid = (string) $data['sessionId'];
            $record->status = isset($data['status']) ? $data['status'] : 'open';
            $record->timemodified = $now;
            $DB->update_record(self::TABLE, $record);
        } else {
            $DB->insert_record(self::TABLE, (object) [
                'attemptid'    => $attemptid,
                'sessionid'    => (string) $data['sessionId'],
                'status'       => isset($data['status']) ? $data['status'] : 'open',
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }

        $browserconfig = self::get_browser_config(isset($data['checks']) ? $data['checks'] : $checks);
        $browserconfig['sessionId'] = $data['sessionId'];
        $browserconfig['token'] = $data['token'];
        return $browserconfig;
    }

    /**
     * Closes the proctoring session for a finished attempt. Stenproc completes
     * any recordings still uploading and seals the incident log.
     *
     * @param int $attemptid
     * @param string $reason
     */
    public static function close_session($attemptid, $reason = '') {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['attemptid' => $attemptid]);
        if (!$record || $record->status === 'closed') {
            return;
        }

        self::request('POST', '/proctoring/sessions/' . rawurlencode($record->sessionid) . '/close', [
            'reason' => $reason,
        ]);

        $record->status = 'closed';
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);
    }

    /**
     * The incidents and recordings for an attempt, or null when it wasn't proctored.
     *
     * @param int $attemptid
     * @return array|null
     */
    public static function get_evidence($attemptid) {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['attemptid' => $attemptid]);
        if (!$record) {
            return null;
        }

        return self::request('GET', '/proctoring/sessions/' . rawurlencode($record->sessionid) . '/evidence');
    }

    /**
     * Forgets the link between an attempt and its proctoring session. The
     * recordings themselves live in the organisation's storage and are kept or
     * removed there.
     *
     * @param int $attemptid
     */
    public static function forget_attempt($attemptid) {
        global $DB;
        $DB->delete_records(self::TABLE, ['attemptid' => $attemptid]);
    }
}
