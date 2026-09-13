Auth API
I kept writing the same register/login/reset boilerplate for every side project, so I pulled it into one service I can point any app at. PHP 8 with MySQL, no framework: just PDO, a hand rolled HS256 JWT helper, and PHPMailer for the emails. It is not a framework; it is about twenty small files you can read in one sitting.

The idea: put it on a subdomain (for example api.example.com), call it from wherever (web apps, Discord bots, scripts), and stop reimplementing accounts.

How auth works here
Access token: a JWT signed with HS256, valid for 15 minutes. Sent as Authorization: Bearer <token>. Your frontend keeps it in memory (never localStorage).
Refresh token: 64 random hex characters, valid for 30 days. Stored hashed with sha256 in MySQL and delivered as an HttpOnly + Secure + SameSite=Strict cookie. Rotated every time it is used.
Verification and password reset links use single purpose tokens, also stored hashed, each with an expiry.
login => access token (15 min, in memory)      => refresh_token cookie (30 days, HttpOnly)authorized request => 401 after 15 min => POST /refresh.php (cookie rotates)                                        => new access token => retry
Requirements
PHP 8.0 or newer with PDO
MySQL or MariaDB
Composer (only for PHPMailer; the manual route without it is in Troubleshooting)
HTTPS in production, because the Secure cookie and credentialed CORS do not work without it
Setup
Import schema.sql into MySQL.
Upload the project to your server.
Edit config.php: DB credentials, URLs, a real JWT_SECRET, SMTP settings.Generate a secret with php -r "echo bin2hex(random_bytes(32));"
Run composer require phpmailer/phpmailer (or see Troubleshooting).
Add your frontend origin to CORS_ALLOWED_ORIGINS, exact match, protocol and port included.
Sanity check: open https://your-api/api/login.php in a browser. You should get:
{"status":"error","message":"Metodă nepermisă. Folosește POST."}
If you see that, PHP, the config and every helper load correctly.

DB_HOST, DB_NAME, DB_USER, DB_PASS: MySQL connection
BASE_URL: where this API lives; used to build the verification link
APP_URL: your frontend; used to build the password reset link
JWT_SECRET: HMAC key for access tokens. Long and random, please.
JWT_ISSUER: the iss claim
ACCESS_TOKEN_TTL: access token lifetime in seconds (default 900)
REFRESH_TOKEN_TTL: refresh token lifetime in seconds (default 30 days)
COOKIE_PATH: cookie path; / works no matter what subfolder the API sits in
CORS_ALLOWED_ORIGINS: array of exact allowed origins; * is not allowed because credentials would not work
SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS: outgoing mail
MAIL_FROM, MAIL_FROM_NAME: the From header (keep it equal to SMTP_USER)
RATE_WINDOW: shared rate limit window (default 15 minutes)
LOGIN_MAX and LOGIN_IP_MAX: login attempts per email and per IP
FORGOT_MAX and FORGOT_IP_MAX: password reset requests per email and per IP
REGISTER_IP_MAX: registrations per IP
RESEND_MAX and RESEND_IP_MAX: verification resends per email and per IP
MIN_AGE: minimum age to register (13 or 18, your call)
PASSWORD_MIN and PASSWORD_MAX: password length. 72 is the internal bcrypt ceiling, do not raise it.
DEBUG: when true, 500 responses include the error plus file and line. Must be false in production.
Database
Three tables, all InnoDB with utf8mb4:

users: email (unique), first and last name, birth date, bcrypt hash, is_verified, a hashed verify_token with verify_expires, and a hashed reset_token with reset_expires.
refresh_tokens: user id (foreign key with cascade delete), a hashed token (unique), expiry, IP and user agent. Rotation means deleting the old row and inserting a new one.
rate_limits: action, identifier (email or IP) and timestamp. Cleaned up lazily.
No token is ever stored in plaintext. The raw value exists only in the email link or in the cookie.

API endpoints
Base path: /api/. All responses are JSON with a proper HTTP status code.

Quick test with curl:

# login (note the cookie jar: the refresh token arrives as a cookie)curl -X POST https://api.example.com/api/login.php \  -H 'Content-Type: application/json' \  -d '{"email":"user@example.com","password":"secret123"}' \  -c cookies.txt# protected routecurl https://api.example.com/api/user.php \  -H "Authorization: Bearer $TOKEN"# refresh (uses the cookie and rotates it)curl -X POST https://api.example.com/api/refresh.php -b cookies.txt -c cookies.txt
The endpoints:

POST /api/register.php (no auth): create account (inactive), send verification email
GET /api/verify.php?token= (no auth): activate the account, redirect to the frontend
POST /api/resend-verification.php (no auth): send the verification email again
POST /api/login.php (no auth): get an access token plus the refresh cookie
POST /api/refresh.php (cookie): new access token, rotates the refresh token
GET /api/user.php (Bearer): data of the current user
POST /api/logout.php (cookie): delete the refresh token, clear the cookie
POST /api/forgot-password.php (no auth): send the password reset link
POST /api/reset-password.php (no auth): set a new password, kills all sessions
POST /api/delete-account.php (Bearer plus password): permanently delete the account
POST /api/register.php
{ "email": "user@example.com", "password": "secret123",  "first_name": "Ion", "last_name": "Popescu", "birth_date": "2000-05-14" }
201 on success. The account exists but is_verified stays 0 until the email link is used.

