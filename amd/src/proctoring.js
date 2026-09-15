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
    var submitting = false;
    var lockedControls = [];
    var quizLocked = false;

    // The only events that count against a candidate. Anything else the agent
    // starts reporting later is recorded but never ends an attempt by itself.
    var VIOLATIONS = [
        'tab_switch',
        'fullscreen_exit',
        'devtools_detected',
        'face_absent',
        'multiple_faces',
        'screen_share_stopped'
    ];

    /**
     * Loads the agent, which is hosted by Stenproc rather than shipped with
     * this plugin, so face detection can be downloaded only when it is used.
     *
     * @param {String} agentUrl
     * @return {Promise}
     */
    function loadAgent(agentUrl) {
        if (agentModule) {
            return agentModule;
        }

        // Moodle's build rewrites a dynamic import() into a RequireJS call,
        // and RequireJS cannot load the agent: it is an ES module, so there is
        // no define() for RequireJS to find and the browser reports
        // "Unexpected token 'export'". Importing from an inline module script
        // keeps the browser's own loader, which is the one that understands it.
        agentModule = new Promise(function(resolve, reject) {
            var callback = 'stenprocAgentLoaded' + Date.now();

            window[callback] = function(module, error) {
                delete window[callback];
                if (error) {
                    reject(error);
                } else {
                    resolve(module);
                }
            };

            var script = document.createElement('script');
            script.type = 'module';
            script.textContent =
                'import(' + JSON.stringify(agentUrl) + ').then(function(m) {' +
                ' window[' + JSON.stringify(callback) + '](m);' +
                '}).catch(function(e) {' +
                ' window[' + JSON.stringify(callback) + '](null, e);' +
                '});';
            script.onerror = function() {
                reject(new Error('The proctoring agent could not be loaded'));
            };
            document.head.appendChild(script);
        });

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
            agent.stop().then(function() {
                return null;
            }).catch(function() {
                // Whether or not the upload finished, the student still has to
                // be able to submit.
                return null;
            }).then(function() {
                button.dataset.stenprocDone = '1';
                button.click();
                return null;
            }).catch(Log.error);
        }, true);

        // A best-effort catch-all if the page goes away another way.
        window.addEventListener('pagehide', function() {
            if (agent && !stopping) {
                stopping = true;
                agent.stop();
            }
        });
    }


    /**
     * Stops the candidate answering until proctoring is running. Hidden fields
     * are left alone: a disabled field is not submitted, and Moodle needs them.
     */
    function lockQuiz() {
        var form = document.getElementById('responseform');
        if (!form || quizLocked) {
            return;
        }
        // Only a page with questions on it. The summary page carries nothing
        // but "Submit all and finish", and a candidate whose camera failed
        // must still be able to hand in the work they have already done.
        if (!form.querySelector('.que')) {
            return;
        }
        quizLocked = true;
        Array.prototype.forEach.call(
            form.querySelectorAll('input, select, textarea, button'),
            function(element) {
                if (element.disabled || element.type === 'hidden') {
                    return;
                }
                element.disabled = true;
                lockedControls.push(element);
            }
        );
    }

    /**
     * Gives the quiz back once proctoring is running.
     */
    function unlockQuiz() {
        quizLocked = false;
        lockedControls.forEach(function(element) {
            element.disabled = false;
        });
        lockedControls = [];
    }

    /**
     * Navigation links are anchors, so disabling form controls does not stop
     * them. This swallows them while the quiz is locked.
     */
    function blockNavigationWhileLocked() {
        document.addEventListener('click', function(event) {
            if (!quizLocked) {
                return;
            }
            if (!event.target || !event.target.closest) {
                return;
            }
            if (event.target.closest('a.mod_quiz-next-nav, a.mod_quiz-prev-nav, #mod_quiz_navblock a')) {
                event.preventDefault();
                event.stopPropagation();
            }
        }, true);
    }

    /**
     * Submits the attempt the way Moodle's own timer does, after giving the
     * agent a chance to finish its uploads.
     *
     * @param {String} message shown to the candidate before the page goes
     */
    function submitAttempt(message) {
        if (submitting) {
            return;
        }
        submitting = true;
        banner(message, 'danger');
        // Deliberately not locking here. A disabled field is not submitted, so
        // locking first would throw away whatever the candidate had answered.

        var finish = function() {
            var form = document.getElementById('responseform');
            if (!form) {
                return;
            }
            var field = document.createElement('input');
            field.type = 'hidden';
            field.name = 'finishattempt';
            field.value = '1';
            form.appendChild(field);
            // Called off the prototype: a field named "submit" would otherwise
            // shadow the method.
            HTMLFormElement.prototype.submit.call(form);
        };

        if (agent && !stopping) {
            stopping = true;
            agent.stop().then(finish).catch(finish);
            return;
        }
        finish();
    }

    /**
     * Fills a message pattern from PHP, which carries {placeholders} because
     * only the browser knows the counts.
     *
     * @param {String} pattern
     * @param {Object} values
     * @return {String}
     */
    function fill(pattern, values) {
        return Object.keys(values).reduce(function(text, key) {
            return text.split('{' + key + '}').join(values[key]);
        }, pattern || '');
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
            var violationNames = strings.violations || {};
            var maxViolations = parseInt(config.maxViolations, 10);
            var violations = 0;

            if (isNaN(maxViolations) || maxViolations < 0) {
                maxViolations = 5;
            }

            // Proctoring is not the candidate's choice: the quiz stays locked
            // until the agent is running. Without this the prompt could simply
            // be ignored and the attempt taken unproctored.
            lockQuiz();
            blockNavigationWhileLocked();

            var onViolation = function(eventType, details) {
                if (VIOLATIONS.indexOf(eventType) === -1 || submitting) {
                    return;
                }
                violations += 1;
                var reason = violationNames[eventType] || details || eventType;

                if (maxViolations > 0 && violations >= maxViolations) {
                    submitAttempt(fill(strings.submitted, {count: violations}));
                    return;
                }

                banner(
                    maxViolations > 0
                        ? fill(strings.warning, {count: violations, max: maxViolations, reason: reason})
                        : fill(strings.warningonly, {reason: reason}),
                    'warning'
                );
            };

            var begin = function() {
                return loadAgent(config.agentUrl).then(function(module) {
                    agent = module.createProctoringAgent(agentOptions(config, {onEvent: onViolation}));
                    return agent.start();
                }).then(function() {
                    var element = document.getElementById('stenproc-banner');
                    if (element) {
                        element.remove();
                    }
                    unlockQuiz();
                    finishBeforeLeaving();
                    return null;
                }).catch(function(error) {
                    Log.error(error);
                    // The quiz stays locked: proctoring could not start, so the
                    // attempt must not continue unwatched.
                    banner(error && error.message ? error.message : (strings.failed || 'Proctoring could not start.'), 'danger');
                });
            };

            if (checks.screen) {
                // A browser only opens the screen picker from a real click, so
                // this one cannot start on its own.
                var prompt = banner(strings.required || strings.startproctoring || 'Start proctoring to continue', 'warning');
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
