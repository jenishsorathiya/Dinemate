<?php
declare(strict_types=1);

function api_route_auth(PDO $pdo, string $method, string $path): bool
{
    if ($path === '/v1/auth/me' && $method === 'GET') {
        $user = api_get_current_user($pdo);
        api_response([
            'success' => true,
            'authenticated' => $user !== null,
            'user' => $user,
        ]);
    }

    if ($path === '/v1/auth/logout' && $method === 'POST') {
        logout();
        api_response(['success' => true]);
    }

    if ($path === '/v1/auth/login' && $method === 'POST') {
        $input = api_read_json_body();
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            api_error('Email and password are required.', 422);
        }

        ensureUserAccountSchema($pdo);
        $stmt = $pdo->prepare("SELECT user_id, email, password, role, name, phone, is_disabled FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            api_error('Invalid email or password.', 401);
        }

        $passwordValid = password_verify($password, (string) $user['password']);
        if (!$passwordValid && $password === (string) $user['password']) {
            $passwordValid = true;
        }

        if (!$passwordValid) {
            api_error('Invalid email or password.', 401);
        }

        if (!empty($user['is_disabled'])) {
            api_error('Your account is disabled.', 403);
        }

        session_regenerate_id(true);
        storeUserSession($user);

        api_response([
            'success' => true,
            'user' => [
                'user_id' => (int) $user['user_id'],
                'name' => (string) ($user['name'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'phone' => (string) ($user['phone'] ?? ''),
                'role' => (string) ($user['role'] ?? ''),
            ],
        ]);
    }

    if ($path === '/v1/auth/social-login' && $method === 'POST') {
        $input = api_read_json_body();
        $provider = strtolower(trim((string) ($input['provider'] ?? '')));

        if (!in_array($provider, ['google', 'apple'], true)) {
            api_error('Invalid social provider.', 422);
        }

        ensureUserAccountSchema($pdo);

        $socialProfiles = [
            'google' => ['email' => 'google_demo@dinemate.com', 'name' => 'Google Demo User'],
            'apple' => ['email' => 'apple_demo@dinemate.com', 'name' => 'Apple Demo User'],
        ];
        $profile = $socialProfiles[$provider];

        $stmt = $pdo->prepare("SELECT user_id, email, password, role, name, phone, is_disabled FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$profile['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $insertStmt = $pdo->prepare("
                INSERT INTO users (name, email, password, role, created_at)
                VALUES (?, ?, ?, 'customer', NOW())
            ");
            $insertStmt->execute([
                $profile['name'],
                $profile['email'],
                password_hash('social123', PASSWORD_BCRYPT),
            ]);

            $user = [
                'user_id' => (int) $pdo->lastInsertId(),
                'name' => $profile['name'],
                'email' => $profile['email'],
                'phone' => null,
                'role' => 'customer',
                'is_disabled' => 0,
            ];
        }

        if (!empty($user['is_disabled'])) {
            api_error('Your account is disabled.', 403);
        }

        session_regenerate_id(true);
        storeUserSession($user);

        api_response([
            'success' => true,
            'user' => [
                'user_id' => (int) $user['user_id'],
                'name' => (string) ($user['name'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'phone' => (string) ($user['phone'] ?? ''),
                'role' => (string) ($user['role'] ?? 'customer'),
            ],
        ]);
    }

    if ($path === '/v1/auth/register' && $method === 'POST') {
        $input = api_read_json_body();
        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $confirmPassword = (string) ($input['confirm_password'] ?? '');

        if ($name === '' || $email === '' || $phone === '' || $password === '' || $confirmPassword === '') {
            api_error('All fields are required.', 422);
        }

        if (!preg_match("/^[a-zA-Z\\s\\-']+$/", $name) || strlen($name) < 2 || strlen($name) > 50) {
            api_error('Name must be 2-50 chars and contain only letters, spaces, hyphens, apostrophes.', 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            api_error('Invalid email format.', 422);
        }

        if (!preg_match("/^[0-9\\s\\-\\(\\)\\+]+$/", $phone)) {
            api_error('Invalid phone format.', 422);
        }

        if (strlen($password) < 6) {
            api_error('Password must be at least 6 characters.', 422);
        }

        if ($password !== $confirmPassword) {
            api_error('Passwords do not match.', 422);
        }

        $checkStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
            api_error('Email already registered.', 409);
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $insertStmt = $pdo->prepare("
            INSERT INTO users (name, email, phone, password, role, created_at)
            VALUES (?, ?, ?, ?, 'customer', NOW())
        ");
        $insertStmt->execute([$name, $email, $phone, $hashed]);

        $userId = (int) $pdo->lastInsertId();
        $user = [
            'user_id' => $userId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'role' => 'customer',
        ];

        storeUserSession($user);
        api_response(['success' => true, 'user' => $user], 201);
    }

    return false;
}
