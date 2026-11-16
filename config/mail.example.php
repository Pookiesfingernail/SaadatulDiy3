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
define('SMTP_USER', 'saadatul.art2@gmail.com');
define('SMTP_PASS', 'F@mB32026saadatuldiy'); // Gmail App Password - 16 chars, no spaces
define('MAIL_FROM', 'saadatul.art2@gmail.com');
define('MAIL_FROM_NAME', "Sa'adatul D.I.Y");

// Optional: public site URL for "view photo" links in emails.
define('SITE_URL', '');

define('ORDER_NOTIFY_EMAIL', 'saadatul.art2@gmail.com');
