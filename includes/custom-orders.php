<?php
/*
 * CUSTOM ORDER OPTIONS - must match the options in js/custom.js
 * (keychain) and js/custom2.js (figurine).
 * The server re-calculates the estimated price so it cannot be
 * changed in the browser.
 */

declare(strict_types=1);

const CUSTOM_MAX_ITEMS = 2;          // couple order = 2 dolls max
const CUSTOM_NOTE_LIMIT = 500;
const CUSTOM_SPECIAL_NOTE_LIMIT = 1000;
const CUSTOM_FILE_MAX_BYTES = 5 * 1024 * 1024; // 5 MB per photo

// Base prices and allowed choices PER ORDER TYPE.
// EDIT PRICES HERE if your prices change.
const CUSTOM_TYPES = [
    'keychain' => [
        'label'    => 'Custom Keychain',
        'genders'  => ['Male' => 38, 'Female' => 40],
        'hijab_ok' => true,
    ],
    'figurine' => [
        'label'    => 'Custom Figurine',
        'genders'  => ['Male' => 58, 'Female' => 60],
        'hijab_ok' => true,
    ],
];

const CUSTOM_SKIN_TONES = ['Light', 'Fair', 'Medium', 'Tan'];

const CUSTOM_HAIR = [
    'Standard Hijab'       => 0,
    'Short Hair'           => 0,
    'Long Hair'            => 0,
    'Curly / Wavy Hair'    => 5,
    'Custom Detailed Hair' => 5,
];

const CUSTOM_OUTFITS = [
    'Casual Outfit'           => 0,
    'Military / Army'         => 8,
    'General Uniform'         => 8,
    'Baju Melayu Traditional' => 8,
    'Formal Suits'            => 8,
    'Dress'                   => 8,
];

const CUSTOM_ACCESSORIES = [
    'Cap / Helmet or Head Accessories' => 8,
    'Eyeglasses'                       => 3,
    'Backpack / Bag'                   => 5,
    'Graduation Robe'                  => 8,
    'Other Details'                    => 5,
];

/**
 * Checks the doll choices sent from custom.html / custom2.html.
 * Returns [orderType, cleanItems, estimatedTotal] or stops with 422.
 *
 * @param mixed $items     the "keychains" or "figurines" array from the page
 * @param mixed $orderType "keychain" or "figurine"
 */
function validate_custom_items(mixed $items, mixed $orderType): array
{
    $orderType = clean_text($orderType);

    if (!isset(CUSTOM_TYPES[$orderType])) {
        json_error('Unknown custom order type.', 422);
    }

    $config = CUSTOM_TYPES[$orderType];
    $itemLabel = $config['label'];

    if (!is_array($items) || !array_is_list($items) || count($items) < 1 || count($items) > CUSTOM_MAX_ITEMS) {
        json_error('Please design at least one ' . strtolower($itemLabel) . '.', 422);
    }

    $clean = [];
    $total = 0.0;

    foreach ($items as $index => $item) {
        $label = $itemLabel . ' #' . ($index + 1);

        if (!is_array($item)) {
            json_error($label . ': invalid choices.', 422);
        }

        $gender = clean_text($item['gender'] ?? '');
        $skin = clean_text($item['skin'] ?? '');
        $hair = clean_text($item['hair'] ?? '');
        $outfit = clean_text($item['outfit'] ?? '');

        if (!array_key_exists($gender, $config['genders'])) {
            json_error($label . ': please choose Male or Female.', 422);
        }
        if (!in_array($skin, CUSTOM_SKIN_TONES, true)) {
            json_error($label . ': please choose a skin tone.', 422);
        }
        if (!array_key_exists($hair, CUSTOM_HAIR) || ($gender === 'Male' && $hair === 'Standard Hijab')) {
            json_error($label . ': please choose a hairstyle.', 422);
        }
        if (!array_key_exists($outfit, CUSTOM_OUTFITS)) {
            json_error($label . ': please choose an outfit.', 422);
        }

        $accessories = $item['accessories'] ?? [];
        if (!is_array($accessories)) {
            json_error($label . ': invalid accessories.', 422);
        }
        $accessories = array_values(array_unique(array_map('clean_text', $accessories)));
        foreach ($accessories as $accessory) {
            if (!array_key_exists($accessory, CUSTOM_ACCESSORIES)) {
                json_error($label . ': unknown accessory selected.', 422);
            }
        }

        $notes = [];
        foreach (['hairNote', 'outfitNote', 'accessoryNote'] as $noteField) {
            $notes[$noteField] = clean_text($item[$noteField] ?? '');
            if (text_length($notes[$noteField]) > CUSTOM_NOTE_LIMIT) {
                json_error($label . ': notes must be ' . CUSTOM_NOTE_LIMIT . ' characters or fewer.', 422);
            }
        }

        $itemTotal = $config['genders'][$gender] + CUSTOM_HAIR[$hair] + CUSTOM_OUTFITS[$outfit];
        foreach ($accessories as $accessory) {
            $itemTotal += CUSTOM_ACCESSORIES[$accessory];
        }

        $clean[] = [
            'gender'      => $gender,
            'basePrice'   => $config['genders'][$gender],
            'skin'        => $skin,
            'hair'        => $hair,
            'outfit'      => $outfit,
            'accessories' => $accessories,
            'itemTotal'   => (float) $itemTotal,
        ] + $notes;

        $total += $itemTotal;
    }

    return [$orderType, $clean, round($total, 2)];
}