Errors: 409 email taken · 422 invalid email, password length outside 8 to 72, invalid names, invalid birth date · 403 under MIN_AGE · 429 rate limited · 500 account created but the email could not be sent (the account is rolled back).

GET /api/verify.php?token=...
This one is clicked straight from the email, so it redirects to APP_URL/index.html?verified=ok|invalid|missing instead of returning JSON. Your frontend reads that parameter and shows a message.

POST /api/resend-verification.php
{ "email": "user@example.com" }
Always the same 200 message whether or not the account exists (anti enumeration). Mail is actually sent only if the account exists and is not verified yet. The token is regenerated, so an older email link stops working.

POST /api/login.php
{ "email": "user@example.com", "password": "secret123" }
200:

{ "status": "success", "access_token": "eyJhbGciOi...", "token_type": "Bearer", "expires_in": 900 }
...plus a Set-Cookie: refresh_token=... header (HttpOnly, Secure, SameSite=Strict, 30 days).

Errors: 401 wrong email or password, same message either way so nothing leaks about which emails exist · 403 account not activated, so your frontend should offer the resend action · 422 missing fields · 429 rate limited.

A password check runs even when the email does not exist (against a dummy bcrypt hash), so response timing does not leak which emails are registered.

POST /api/refresh.php
No body. Reads the refresh_token cookie, checks it against the database, deletes it and issues a new one (rotation), returns a fresh access token. On failure: 401 for a missing, invalid or expired cookie, or 403 for an unverified account.

GET /api/user.php
Requires the Authorization: Bearer <access_token> header.

{ "status": "success",  "user": { "id": 7, "email": "user@example.com", "first_name": "Ion",            "last_name": "Popescu", "created_at": "2025-01-01 12:00:00" } }
401 when the token is missing, invalid, expired, or the user no longer exists.

POST /api/logout.php
Deletes the refresh token matching the cookie (if any) and expires the cookie. Always 200.

POST /api/forgot-password.php
{ "email": "user@example.com" }
Always the same 200 response, whether or not the account exists. If it does, a reset link valid for one hour goes to APP_URL/reset-password.html?token=...

POST /api/reset-password.php
{ "token": "from the email", "password": "newsecret123" }
Sets the new password, clears the reset token and deletes every refresh token of that user, so every session everywhere gets kicked. 400 invalid or expired token · 422 password length.

POST /api/delete-account.php
Requires a valid Bearer token AND the account password in the body:

{ "password": "secret123" }
Deletes the user (refresh tokens cascade away) and clears the cookie. 401 for a wrong password, so a stolen access token alone cannot nuke the account.

Rate limits
Shared window: 15 minutes. A successful login clears that email's counter; the IP counter stays, otherwise distributed attacks with valid passwords could reset themselves.

login and delete account: 5 per email, 20 per IP
forgot password: 3 per email, 10 per IP
resend verification: 3 per email, 6 per IP
register: 5 per IP
Helpers reference
helpers/db.php

db(): PDO, a lazy singleton. Throws on failure (exception mode, real prepared statements).
helpers/cors.php

handle_cors() echoes the origin only if it is whitelisted, sets Allow-Credentials, and answers OPTIONS preflights with 204. Unknown origins get nothing.
helpers/response.php

json_response($data, $status) outputs JSON and exits. Adds nosniff, X-Frame-Options: DENY and Referrer-Policy: no-referrer.
json_error($message, $status) is the {"status":"error",...} shorthand.
json_input() decodes the request body and returns [] on garbage.
helpers/jwt.php

jwt_encode($payload) adds iat, exp and iss, then signs HS256 with hash_hmac.
jwt_decode($jwt) verifies the signature with hash_equals (timing safe), checks alg and exp, and returns the payload or null. No library, no autoloader, nothing to update.
b64url_encode() and b64url_decode() do URL safe base64.
helpers/auth.php

require_auth() reads the Bearer header, decodes the JWT, loads the user from the database, and dies with a 401 JSON on any failure. Returns the user row.
issue_refresh_token($userId) generates the token, stores its sha256 hash with IP and user agent, and sets the HttpOnly cookie.
helpers/ratelimit.php

rate_limit($action, $identifier, $max, $window) counts recent rows in rate_limits, answers 429 when over the limit, and inserts a row otherwise. Randomly prunes rows older than 24 hours (one chance in twenty).
rate_limit_clear($action, $identifier) wipes the counter, used after a successful login.
client_ip() returns REMOTE_ADDR and only that. X-Forwarded-For is client spoofable.
helpers/mailer.php

mailer() returns PHPMailer configured from config.php. The classes load lazily, so a missing vendor cannot take down unrelated requests at include time.
send_verification_email($email, $token) sends the activation link, 24 hours.
send_reset_email($email, $token) sends the reset link, 1 hour.
Building a frontend
The reference implementation is index.html in this repo; everything below is what it does.

