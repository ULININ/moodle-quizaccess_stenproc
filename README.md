# Stenproc proctoring for Moodle quizzes

[![Moodle plugin CI](https://github.com/ULININ/moodle-quizaccess_stenproc/actions/workflows/moodle-ci.yml/badge.svg)](https://github.com/ULININ/moodle-quizaccess_stenproc/actions/workflows/moodle-ci.yml)

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

### From the release zip

Download the zip from the [latest release][latest] -- the one named
`quizaccess_stenproc-<version>.zip`, **not** GitHub's "Source code" zip, which
Moodle refuses because the folder inside it is named after the tag rather than
the plugin. Then install it through **Site administration ▸ Plugins ▸ Install
plugins** and follow the upgrade screen.

[latest]: https://github.com/ULININ/moodle-quizaccess_stenproc/releases/latest

### With git

Clone it to `mod/quiz/accessrule/stenproc`, then visit **Site administration ▸
Notifications** to install it:

```
git clone https://github.com/ULININ/moodle-quizaccess_stenproc.git \
  mod/quiz/accessrule/stenproc
```

The folder has to be called `stenproc` -- Moodle finds the plugin by its path.

### Then configure it

Under **Site administration ▸ Plugins ▸ Activity modules ▸ Quiz ▸ Stenproc
proctoring**:

- **Stenproc API address**, for example `https://api.stenproc.com`
- **Organisation API key**, created in Stenproc under API keys
- **Live video address**, usually the same as the API address
- **Proctoring agent address**, where the Stenproc agent script is hosted

## Two things the network must allow

Both were found by installing this plugin on a real Moodle site.

- **The agent script must allow cross-origin loading.** The quiz page loads it
  from wherever you host it, so that server must send
  `Access-Control-Allow-Origin`. Without it the browser refuses the script and
  the check reports that the device isn't ready.
- **Moodle blocks outgoing requests to private addresses and to ports other than
  80 and 443.** If your Stenproc address is internal, or uses another port,
  Moodle's cURL security settings (`curlsecurityblockedhosts` and
  `curlsecurityallowedport`, under Site security settings) have to allow it.
  Otherwise attempts stop with "Proctoring could not be started".

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

Installed and exercised on Moodle 4.5.13: a proctored quiz was created, an
attempt was taken and submitted, the session opened and closed in Stenproc, the
recording was stored, and the report played it back. It has not yet run on a
customer's site, on a phone, or with a real camera, so treat the first customer
installation as a pilot.

## Licence

GPL v3 or later, the same as Moodle. See [COPYING.txt](COPYING.txt).
