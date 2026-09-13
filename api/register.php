<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/mailer.php';
require_once __DIR__ . '/../helpers/ratelimit.php';

handle_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Metodă nepermisă. Folosește POST.', 405);
}

 $input     = json_input();
 $email     = strtolower(trim($input['email'] ?? ''));
 $password  = $input['password'] ?? '';
 $firstName = trim($input['first_name'] ?? '');
 $lastName  = trim($input['last_name'] ?? '');
 $birthDate = trim($input['birth_date'] ?? ''); // expected: YYYY-MM-DD

// Anti-spam: max 5 accounts / 15 min / IP
rate_limit('register_ip', client_ip(), REGISTER_IP_MAX, RATE_WINDOW);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Adresă de email invalidă.', 422);
}

// Min/max — 72 is bcrypt's internal limit; without a max, huge inputs slow down hashing
if (strlen($password) < PASSWORD_MIN || strlen($password) > PASSWORD_MAX) {
    json_error('Parola trebuie să aibă între ' . PASSWORD_MIN . ' și ' . PASSWORD_MAX . ' caractere.', 422);
}

// Names: letters (including diacritics), spaces, apostrophe, hyphen
if (!preg_match("/^[\p{L}' -]{2,50}$/u", $firstName) || !preg_match("/^[\p{L}' -]{2,50}$/u", $lastName)) {
    json_error('Nume sau prenume invalid.', 422);
}

// Birth date: valid format, real date (not Feb 31), not in the future, minimum age
 $bday = DateTime::createFromFormat('Y-m-d', $birthDate);
if (!$bday || $bday->format('Y-m-d') !== $birthDate) {
    json_error('Data nașterii invalidă.', 422);
}
 $bday->setTime(0, 0);
if ($bday > new DateTime('today')) {
    json_error('Data nașterii nu poate fi în viitor.', 422);
}
 $age = (new DateTime('today'))->diff($bday)->y;
if ($age < MIN_AGE) {
    json_error('Trebuie să ai minimum ' . MIN_AGE . ' ani pentru a crea un cont.', 403);
}

// Email already taken?
 $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
 $stmt->execute([$email]);
if ($stmt->fetch()) {
    json_error('Există deja un cont cu acest email.', 409);
}

// Unactivated account + verification token (raw in email, hash in DB)
 $verifyToken = bin2hex(random_bytes(32));

db()->prepare(
    'INSERT INTO users (email, first_name, last_name, birth_date, password_hash, verify_token, verify_expires)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
)->execute([
    $email,
    $firstName,
    $lastName,
    $birthDate,
    password_hash($password, PASSWORD_DEFAULT),
    hash('sha256', $verifyToken),
    date('Y-m-d H:i:s', time() + 86400), // 24h
]);
 $userId = (int) db()->lastInsertId();

try {
    send_verification_email($email, $verifyToken);
} catch (Throwable $e) {
    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]); // simple rollback
    json_error('Contul a fost creat, dar email-ul nu a putut fi trimis. Încearcă din nou.', 500);
}

json_response(['status' => 'success', 'message' => 'Cont creat. Verifică-ți email-ul pentru activare.'], 201);