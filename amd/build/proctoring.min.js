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
 * Runs the Stenproc proctoring agent inside the quiz.
 *
 * @module     quizaccess_stenproc/proctoring
 * @copyright  Stenproc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/log'], function(Log) {

    var agent = null;
    var agentModule = null;
    var stopping = false;

    /**
     * Loads the agent, which is hosted by Stenproc rather than shipped with
     * this plugin, so face detection can be downloaded only when it is used.
     *
     * @param {String} agentUrl
     * @return {Promise}
     */
    function loadAgent(agentUrl) {
        if (!agentModule) {
            agentModule = import(agentUrl);
        }
        return agentModule;
    }

    /**
     * @param {Object} config
     * @param {Object} extra
     * @return {Object}
     */
    function agentOptions(config, extra) {
        var options = {
            apiBaseUrl: config.apiBaseUrl,
            socketUrl: config.socketUrl,
            sessionId: config.sessionId || 0,
            token: config.token || '',
            checks: config.checks || {},
            onError: function(error) {
                Log.debug('Stenproc proctoring: ' + (error && error.type ? error.type : 'error'));
            }
        };
        return Object.assign(options, extra || {});
    }

    /**
     * Shows a message above the quiz.
     *
     * @param {String} text
     * @param {String} level Bootstrap alert level.
     * @return {Element}
     */
    function banner(text, level) {
        var existing = document.getElementById('stenproc-banner');
        if (!existing) {
            existing = document.createElement('div');
            existing.id = 'stenproc-banner';
            existing.style.cssText = 'position:sticky;top:0;z-index:1030;margin-bottom:.5rem;';
            var region = document.getElementById('region-main') || document.body;
            region.insertBefore(existing, region.firstChild);
        }
        existing.className = 'alert alert-' + level;
        existing.textContent = text;
        return existing;
    }

    /**
     * Finishes uploads before Moodle leaves the page. The clicked button is
     * pressed again afterwards, so Moodle still sees which one was used.
     */
    function finishBeforeLeaving() {
        document.addEventListener('click', function(event) {
            var button = event.target.closest('input[type=submit], button[type=submit], a.mod_quiz-next-nav');
            if (!button || button.dataset.stenprocDone || !agent || stopping) {
                return;
            }
            event.preventDefault();
            stopping = true;
            agent.stop().catch(function() {
                return null;
            }).then(function() {
                button.dataset.stenprocDone = '1';
                button.click();
                return null;
            });
        }, true);

        // A best-effort catch-all if the page goes away another way.
        window.addEventListener('pagehide', function() {
            if (agent && !stopping) {
                stopping = true;
                agent.stop();
            }
        });
    }

    return {
        /**
         * Checks the student's device before the attempt starts, and lets the
         * form be submitted only when it passes.
         *
         * @param {Object} config
         */
        initPreflight: function(config) {
            var status = document.getElementById('stenproc-preflight-status');
            var ready = document.querySelector('input[name="stenprocready"]');
            var strings = config.strings || {};

            var fail = function(message) {
                if (ready) {
                    ready.value = 0;
                }
                if (status) {
                    status.className = 'alert alert-danger';
                    status.textContent = message;
                }
            };

            loadAgent(config.agentUrl).then(function(module) {
                var checker = module.createProctoringAgent(agentOptions(config, {}));
                return checker.checkSystem({requestCamera: true});
            }).then(function(report) {
                if (report.ready) {
                    if (ready) {
                        ready.value = 1;
                    }
                    if (status) {
                        status.className = 'alert alert-success';
                        status.textContent = strings.ready || 'Your device is ready.';
                    }
                } else {
                    fail(report.problems.join(' '));
                }
                return null;
            }).catch(function(error) {
                Log.error(error);
                fail(strings.failed || 'Your device is not ready for a proctored quiz.');
            });
        },

        /**
         * Starts proctoring for an attempt. Where the quiz shares the screen,
         * the student presses a button first, because browsers only show the
         * screen picker in response to a click or tap.
         *
         * @param {Object} config
         */
        initAttempt: function(config) {
            var strings = config.strings || {};
            var checks = config.checks || {};

            var begin = function() {
                return loadAgent(config.agentUrl).then(function(module) {
                    agent = module.createProctoringAgent(agentOptions(config, {}));
                    return agent.start();
                }).then(function() {
                    var element = document.getElementById('stenproc-banner');
                    if (element) {
                        element.remove();
                    }
                    finishBeforeLeaving();
                    return null;
                }).catch(function(error) {
                    Log.error(error);
                    banner(error && error.message ? error.message : (strings.failed || 'Proctoring could not start.'), 'danger');
                });
            };

            if (checks.screen) {
                var prompt = banner(strings.startproctoring || 'Start proctoring to continue', 'warning');
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-primary ml-2';
                button.textContent = strings.startbutton || 'Start proctoring';
                button.addEventListener('click', function() {
                    button.disabled = true;
                    begin();
                });
                prompt.appendChild(button);
            } else {
                begin();
            }
        }
    };
});
