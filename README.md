# Stenproc proctoring for Moodle quizzes

A Moodle quiz access rule that proctors an attempt with Stenproc. Moodle keeps
the questions, the timing and the marks. Stenproc watches and records the
attempt, and staff review it afterwards.

## What it does

- Checks the student's device and camera before the attempt starts.
- Opens a proctoring session in Stenproc for each attempt, and closes it when
  the attempt is submitted, abandoned or deleted.
- Runs the Stenproc agent in the quiz page: camera monitoring, screen sharing on
  computers, recording, face checks, and reporting when the student leaves the
  quiz.
- Shows staff a report for each attempt: what was reported, and the recordings.

Recordings upload straight from the student's browser to **your organisation's
own storage**, which you connect in Stenproc. No video passes through Moodle.

## Requirements

- Moodle 4.1 or later. The plugin supports both the older and newer quiz access
  rule interfaces (Moodle renamed them in 4.2).
- A Stenproc organisation with an API key, and connected storage if you turn on
  recording.
- Students must use a web browser. **The Moodle app cannot run the agent**, and
  proctored attempts are blocked there.

## Installing

1. Copy this folder to `mod/quiz/accessrule/stenproc` in your Moodle.
2. Visit Site administration ▸ Notifications to install it.
3. Go to Site administration ▸ Plugins ▸ Activity modules ▸ Quiz ▸ Stenproc
   proctoring and fill in:
   - **Stenproc API address**, for example `https://api.stenproc.com`
   - **Organisation API key**, created in Stenproc under API keys
   - **Live video address**, usually the same as the API address
   - **Proctoring agent address**, where the Stenproc agent script is hosted

## Using it

Edit any quiz, open the **Stenproc proctoring** section, turn it on and choose
which checks to use: camera monitoring, screen sharing, recording, face checks
and reporting when a student leaves the quiz.

Students see a check before they begin, and are asked to agree to being
recorded. The report for an attempt is at
`/mod/quiz/accessrule/stenproc/report.php?attemptid=<id>`, and needs the
`quizaccess/stenproc:viewreport` permission.

## Things to know

- **Phones and tablets can't share their screen.** Those attempts continue with
  the camera only, and are given longer before leaving the quiz is reported,
  because notifications hide the page.
- **Multi-page quizzes**: the agent finishes its upload and restarts on each
  page, so each page is a separate recording segment. A quiz with all questions
  on one page avoids this.
- **Face checks** download extra software to the student's browser the first
  time they are used.
- **Deleting a student's data in Moodle** removes the link to their proctoring
  session. The recordings live in your own storage and are removed under your
  own retention rules.

## Status

This plugin has not yet been installed on a live Moodle. Treat the first
installation as a pilot, on a test site, before using it for a real exam.
