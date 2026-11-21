# CASM - Classroom, Assignment & Study Manager
A soft, pastel Learning Management System built with **PHP + MySQL (phpMyAdmin / XAMPP)**.

## 1. Install (XAMPP, about 3 minutes)
1. Copy the whole **`casm`** folder into `C:\xampp\htdocs\` (so you have `C:\xampp\htdocs\casm`).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open **http://localhost/phpmyadmin** -> **Import** -> *Choose file* -> select `casm/database/casm.sql` -> **Go**.
   This creates the database `casm_db`, all tables and demo data.
4. Open **http://localhost/casm/** in your browser.

**Already imported an older version?** Do not re-import (that would reset your data). Instead run
`database/update_add_profile_picture.sql` once: phpMyAdmin -> `casm_db` -> **SQL** tab -> paste -> Go.
It only adds the profile-picture column; your users, classes and submissions stay as they are.

**Adding Class Attendance to an existing database:** run `database/update_add_attendance.sql` once the same way (it only creates two new tables and is safe to run again). A fresh import of `casm.sql` already includes them, plus sample attendance.

**Adding the Forum to an existing database:** run `database/update_add_forum.sql` once the same way (it only creates two new tables and is safe to run again). A fresh import of `casm.sql` already includes them.

**Adding Online Meetings to an existing database:** run `database/update_add_meetings.sql` once the same way (it only creates two new tables and is safe to run again). A fresh import of `casm.sql` already includes them.

If your MySQL has a password, edit `config/config.php` (`DB_USER`, `DB_PASS`).
The folder name does not matter - the web path is detected automatically.

## 2. Demo accounts
| Role     | Email                 | Password     |
|----------|-----------------------|--------------|
| Admin    | admin@casm.test       | admin123     |
| Lecturer | lecturer1@casm.test   | lecturer123  | (Dr. Aisyah - CS201, CS202)
| Lecturer | lecturer2@casm.test   | lecturer123  | (Mr. Daniel - CS203, CS204)
| Student  | student1@casm.test ... student8@casm.test | student123 |

Class join codes: `DB2026`, `WEB2026`, `SE2026`, `DS2026`.

## 3. Folder structure
```
casm/
├── index.php, login.php, logout.php     entry + authentication
├── meeting_join.php                     enter an online meeting (start / join)
├── search.php, profile.php, notifications.php, download.php, avatar.php   shared pages
├── config/config.php                    database + app settings
├── database/casm.sql                    import this in phpMyAdmin
│   ├── update_add_profile_picture.sql   only for databases imported before profile pictures existed
│   └── update_add_meetings.sql          only for databases imported before online meetings existed
├── includes/                            bootstrap (DB + helpers), header, footer,
│                                        calendar + timetable partials
├── assets/css/style.css, assets/js/     pastel design, calendar + UI scripts
├── admin/      dashboard, analytics, users, user_form, courses, course_form, course_view, logs, attendance, attendance_view
├── lecturer/   dashboard, courses, course_view, material_form, assignments,
│               assignment_form, assignment_view, calendar, timetable, doubts, api_workload,
│               meetings, meeting_form, meeting_view
├── student/    dashboard, courses, course_view, assignments, assignment_view,
│               calendar, timetable, doubts, meetings, attendance
└── uploads/    materials/, submissions/, avatars/ (protected, served via download.php / avatar.php)
```

## 4. Features -> where to find them
| Feature from the proposal | Where |
|---|---|
| Separate logins: Admin / Lecturer / Student | `login.php`, role checks in `includes/bootstrap.php` |
| ACRUDS (Authentication, Create, Read, Update, Delete, Search) | Users, classes, materials, assignments, events all have create/edit/delete; search in `search.php` + top bar |
| Profile picture (all roles) | `profile.php` (upload, live preview, remove); shown in the sidebar and user lists; served by `avatar.php` |
| Class management (admin only) | Admin creates, edits and deletes classes, assigns the lecturer and removes students: `admin/courses.php`, `class_form.php`, `class_view.php` |
| Class materials (lecturer) | `lecturer/class_view.php`, `material_form.php` |
| Statistics & analytics (admin) | `admin/analytics.php` (users, submissions, grades, activity, per-class and per-lecturer reports, CSV export) |
| Student submissions (upload + update) | `student/assignment_view.php` |
| 1. Smart Workload Balancer | `lecturer/assignment_form.php` (live warning + server check); logic in `workload_conflicts()` |
| Forum (lecturer starts topics, students reply) | Lecturer: `lecturer/forum.php`, `forum_form.php`. Students: `student/forum.php`. Both open topics in `forum_topic.php` |
| 2. Discussion (questions + replies) | `student/doubts.php`, `lecturer/doubts.php`, latest open questions on `lecturer/dashboard.php` |
| 3. Automated Activity Audits | `admin/logs.php` (filters + CSV export), written by `log_activity()` |
| 4. Interactive calendar | `lecturer/calendar.php` (click a date to schedule), `student/calendar.php` |
| 5. Submission analytics + flag + notify | `lecturer/assignment_view.php` (donut chart, pending list, Notify / Remind all) |
| 6. Student timetable | `student/timetable.php` (built from enrolled classes) |
| 7. Online Meetings (like Google Meet) | Lecturer: `lecturer/meetings.php` (schedule, start, end, attendance). Student: `student/meetings.php` (Join button appears when live). Meetings also show on both calendars, and students get a notification |

## 4b. Who can do what
| Action | Admin | Lecturer | Student |
|---|---|---|---|
| Create / delete a class | yes | no | no |
| Assign a lecturer to a class | yes | no | no |
| Remove a student from a class | yes | no | no |
| Remove a lecturer / student account | yes | no | no |
| Edit class details (room, schedule, description) | yes | own classes | no |
| Notes, assignments, grading, meetings | - | own classes | - |
| Join a class with a join code | - | - | yes |

These rules are enforced on the server, not just hidden in the menus. A lecturer who opens `lecturer/class_form.php` without an existing class is sent back with a message.

## 5. Online Meetings
* The lecturer schedules a meeting for a class (or ticks "Start right now"), students are notified, and when the lecturer presses **Start** the meeting goes **Live** and students see a **Join now** button. Attendance is recorded when a student joins. The lecturer can end the meeting; a live meeting that is forgotten is closed automatically after 6 hours.
* The video call is a **Google Meet** meeting. When scheduling, the lecturer presses **Create Meet link** (opens Google Meet in a new tab, sign in with a Google account), copies the meeting link and pastes it into CASM. Only `https://meet.google.com/...` links are accepted.
* Google Meet cannot be created or embedded by a website without a Google Cloud / Workspace sign-in setup, which is why the lecturer pastes the link. Pressing **Start** or **Join** in CASM opens that Meet link in a new tab, and CASM records attendance.
* Students may see "Ask to join" in Google Meet; the lecturer admits them from the Meet screen. Group calls from a free personal Google account are limited to 60 minutes (school Google Workspace accounts usually allow longer).
* The sample meetings in `casm.sql` use the placeholder link `https://meet.google.com/abc-defg-hij`. Edit a meeting to put in a real link.
* Meetings made before this change have no Google Meet link: the lecturer opens the meeting and presses **Add Meet link**.
* Needs internet access on the lecturer's and students' computers, and a camera/microphone allowed in the browser.