/**
 * Validates and stores one uploaded reference photo.
 * Only JPG / JPEG / PNG / WEBP images are accepted (checked by
 * real file content, not just the file name). Returns the
 * generated file name saved inside $targetDir.
 */
function store_custom_upload(array $file, string $targetDir): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        json_error('Please upload the required reference photo.', 422);
    }
    if (in_array($file['error'] ?? UPLOAD_ERR_OK, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($file['size'] ?? 0) > CUSTOM_FILE_MAX_BYTES) {
        json_error('Reference photos must be 5 MB or smaller.', 422);
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        json_error('A reference photo could not be uploaded. Please try again.', 422);
    }

    $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 12);
    $extension = match (true) {
        str_starts_with($head, "\xFF\xD8\xFF")                           => 'jpg',
        str_starts_with($head, "\x89PNG\r\n\x1A\n")                      => 'png',
        str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP' => 'webp',
        default                                                        => null,
    };

    if ($extension === null) {
        json_error('Reference photos must be JPG, PNG or WEBP images.', 422);
    }

    if (!is_dir($targetDir) && !mkdir($targetDir, 0750, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Cannot create upload folder.');
    }

    $name = 'custom-' . date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
    $path = $targetDir . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        throw new RuntimeException('Cannot save reference photo.');
    }

    return $name;
}

/**
 * Converts the raw $_FILES['files'] structure into a simple map:
 *   [dollPosition][slot] => ['name','type','tmp_name','error','size']
 * PHP stores files[i][slot] as name[i][slot], tmp_name[i][slot], ...
 * so the raw array cannot be used directly.
 */
function normalize_custom_files(array $files): array
{
    $out = [];

    if (!isset($files['name']) || !is_array($files['name'])) {
        return $out;
    }

    foreach ($files['name'] as $position => $slots) {
        if (!is_array($slots)) {
            continue;
        }
        foreach ($slots as $slot => $name) {
            $out[(int) $position][(string) $slot] = [
                'name'     => (string) $name,
                'type'     => (string) ($files['type'][$position][$slot] ?? ''),
                'tmp_name' => (string) ($files['tmp_name'][$position][$slot] ?? ''),
                'error'    => (int) ($files['error'][$position][$slot] ?? UPLOAD_ERR_NO_FILE),
                'size'     => (int) ($files['size'][$position][$slot] ?? 0),
            ];
        }
    }

    return $out;
}

/**
 * Saves the uploaded reference photos for one doll.
 * $files is the NORMALIZED map from normalize_custom_files():
 *   [dollPosition][slot] => file info array
 * Hair and outfit photos are compulsory; accessory is optional.
 * Returns the list of saved file records for custom_order_files.
 */
function store_doll_files(array $files, int $dollPosition, string $targetDir): array
{
    $dollFiles = $files[$dollPosition] ?? null;

    if (!is_array($dollFiles)) {
        json_error('Please upload the required reference photos (hair and outfit).', 422);
    }

    $saved = [];
    $required = ['hair', 'outfit'];
    $optional = ['accessory'];

    foreach (array_merge($required, $optional) as $slot) {
        $file = $dollFiles[$slot] ?? null;

        if (!is_array($file)) {
            if (in_array($slot, $required, true)) {
                json_error('Please upload the required ' . $slot . ' reference photo.', 422);
            }
            continue;
        }

        $saved[] = [
            'slot'          => $slot,
            'file_path'     => store_custom_upload($file, $targetDir),
            'original_name' => clean_text($file['name'] ?? ''),
        ];
    }

    return $saved;
}

/** Builds the WhatsApp chat link with the full custom order details. */
function custom_order_whatsapp_url(int $orderId, array $customer, string $orderType, array $items, string $specialNotes, float $estimatedTotal): string
{
    $typeLabel = CUSTOM_TYPES[$orderType]['label'] ?? 'Custom Order';
    $itemNoun = $orderType === 'figurine' ? 'Figurine' : 'Keychain';

    $lines = [
        "Hello Sa'adatul D.I.Y! I would like to discuss my custom order.",
        '',
        '*' . $typeLabel . ' Order #' . $orderId . '*',
        'Name: ' . $customer['full_name'],
        'Email: ' . $customer['email'],
    ];

    if (!empty($customer['phone'])) {
        $lines[] = 'Phone: ' . $customer['phone'];
    }

    foreach ($items as $index => $item) {
        $lines[] = '';
        $lines[] = '*' . $itemNoun . ' #' . ($index + 1) . ' (' . $item['gender'] . ')*';
        $lines[] = '- Skin tone: ' . $item['skin'];
        $lines[] = '- Hair: ' . $item['hair'] . ($item['hairNote'] !== '' ? ' (' . $item['hairNote'] . ')' : '');
        $lines[] = '- Outfit: ' . $item['outfit'] . ($item['outfitNote'] !== '' ? ' (' . $item['outfitNote'] . ')' : '');
        $lines[] = '- Accessories: ' . ($item['accessories'] ? implode(', ', $item['accessories']) : 'None')
            . ($item['accessoryNote'] !== '' ? ' (' . $item['accessoryNote'] . ')' : '');
        $lines[] = '- Item estimate: BND $' . money((float) $item['itemTotal']);
    }

    if ($specialNotes !== '') {
        $lines[] = '';
        $lines[] = 'Special notes: ' . $specialNotes;
    }

    $lines[] = '';
    $lines[] = 'Estimated total: BND $' . money($estimatedTotal);
    $lines[] = 'I understand the final price will be confirmed with you here on WhatsApp.';

    return 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . rawurlencode(implode("\n", $lines));
}
