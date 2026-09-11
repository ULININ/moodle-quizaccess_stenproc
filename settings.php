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
 * Site-wide settings for Stenproc proctoring.
 *
 * @package    quizaccess_stenproc
 * @copyright  Stenproc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'quizaccess_stenproc/apibaseurl',
        get_string('apibaseurl', 'quizaccess_stenproc'),
        get_string('apibaseurl_desc', 'quizaccess_stenproc'),
        'https://api.stenproc.com',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'quizaccess_stenproc/apikey',
        get_string('apikey', 'quizaccess_stenproc'),
        get_string('apikey_desc', 'quizaccess_stenproc'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'quizaccess_stenproc/socketurl',
        get_string('socketurl', 'quizaccess_stenproc'),
        get_string('socketurl_desc', 'quizaccess_stenproc'),
        'https://api.stenproc.com',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'quizaccess_stenproc/agenturl',
        get_string('agenturl', 'quizaccess_stenproc'),
        get_string('agenturl_desc', 'quizaccess_stenproc'),
        'https://app.stenproc.com/agent/stenproc-proctoring-agent.js',
        PARAM_URL
    ));
}