## 5b. Class Attendance
* **Lecturer** (`lecturer/attendance.php`): press **New attendance**, pick the class, date and title. Students are notified and can tick **Present**. Open the session to see who ticked, then set each student to **Present**, **Absent** or **Absent with permission** (with an optional reason), or press **Mark all present**. **Close attendance** records every student who did not tick as Absent (an open session also closes by itself after 24 hours). The **Student summary** tab shows each student's rate for a class; below 80% is flagged (`ATTENDANCE_MIN_RATE` in `includes/bootstrap.php`).
* **Student** (`student/attendance.php`): open sessions appear at the top with a **Tick Present** button (the menu shows an OPEN badge). Below are the student's rate per class and full history. A student cannot change a record the lecturer has set, and cannot tick a closed session.
* **Admin** (`admin/attendance.php`): read-only totals, rate per class, students below 80%, all records with filters (class, lecturer, status, student, dates), CSV export, and a page per session (`attendance_view.php`).
* Tables: `attendance_sessions`, `attendance_records` (separate from `meeting_attendance`, which only logs who joined an online meeting). Actions are written to the Activity Logs (`CREATE_ATTENDANCE`, `OPEN_ATTENDANCE`, `TICK_ATTENDANCE`, `UPDATE_ATTENDANCE`, `CLOSE_ATTENDANCE`, `DELETE_ATTENDANCE`).
* Attendance rate = Present / all recorded sessions.

