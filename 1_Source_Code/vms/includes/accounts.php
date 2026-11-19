<?php
/**
 * ---------------------------------------------------------------------
 * File    : includes/accounts.php
 * Purpose : One place for the account form rules, so registration,
 *           "My profile" and the admin's hospital form all validate the
 *           same way.
 * ---------------------------------------------------------------------
 */

/** Editable profile fields of each role (password is handled separately). */
function account_fields(string $role): array
{
    $fields = [
        'admin'    => ['name', 'email'],
        'parent'   => ['name', 'email', 'phone', 'cnic', 'city', 'address'],
        'hospital' => ['name', 'email', 'phone', 'city', 'address', 'location', 'timings'],
    ];
    return $fields[$role] ?? [];
}

/** Reads the role's fields from the submitted form. */
function read_account_input(string $role): array
{
    $data = [];
    foreach (account_fields($role) as $field) {
        $data[$field] = post($field);
    }
    if (isset($data['email'])) {
        $data['email'] = strtolower($data['email']);
    }
    // Single spaces only in names
    if (isset($data['name'])) {
        $data['name'] = preg_replace('/\s+/', ' ', $data['name']);
    }
    return $data;
}

/**
 * Validates account data and returns [field => message] for every problem.
 * $ignoreId is the account's own id when editing (for the email check).
 */
function validate_account(string $role, array &$data, int $ignoreId = 0): array
{
    $errors = [];
    $cfg    = role_config($role);

    // Name: people use letters only, hospitals may also use numbers and & ( ) ,
    $namePattern = $role === 'hospital' ? "/^[\\p{L}\\p{N} .,'&()\\-]{3,150}$/u" : "/^[\\p{L} .'\\-]{3,100}$/u";
    if ($data['name'] === '') {
        $errors['name'] = 'Please enter the name.';
    } elseif (!preg_match($namePattern, $data['name'])) {
        $errors['name'] = $role === 'hospital'
            ? 'Use 3 to 150 characters (letters, numbers, spaces and . , \' & ( ) -).'
            : 'Use 3 to 100 letters (spaces, dots, apostrophes and hyphens are allowed).';
    }

    // Email: valid and not used by another account of the same role
    if (!valid_email($data['email'])) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif (email_taken($cfg['table'], $data['email'], $ignoreId)) {
        $errors['email'] = 'This email is already registered.';
    }

    // Phone (parents and hospitals)
    if (array_key_exists('phone', $data)) {
        if (!valid_phone($data['phone'])) {
            $errors['phone'] = 'Enter a Pakistani number, e.g. 0300-1234567 or 021-34567890.';
        } else {
            $data['phone'] = format_phone($data['phone']);
        }
    }

    // CNIC is optional for parents; stored in the 12345-1234567-1 format
    if (array_key_exists('cnic', $data) && $data['cnic'] !== '') {
        if (!valid_cnic($data['cnic'])) {
            $errors['cnic'] = 'CNIC must have 13 digits, e.g. 42101-1234567-1.';
        } else {
            $data['cnic'] = format_cnic($data['cnic']);
        }
    }

    // City must come from the list
    if (array_key_exists('city', $data) && !in_array($data['city'], city_list(), true)) {
        $errors['city'] = 'Please choose a city.';
    }

    // Address: required for hospitals, optional for parents
    if (array_key_exists('address', $data)) {
        if ($role === 'hospital' && mb_strlen($data['address']) < 5) {
            $errors['address'] = 'Please enter the full address.';
        } elseif (mb_strlen($data['address']) > 255) {
            $errors['address'] = 'Address is too long (255 characters at most).';
        }
    }

    // Optional hospital extras
    if (array_key_exists('location', $data) && mb_strlen($data['location']) > 255) {
        $errors['location'] = 'Location is too long (255 characters at most).';
    }
    if (array_key_exists('timings', $data) && mb_strlen($data['timings']) > 100) {
        $errors['timings'] = 'Timings are too long (100 characters at most).';
    }

    return $errors;
}

/** Checks a new password and its confirmation. */
function validate_new_password(string $password, string $confirm, array &$errors, string $field = 'password'): void
{
    if (!valid_password($password)) {
        $errors[$field] = 'Use at least 8 characters with at least one letter and one number.';
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }
}

/**
 * Prints a password input with the show/hide button.
 * $autocomplete is "current-password" or "new-password".
 */
function password_input(string $name, string $label, array $errors, string $autocomplete = 'new-password', string $help = ''): string
{
    $id   = 'f-' . $name;
    $html = '<label class="form-label required" for="' . $id . '">' . e($label) . '</label>';
    $html .= '<div class="password-field">';
    $html .= '<input class="form-control' . invalid($errors, $name) . '" type="password" id="' . $id . '" name="' . e($name) . '"'
           . ' autocomplete="' . e($autocomplete) . '" required' . ($help !== '' ? ' aria-describedby="' . $id . '-help"' : '') . '>';
    $html .= '<button class="password-toggle" type="button" data-target="#' . $id . '" aria-label="Show password"><i class="bi bi-eye"></i></button>';
    $html .= field_error($errors, $name) . '</div>';
    if ($help !== '') {
        $html .= '<div class="form-text" id="' . $id . '-help">' . e($help) . '</div>';
    }
    return $html;
}

/** <option> list of cities. */
function city_options(string $current = '', string $placeholder = 'Choose city'): string
{
    $html = '<option value="">' . e($placeholder) . '</option>';
    foreach (city_list() as $city) {
        $html .= '<option' . selected($city, $current) . '>' . e($city) . '</option>';
    }
    return $html;
}
