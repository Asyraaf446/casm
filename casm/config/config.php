<?php
/**
 * CASM - configuration
 * Change these values only if your MySQL settings are different from XAMPP's defaults.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'casm_db');
define('DB_USER', 'root');
define('DB_PASS', '');            // XAMPP default password is empty

define('APP_NAME', 'CASM');

// Logo pictures (inside the project folder). To change the logo, just replace these
// image files, or point these two lines to a different file name.
define('LOGO_ICON', 'assets/img/logo-icon.png');   // square icon shown in the menu, login and home page
define('LOGO_FULL', 'assets/img/logo-full.png');   // icon + name, for you to use anywhere you need it
define('APP_TAGLINE', 'Classroom, Assignment & Study Manager');
define('MAX_UPLOAD_MB', 10);

/* Online meetings use Google Meet. Nothing to configure here: the lecturer creates a Meet link
   (meet.google.com/new) and pastes it when scheduling a meeting. */

/* Email notices. Whenever a lecturer posts something (assignment, material, quiz/exam,
   online meeting, grade, reminder ...) students also get it by email, like Google Classroom.
   Leave MAIL_USER / MAIL_PASS empty to keep email OFF. Everything else keeps working.

   Gmail example (recommended for testing):
     1. Turn on 2-Step Verification for the Gmail account.
     2. Google Account > Security > App passwords > create one (16 letters).
     3. MAIL_USER = the Gmail address, MAIL_PASS = that 16-letter app password
        (NOT the normal Gmail password). */
define('MAIL_ENABLED', true);
define('MAIL_HOST', 'smtp.gmail.com');   // Outlook: smtp.office365.com
define('MAIL_PORT', 587);                // 587 with 'tls', or 465 with 'ssl'
define('MAIL_SECURE', 'tls');            // 'tls' or 'ssl'
define('MAIL_USER', '');                 // e.g. yourname@gmail.com
define('MAIL_PASS', '');                 // the app password
define('MAIL_FROM', '');                 // leave empty to use MAIL_USER
define('MAIL_FROM_NAME', 'CASM');
define('MAIL_STAFF_TOO', false);         // false = only students get emails; true = lecturers/admin too
define('MAIL_VERIFY_SSL', false);        // false is easiest on XAMPP; set true on a real server
define('APP_URL', '');                   // link used inside emails. Empty = detected automatically.
                                         // On a real server write it, e.g. 'https://casm.myschool.edu.my'

define('APP_ROOT', str_replace('\\', '/', (string) realpath(__DIR__ . '/..')));
define('UPLOAD_DIR', APP_ROOT . '/uploads');

date_default_timezone_set('Asia/Kuala_Lumpur');

/* Work out the web path of this folder automatically (e.g. "/casm"),
   so the project works no matter what you name the folder in htdocs. */
$__doc  = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$__base = '';
if ($__doc !== '' && stripos(APP_ROOT, $__doc) === 0) {
    $__base = substr(APP_ROOT, strlen($__doc));
}
define('BASE_URL', rtrim($__base, '/'));
unset($__doc, $__base);