## 6. Notes for your report
* Passwords are hashed with `password_hash()`; all queries use prepared statements (PDO); forms use CSRF tokens; output is escaped.
* Uploaded files get random names and are only reachable through `download.php`, which checks who is allowed to open them.
* Doubts show the name of the student who asked. Classmates and the lecturer can reply (table `doubt_replies`). If you imported an older casm.sql, run `database/update_add_doubt_replies.sql` once.
* Profile pictures are checked to be real images (JPG/PNG/GIF/WEBP, max 3 MB), cropped to a square and re-saved as a 320x320 JPEG, which also removes any hidden data. This needs PHP's GD extension (on by default in XAMPP).
* Max upload size is 10 MB (also check `upload_max_filesize` in `php.ini`).
* Database tables: users, courses, enrollments, materials, assignments, submissions, doubts, doubt_replies, forum_topics, forum_replies, events, notifications, activity_logs, meetings, meeting_attendance.


## Email notices
Everything a student gets as a notification (new assignment, new material, quiz/exam, online meeting, grade, reminder, reply to their question) is also emailed to them.
1. Open `config/config.php` and fill in `MAIL_USER` and `MAIL_PASS` (for Gmail use an **App password**, see the comments there).
2. Make sure the student's email address in CASM is a real one (the demo `@casm.test` addresses are skipped on purpose).
3. If an email does not arrive, check `uploads/email_errors.log`.


## Home page
`index.php` is now the public home page (features and who CASM is for) with a **Sign in** button. Signed-in users see **Go to dashboard** instead. The sign-in page (`login.php`) is a single centered card with a "Back to home" link.


## Student details and student ID
When the administrator adds a **Student**, extra fields appear: IC/passport, date of birth, gender, phone, programme, home address (postcode, city, state) and an optional guardian contact. The **student ID** is generated automatically when the account is saved: the year registered + 6 random digits (for example `2026773856`). It is shown in Users and on the student's own Profile.
If you imported an older `casm.sql`, run `database/update_add_student_details.sql` once (existing students get an ID too).


## Courses and classes
CASM now has two levels:

* **Course** (e.g. CS201 Database Systems) is created **only by the administrator** (Courses menu). It has one lecturer.
* **Class** (Class A, Class B, Class C ...) lives inside a course. The administrator adds the classes (creating a course can create Class A, B and C for you). Every class has its own schedule, room, join code and students. Notes, assignments, meetings, attendance, forum and discussion belong to a class.
* Class code = course code + class name (e.g. `CS201-A`). Changing a course's code, title or lecturer updates all of its classes.
* A student joins a class with its join code, and can be in **one class per course**.
* The lecturer of a course sees it under **My Courses** and works inside its classes. Students see their classes under **My Courses**.

The demo data has 2 courses (CS201 Database Systems by Dr. Aisyah, CS204 Data Structures by Mr. Daniel), each with Class A, B and C.

**Database:** this version needs a fresh import of `database/casm.sql` (new `courses` table; the old classes table is now called `classes`, and the `course_id` columns are now `class_id`). Importing resets the demo data.
