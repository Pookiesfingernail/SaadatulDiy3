<?php
/*
 * EXAMPLE MAIL CONFIGURATION - copy to config/mail.local.php
 * and fill in your real SMTP credentials.
 * config/mail.local.php is ignored by git so passwords stay private.
 */

declare(strict_types=1);

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
define('SMTP_USER', 'Saadatul.Art2@gmail.com');
define('SMTP_PASS', 'xxxx xxxx xxxx xxxx'); // Gmail App Password
define('MAIL_FROM', 'Saadatul.Art2@gmail.com');
define('MAIL_FROM_NAME', "Sa'adatul D.I.Y");
define('ORDER_NOTIFY_EMAIL', 'Saadatul.Art2@gmail.com');