Rules:

Every request goes out with credentials: 'include' (in axios: withCredentials: true), otherwise the refresh cookie never travels.
The frontend origin must be listed exactly in CORS_ALLOWED_ORIGINS. https://www.example.com and https://example.com are not the same.
The cookie is SameSite=Strict, so the frontend must be on the same site as the API. app.example.com calling api.example.com is fine; a completely different domain will not receive the cookie.
Keep the access token in a JS variable, not in localStorage. It dies with the tab; the cookie handles persistence.
Handle 401 from protected routes by calling refresh.php once, then retrying the request once.
Minimal client:

const API = 'https://api.example.com/api';let accessToken = null;async function request(path, { method = 'GET', body, auth = false, retried = false } = {}) {  const res = await fetch(API + '/' + path, {    method,    credentials: 'include',                       // sends the refresh cookie    headers: {      ...(body && { 'Content-Type': 'application/json' }),      ...(auth && accessToken && { Authorization: 'Bearer ' + accessToken }),    },    body: body && JSON.stringify(body),  });  // access token expired: refresh once, retry once  if (res.status === 401 && auth && !retried && await refresh()) {    return request(path, { method, body, auth, retried: true });  }  const data = await res.json().catch(() => ({}));  if (!res.ok) throw Object.assign(new Error(data.message || 'HTTP ' + res.status), { status: res.status });  return data;}async function refresh() {  const res = await fetch(API + '/refresh.php', { method: 'POST', credentials: 'include' });  if (!res.ok) return false;  accessToken = (await res.json()).access_token;  return true;}
Usage:

// loginconst data = await request('login.php', { method: 'POST', body: { email, password } });accessToken = data.access_token;// any protected callconst { user } = await request('user.php', { auth: true });// silent login on page loadif (await refresh()) { /* show the dashboard */ }// logoutawait request('logout.php', { method: 'POST' });accessToken = null;
Gotchas worth knowing:

403 on login means the account is not activated. Show a resend action that POSTs to resend-verification.php with the email the user just typed.
The verification link lands on verify.php, which redirects to your index.html?verified=ok|invalid. Read the parameter, show a banner, prefill the login email.
Non browser clients (bots, scripts): browser cookie rules do not apply. Grab the Set-Cookie header from the login response and send it back as a Cookie header on refresh.php, or skip cookies entirely and just log in again when the 15 minutes are up.
Security, what is covered and what is not
Covered:

bcrypt via password_hash, plus a dummy hash for unknown emails (timing attack defense)
no user enumeration in the login, forgot and resend responses
every token (verify, reset, refresh) is stored hashed; rotation on refresh use
a password reset invalidates every session
account deletion requires the password, not just a valid token
per email and per IP rate limiting on every sensitive endpoint
prepared statements everywhere; SameSite=Strict HttpOnly cookie; the nosniff, DENY and no-referrer headers
config.php and helpers/ blocked from the web via .htaccess
Not covered, on purpose for now:

no 2FA, no explicit "log out all devices" button (a password reset does it implicitly)
no email change or profile update endpoints
one JWT secret; rotating it logs everyone out, and there is no kid support
the rate limiter is backed by the database. Fine for a personal project; move to Redis before you scale.
Troubleshooting
All of these happened during the first deployment of this exact code, in this exact order.

Every request fails with "blocked by CORS policy". Look at the Network tab first: when the status is 500, it is not a CORS problem. PHP crashed before sending headers, so the browser reports the missing header as CORS. Open the endpoint directly in the browser; with DEBUG = true the JSON includes the file and line. Usual suspects: vendor/ not uploaded, or a hand edited file with a syntax error.

Login works, but every page load is logged out. The refresh cookie is not being sent. The frontend is on a different site (SameSite=Strict blocks it), the page is on plain http (except localhost), the origin is missing from the whitelist, or you mixed localhost and 127.0.0.1, which are two different sites for the browser. Pick one and use it everywhere.

SMTP: "Could not authenticate". Wrong password. Port 465 needs ENCRYPTION_SMTPS in mailer.php; port 587 needs ENCRYPTION_STARTTLS. Mixing them gives a misleading "SMTP connect() failed".

The Authorization header seems missing on protected routes. Some Apache and CGI setups strip it. The included .htaccess contains the SetEnvIf fix.

429 everywhere while developing. The limits are per email and per IP in a 15 minute window. Wait, raise the constants, or run TRUNCATE rate_limits in dev.

No Composer on the server. Download the PHPMailer release zip and upload PHPMailer.php, SMTP.php and Exception.php to vendor/phpmailer/phpmailer/src/, then follow the comment in helpers/mailer.php (the manual require block).

Deployment checklist
 DEBUG = false in config.php
 a real JWT_SECRET, not the placeholder
 the DB user is not root
 SMTP settings are real (send yourself a test email)
 HTTPS on both the API and the frontend
 CORS_ALLOWED_ORIGINS matches the production frontend, exactly
 the full schema.sql imported, including rate_limits
 vendor/ present on the server
 config.php and helpers/ not directly reachable (test: https://api.example.com/config.php should be denied)
License
MIT, do whatever you want.
