# Course reminders (local_coursereminders)

**Automatically remind learners who stop engaging with a course.**

Teachers create reminder rules on their own course — after enrolment, after a period of inactivity, or a set time before the course ends — and a daily scheduled task sends the messages through Moodle's normal messaging. Everything is configured by the teacher in the course; the administrator only installs the plugin. No core files are modified.

---

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Creating a reminder](#creating-a-reminder)
- [Message placeholders](#message-placeholders)
- [Following up learners](#following-up-learners)
- [Site settings](#site-settings)
- [Large sites](#large-sites)
- [Capabilities](#capabilities)
- [Privacy](#privacy)
- [Support](#support)

---

## Requirements

| | |
|---|---|
| Moodle | 4.5 to 5.2 (`$plugin->supported = [405, 502]`) |
| PHP | 8.1+ |
| Database | MySQL / MariaDB / PostgreSQL |
| Cron | Required — a scheduled task sends the reminders |
| Course completion | **Required** on every course using reminders. Rules cannot be enabled without it |

No third-party libraries and no dependencies on other plugins.

## Installation

1. Copy the plugin folder to `local/coursereminders` in your Moodle code root (on Moodle 5.1+ this is `public/local/coursereminders`).
2. Visit **Site administration → Notifications** and complete the upgrade.
3. Optionally review the defaults at **Site administration → Plugins → Local plugins → Course reminders**.

## Creating a reminder

**Course reminders** appears in the course secondary navigation for editing teachers and managers. The manage page lists the rules on the course, with edit, duplicate, enable/disable and delete actions available per row or in bulk.

Three reminder types are available:

| Type | Sent when | Stops when |
|---|---|---|
| **After enrolment** | the learner has not opened the course *N* days after enrolling | the learner opens the course |
| **Inactivity** | the learner has not opened the course for *N* days, then every *N* days after that | the learner returns, the course ends, or the course is completed |
| **Before course end** | *N* days before the course end date, if the course is not complete | the course is completed |

Each rule sets:

- **Delay**, in days or weeks.
- **Recipients**, filtered by enrolment method (self, manual, other, or all). Only learners are targeted.
- **Target group**, offered when the course uses separate groups.
- **Subject and message body**, with the placeholders below.
- **Maximum number of reminders** per learner, and the **alert badge threshold**.

A **before course end** rule also needs an end date on the course before it can be enabled.

## Message placeholders

| Placeholder | Value |
|---|---|
| `[firstname]` / `[lastname]` | the recipient's name |
| `[coursename]` / `[courseurl]` | the course name and a link to it |
| `[delay]` | the rule's delay, in weeks where it divides evenly, otherwise in days |
| `[enroldate]` | the learner's enrolment date |
| `[courseenddate]` | the course end date |

Placeholders are localised, so both `[firstname]` and the French `[prenom]` are recognised whichever language the message is written in.

## Following up learners

**Sent reminders list** shows every enrolled learner with the number of reminders received, the date of the last one, and their groups. Learners past the alert threshold are flagged **Inactive**.

From there a teacher can view a learner's reminder log (sent and upcoming), send them a message, or unenrol them — the last subject to the usual `enrol/*:unenrol` capabilities, exactly as on the participants page.

## Site settings

**Site administration → Plugins → Local plugins → Course reminders** sets the starting values teachers see when creating a rule: default sender, alert threshold, maximum count, and a default delay, subject and body per reminder type.

Leave a default subject or body empty to use the language pack text, so the form is prefilled in each teacher's own language. The **sender** is the no-reply user, the support user, or a course teacher.

## Large sites

By default the scheduled task sends inline, which is fine for a few hundred reminders per run. Above that, enable **Send reminders in background batches** under *Sending*: the task then queues ad hoc tasks of at most 200 learners (configurable) that cron drains over its following runs. Each batch re-checks the rule, course and learner before sending, so nothing goes out to someone who has since completed or left the course.

Sites sending thousands of reminders per run should also relay through a queueing MTA with a sensible `smtpmaxbulk`.

## Capabilities

| Capability | Context | Default roles |
|---|---|---|
| `local/coursereminders:manage` | Course | editing teacher, manager |
| `local/coursereminders:send` | Course | editing teacher, manager |
| `local/coursereminders:viewhistory` | Course | editing teacher, manager |

## Privacy

The plugin stores reminder rules (course configuration) and a log of every reminder sent: rule, course, recipient, type, occurrence and timestamp. That log is also what prevents the same reminder being sent twice. The reminders themselves are delivered by core messaging, which keeps its own record.

The Moodle Privacy API is implemented for export and deletion.

Reminder rules are included in course backups; the reminder history is included only when the backup contains user data.

## License

Licensed under the [GNU GPL v3 licence](http://www.gnu.org/copyleft/gpl.html).
