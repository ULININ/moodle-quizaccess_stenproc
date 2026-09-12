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
 * Plays an attempt's recording parts one after another.
 *
 * A quiz records a separate part for each page, because a browser cannot keep
 * recording across a page change. This plays them in order so staff can watch
 * an attempt straight through, and says where the unrecorded gaps fall rather
 * than gliding over them.
 *
 * @module     quizaccess_stenproc/player
 * @copyright  Stenproc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/log'], function(Log) {

    /**
     * Whether a part's temporary link has run out.
     *
     * @param {Object} part
     * @return {Boolean}
     */
    function expired(part) {
        return Boolean(part.expiresat) && part.expiresat * 1000 <= Date.now();
    }

    /**
     * One group of parts -- the camera, or the screen -- with its own player.
     *
     * @param {Element} root
     * @param {Object} group
     * @param {Object} strings
     */
    function Player(root, group, strings) {
        this.root = root;
        this.parts = group.parts.filter(function(part) {
            return Boolean(part.url);
        });
        this.strings = strings;
        this.index = 0;

        this.video = root.querySelector('[data-stenproc="video"]');
        this.status = root.querySelector('[data-stenproc="status"]');
        this.steps = root.querySelector('[data-stenproc="steps"]');

        if (!this.video || !this.parts.length) {
            return;
        }

        this.buildSteps();
        this.wire();
        this.load(0, false);
    }

    /**
     * A button per part, so a proctor can jump straight to one.
     */
    Player.prototype.buildSteps = function() {
        var self = this;

        this.parts.forEach(function(part, index) {
            if (part.gapbefore > 0) {
                var gap = document.createElement('span');
                gap.className = 'text-muted mr-2';
                gap.textContent = self.strings.gap.replace('{$a}', part.gapbeforetext);
                self.steps.appendChild(gap);
            }

            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-secondary btn-sm mr-2 mb-2';
            button.textContent = self.strings.part.replace('{$a}', part.number);
            button.addEventListener('click', function() {
                self.load(index, true);
            });
            self.steps.appendChild(button);
            part.button = button;
        });
    };

    /**
     * Moves to the next part when one finishes, so the attempt plays through.
     */
    Player.prototype.wire = function() {
        var self = this;

        this.video.addEventListener('ended', function() {
            if (self.index + 1 < self.parts.length) {
                self.load(self.index + 1, true);
            }
        });

        this.video.addEventListener('error', function() {
            Log.debug('Stenproc: a recording part could not be played');
            self.say(self.strings.cannotplay, true);
        });
    };

    /**
     * Shows a line of text above the player.
     *
     * @param {String} text
     * @param {Boolean} warn
     */
    Player.prototype.say = function(text, warn) {
        this.status.textContent = text;
        this.status.className = warn ? 'text-danger mb-2' : 'text-muted mb-2';
    };

    /**
     * Loads one part, and plays it when the viewer asked for it.
     *
     * @param {Number} index
     * @param {Boolean} play
     */
    Player.prototype.load = function(index, play) {
        var part = this.parts[index];
        if (!part) {
            return;
        }

        this.index = index;
        this.parts.forEach(function(other) {
            if (other.button) {
                other.button.classList.toggle('active', other === part);
            }
        });

        // The links are temporary addresses for the organisation's own storage.
        // Once one has run out, only a fresh page can get another.
        if (expired(part)) {
            this.say(this.strings.linkexpired, true);
            return;
        }

        this.video.src = part.url;
        this.say(
            this.strings.playing
                .replace('{$a->number}', part.number)
                .replace('{$a->total}', this.parts.length)
                .replace('{$a->when}', part.when || '')
        );

        if (play) {
            var playing = this.video.play();
            if (playing && typeof playing.catch === 'function') {
                playing.catch(function() {
                    // Autoplay can be refused; the controls still work.
                    return null;
                });
            }
        }
    };

    return {
        /**
         * Starts a player for each group of recordings on the report.
         *
         * @param {Object} config
         */
        init: function(config) {
            var strings = config.strings || {};

            (config.groups || []).forEach(function(group) {
                var root = document.getElementById('stenproc-player-' + group.type);
                if (root) {
                    new Player(root, group, strings);
                }
            });
        }
    };
});
