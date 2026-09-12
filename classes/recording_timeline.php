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
 * Arranges an attempt's recordings into a readable timeline.
 *
 * @package    quizaccess_stenproc
 * @copyright  Stenproc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_stenproc;

/**
 * A quiz records one part per page, because a browser cannot keep recording
 * across a page change. This turns those parts into something staff can read:
 * numbered, in order, with the unrecorded gap between them stated rather than
 * hidden.
 */
class recording_timeline {
    /**
     * Groups an attempt's recordings by kind, oldest first.
     *
     * @param array $recordings as returned by the Stenproc evidence endpoint
     * @return array one entry per kind, each with its parts and total length
     */
    public static function build(array $recordings) {
        $groups = [];

        foreach ($recordings as $recording) {
            $type = empty($recording['recordingType']) ? 'webcam' : $recording['recordingType'];
            $groups[$type][] = $recording;
        }

        $built = [];
        foreach ($groups as $type => $recordings) {
            $parts = [];
            $recorded = 0;
            $number = 0;
            $previousend = null;

            foreach ($recordings as $recording) {
                $number++;
                $startedat = self::stamp($recording, 'startedAt');
                $endedat = self::stamp($recording, 'endedAt');
                $length = self::length($recording, $startedat, $endedat);
                $recorded += $length === null ? 0 : $length;

                // Only a gap between two known times means anything. A missing
                // timestamp is unknown, not zero.
                $gap = 0;
                if ($previousend !== null && $startedat !== null && $startedat > $previousend) {
                    $gap = $startedat - $previousend;
                }

                $parts[] = [
                    'number' => $number,
                    'startedat' => $startedat,
                    'endedat' => $endedat,
                    'length' => $length,
                    'gapbefore' => $gap,
                    'url' => empty($recording['url']) ? null : $recording['url'],
                    'playable' => !empty($recording['url']),
                ];

                if ($endedat !== null) {
                    $previousend = $endedat;
                }
            }

            $built[] = [
                'type' => $type,
                'parts' => $parts,
                'recorded' => $recorded,
            ];
        }

        return $built;
    }

    /**
     * A length in seconds, written the way a person reads a clock.
     *
     * @param int|null $seconds
     * @return string
     */
    public static function readable($seconds) {
        if ($seconds === null) {
            return '';
        }

        $seconds = (int) round($seconds);
        if ($seconds < 60) {
            return $seconds . 's';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60) . 'm ' . ($seconds % 60) . 's';
        }
        return intdiv($seconds, 3600) . 'h ' . str_pad(intdiv($seconds % 3600, 60), 2, '0', STR_PAD_LEFT) . 'm';
    }

    /**
     * How long one part ran for, from its own duration or its two timestamps.
     *
     * @param array $recording
     * @param int|null $startedat
     * @param int|null $endedat
     * @return int|null null when neither is known
     */
    protected static function length(array $recording, $startedat, $endedat) {
        if (isset($recording['duration']) && $recording['duration'] > 0) {
            return (int) $recording['duration'];
        }
        if ($startedat !== null && $endedat !== null && $endedat >= $startedat) {
            return $endedat - $startedat;
        }
        return null;
    }

    /**
     * One of the evidence timestamps as a unix time.
     *
     * @param array $recording
     * @param string $field
     * @return int|null
     */
    protected static function stamp(array $recording, $field) {
        if (empty($recording[$field])) {
            return null;
        }
        $time = strtotime($recording[$field]);
        return $time === false ? null : $time;
    }
}
