<?php
/*
 * EMAIL SETTINGS - SA'ADATUL D.I.Y
 *
 * ORDER NOTIFICATION EMAIL: every checkout order is sent to
 * saadatul.art2@gmail.com  (the shop's backend inbox).
 * The public contact email Saadatul.Art@gmail.com stays on
 * the Contact Us page only - it is NOT used for order alerts.
 *
 * HOW TO CONFIGURE GMAIL SMTP:
 *   1. Use a Gmail account (e.g. Saadatul.Art2@gmail.com).
 *   2. Turn on 2-Step Verification, then create an App Password
 *      at https://myaccount.google.com/apppasswords
 *   3. Copy the 16-character app password into MAIL_PASS below.
 *
 * To keep real passwords out of this file, create
 * config/mail.local.php (it is ignored by git) with the same
 * constants and your real values. Values here are only defaults.
 */

declare(strict_types=1);

if (is_file(__DIR__ . '/mail.local.php')) {
    require __DIR__ . '/mail.local.php';
}

// Gmail SMTP settings (port 465 = SSL).
defined('SMTP_HOST')     || define('SMTP_HOST', 'smtp.gmail.com');
defined('SMTP_PORT')     || define('SMTP_PORT', 465);
defined('SMTP_SECURE')   || define('SMTP_SECURE', 'ssl'); // 'ssl' or 'tls'
defined('SMTP_USER')     || define('SMTP_USER', 'saadatul.art2@gmail.com'); // Gmail account
// SMTP_PASS stays EMPTY here on purpose - put the real Gmail
// App Password only in config/mail.local.php (git-ignored).
defined('SMTP_PASS')     || define('SMTP_PASS', '');
defined('MAIL_FROM')     || define('MAIL_FROM', 'saadatul.art2@gmail.com');
defined('MAIL_FROM_NAME')|| define('MAIL_FROM_NAME', "Sa'adatul D.I.Y");

// Public site address used to build "view photo" links inside
// order emails (e.g. https://yourdomain.com). Leave EMPTY for
// local development - photos are then only sent as attachments.
defined('SITE_URL')      || define('SITE_URL', '');

// BACKEND ORDER NOTIFICATION RECIPIENT - do not change to the
// public contact email.
defined('ORDER_NOTIFY_EMAIL') || define('ORDER_NOTIFY_EMAIL', 'saadatul.art2@gmail.com');
