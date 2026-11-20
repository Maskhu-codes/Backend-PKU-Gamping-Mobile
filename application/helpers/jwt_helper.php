<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function get_jwt_key() {
    $key = getenv('JWT_SECRET');
    if ($key === false || strlen($key) < 32) {
        throw new RuntimeException('JWT_SECRET must be configured and contain at least 32 characters');
    }

    return $key;
}

function generate_jwt($payload) {
    $key = get_jwt_key();
    return JWT::encode($payload, $key, 'HS256');
}

function verify_jwt($token) {
    $key = get_jwt_key();
    try {
        return JWT::decode($token, new Key($key, 'HS256'));
    } catch (\Exception $e) {
        return false;
    }
}

function normalize_patient_mr($fs_mr) {
    if (!is_scalar($fs_mr)) {
        return null;
    }

    $fs_mr = trim((string) $fs_mr);
    if ($fs_mr === '') {
        return null;
    }

    if (strlen($fs_mr) <= 10) {
        if (!ctype_digit($fs_mr)) {
            return null;
        }

        return number_format(347104100000000 + (float) $fs_mr, 0, '', '');
    }

    return $fs_mr;
}
