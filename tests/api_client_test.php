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
 * Tests for the Stenproc API client.
 *
 * Nothing here reaches the network. Every case is one the client answers on its
 * own, which is also where a mistake would quietly send a student's attempt to
 * the wrong place, or leak the site's API key into the browser.
 *
 * @package    quizaccess_stenproc
 * @copyright  Stenproc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_stenproc\api_client
 */
final class api_client_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Records a proctoring session against an attempt id.
     *
     * @param int $attemptid
     * @param string $status
     * @return int the new row's id
     */
    protected function add_session($attemptid, $status = 'open') {
        global $DB;

        return $DB->insert_record(api_client::TABLE, (object) [
            'attemptid'    => $attemptid,
            'sessionid'    => '55',
            'status'       => $status,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    public function test_a_site_without_an_address_or_key_is_not_configured(): void {
        $this->assertFalse(api_client::is_configured());

        set_config('apibaseurl', 'https://api.example.com', 'quizaccess_stenproc');
        $this->assertFalse(api_client::is_configured());

        set_config('apikey', 'test-key', 'quizaccess_stenproc');
        $this->assertTrue(api_client::is_configured());
    }

    public function test_the_browser_config_never_carries_the_api_key(): void {
        set_config('apibaseurl', 'https://api.example.com', 'quizaccess_stenproc');
        set_config('apikey', 'super-secret-key', 'quizaccess_stenproc');

        $config = api_client::get_browser_config(['webcam' => true]);

        $this->assertStringNotContainsString('super-secret-key', json_encode($config));
    }

    public function test_the_browser_config_trims_trailing_slashes(): void {
        set_config('apibaseurl', 'https://api.example.com/', 'quizaccess_stenproc');
        set_config('apikey', 'test-key', 'quizaccess_stenproc');

        $config = api_client::get_browser_config([]);

        $this->assertSame('https://api.example.com', $config['apiBaseUrl']);
    }

    public function test_the_live_video_address_falls_back_to_the_api_address(): void {
        set_config('apibaseurl', 'https://api.example.com', 'quizaccess_stenproc');
        set_config('apikey', 'test-key', 'quizaccess_stenproc');

        $config = api_client::get_browser_config([]);
        $this->assertSame('https://api.example.com', $config['socketUrl']);

        set_config('socketurl', 'https://live.example.com', 'quizaccess_stenproc');
        $config = api_client::get_browser_config([]);
        $this->assertSame('https://live.example.com', $config['socketUrl']);
    }

    public function test_the_browser_config_passes_on_the_checks(): void {
        set_config('apibaseurl', 'https://api.example.com', 'quizaccess_stenproc');
        set_config('apikey', 'test-key', 'quizaccess_stenproc');

        $config = api_client::get_browser_config(['webcam' => true, 'screen' => false]);

        $this->assertSame(['webcam' => true, 'screen' => false], $config['checks']);
    }

    public function test_forgetting_an_attempt_removes_only_its_own_session(): void {
        global $DB;

        $this->add_session(1);
        $this->add_session(2);

        api_client::forget_attempt(1);

        $this->assertFalse($DB->record_exists(api_client::TABLE, ['attemptid' => 1]));
        $this->assertTrue($DB->record_exists(api_client::TABLE, ['attemptid' => 2]));
    }

    public function test_closing_an_unproctored_attempt_does_nothing(): void {
        // No row, so there is nothing to close and no call to make. If this
        // reached the network it would throw, because the site is unconfigured.
        api_client::close_session(999, 'no such attempt');

        $this->assertTrue(true);
    }

    public function test_closing_an_already_closed_session_does_nothing(): void {
        global $DB;

        $this->add_session(1, 'closed');

        api_client::close_session(1, 'again');

        $record = $DB->get_record(api_client::TABLE, ['attemptid' => 1]);
        $this->assertSame('closed', $record->status);
    }

    public function test_there_is_no_evidence_for_an_unproctored_attempt(): void {
        $this->assertNull(api_client::get_evidence(999));
    }
}
