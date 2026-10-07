<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function get_jwt_key() {
    return 'PKUG_ONLINE_API_SECRET_KEY';
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
