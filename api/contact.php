<?php
/*
 * CONTACT US - POST JSON { fullname, email, phone, message }
 * Saves the message in contact_messages and shows a success
 * message only after it is stored. Optional: also emails the
 * shop inbox when SMTP is configured.
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

require_method('POST');
$data = read_json();

$fullName = clean_text($data['fullname'] ?? '');
$email = strtolower(clean_text($data['email'] ?? ''));
$phone = clean_text($data['phone'] ?? '');
$message = clean_text($data['message'] ?? '');

$errors = [];

if (text_length($fullName) < 2 || text_length($fullName) > 100) {
    $errors['fullname'] = 'Please enter your name.';
}
if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}

// Phone must be a Brunei number: +673 followed by exactly 7 digits.
$phoneDigits = preg_replace('/\D/', '', $phone) ?? '';
if (preg_match('/^673\d{7}$/', $phoneDigits) !== 1) {
    $errors['phone'] = 'Please enter a valid Brunei phone number (+673 followed by 7 digits).';
} else {
    $phone = '+673' . substr($phoneDigits, 3);
}

if ($message === '' || text_length($message) > 5000) {
    $errors['message'] = 'Please enter your message.';
}

if ($errors) {
    json_error(reset($errors), 422, ['errors' => $errors]);
}

db()->prepare(
    'INSERT INTO contact_messages (full_name, email, phone, message) VALUES (?, ?, ?, ?)'
)->execute([$fullName, $email, $phone, $message]);

json_response([
    'success' => true,
    'message' => 'Thank you! Your message has been sent. We will get back to you soon.',
], 201);
