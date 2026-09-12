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
 * Tests for the recording timeline shown on the report.
 *
 * A multi-page quiz records one part per page, so the report has to make a
 * pile of separate files read as one attempt.
 *
 * @package    quizaccess_stenproc
 * @copyright  Stenproc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_stenproc\recording_timeline
 */
final class recording_timeline_test extends \advanced_testcase {
    /**
     * One part, as the evidence endpoint returns it.
     *
     * @param string $started
     * @param string $ended
     * @param array $overrides
     * @return array
     */
    protected function part($started, $ended, array $overrides = []) {
        return array_merge([
            'recordingType' => 'webcam',
            'startedAt' => $started,
            'endedAt' => $ended,
            'url' => 'https://storage.example.com/part',
        ], $overrides);
    }

    public function test_an_unproctored_attempt_has_no_timeline(): void {
        $this->assertSame([], recording_timeline::build([]));
    }

    public function test_the_parts_of_a_multi_page_quiz_are_numbered_in_order(): void {
        $timeline = recording_timeline::build([
            $this->part('2026-09-12T09:14:00Z', '2026-09-12T09:18:00Z'),
            $this->part('2026-09-12T09:18:06Z', '2026-09-12T09:22:00Z'),
            $this->part('2026-09-12T09:22:04Z', '2026-09-12T09:25:00Z'),
        ]);

        $this->assertCount(1, $timeline);
        $this->assertSame('webcam', $timeline[0]['type']);
        $this->assertSame([1, 2, 3], array_column($timeline[0]['parts'], 'number'));
    }

    public function test_the_seconds_nobody_saw_are_reported(): void {
        $timeline = recording_timeline::build([
            $this->part('2026-09-12T09:14:00Z', '2026-09-12T09:18:00Z'),
            $this->part('2026-09-12T09:18:06Z', '2026-09-12T09:22:00Z'),
        ]);
        $parts = $timeline[0]['parts'];

        $this->assertSame(0, $parts[0]['gapbefore'], 'the first part follows nothing');
        $this->assertSame(6, $parts[1]['gapbefore'], 'six seconds passed while the page changed');
    }

    public function test_parts_that_run_straight_on_show_no_gap(): void {
        $timeline = recording_timeline::build([
            $this->part('2026-09-12T09:14:00Z', '2026-09-12T09:18:00Z'),
            $this->part('2026-09-12T09:18:00Z', '2026-09-12T09:22:00Z'),
        ]);

        $this->assertSame(0, $timeline[0]['parts'][1]['gapbefore']);
    }

    public function test_camera_and_screen_are_kept_apart(): void {
        $timeline = recording_timeline::build([
            $this->part('2026-09-12T09:14:00Z', '2026-09-12T09:18:00Z'),
            $this->part('2026-09-12T09:14:00Z', '2026-09-12T09:18:00Z', ['recordingType' => 'screen']),
            $this->part('2026-09-12T09:18:06Z', '2026-09-12T09:22:00Z'),
        ]);

        $this->assertCount(2, $timeline);
        $types = array_column($timeline, 'type');
        $this->assertEqualsCanonicalizing(['webcam', 'screen'], $types);

        foreach ($timeline as $group) {
            $expected = $group['type'] === 'webcam' ? 2 : 1;
            $this->assertCount($expected, $group['parts']);
            // Each kind is numbered from one, not continued from the other.
            $this->assertSame(1, $group['parts'][0]['number']);
        }
    }

    public function test_the_total_adds_up_the_parts(): void {
        $timeline = recording_timeline::build([
            $this->part('2026-09-12T09:14:00Z', '2026-09-12T09:18:00Z'),
            $this->part('2026-09-12T09:18:06Z', '2026-09-12T09:20:00Z'),
        ]);

        // Four minutes and one minute fifty-four, and not the six-second gap.
        $this->assertSame(240 + 114, $timeline[0]['recorded']);
    }

    public function test_a_length_reported_by_stenproc_is_preferred_to_the_clock(): void {
        $timeline = recording_timeline::build([
            $this->part('2026-09-12T09:14:00Z', '2026-09-12T09:18:00Z', ['duration' => 111]),
        ]);

        $this->assertSame(111, $timeline[0]['parts'][0]['length']);
    }

    public function test_a_part_still_uploading_is_listed_rather_than_dropped(): void {
        $timeline = recording_timeline::build([
            $this->part('2026-09-12T09:14:00Z', '2026-09-12T09:18:00Z'),
            $this->part('2026-09-12T09:18:06Z', '2026-09-12T09:22:00Z', ['url' => null]),
        ]);
        $parts = $timeline[0]['parts'];

        $this->assertCount(2, $parts, 'a part with no link is still part of the attempt');
        $this->assertTrue($parts[0]['playable']);
        $this->assertFalse($parts[1]['playable']);
        $this->assertNull($parts[1]['url']);
    }

    public function test_a_missing_time_is_unknown_rather_than_zero(): void {
        $timeline = recording_timeline::build([
            $this->part('2026-09-12T09:14:00Z', null),
            $this->part('2026-09-12T09:18:06Z', '2026-09-12T09:22:00Z'),
        ]);
        $parts = $timeline[0]['parts'];

        $this->assertNull($parts[0]['endedat']);
        $this->assertNull($parts[0]['length']);
        // The gap is measured from the last part that actually ended, so an
        // unknown end does not invent one.
        $this->assertSame(0, $parts[1]['gapbefore']);
    }

    public function test_lengths_are_written_the_way_a_person_reads_a_clock(): void {
        $this->assertSame('6s', recording_timeline::readable(6));
        $this->assertSame('4m 38s', recording_timeline::readable(278));
        $this->assertSame('1h 04m', recording_timeline::readable(3849));
        $this->assertSame('', recording_timeline::readable(null));
    }
}
