<?php
class ReviewAccess {
    public static function requestError(array $account, string $method, $token, $expected): ?string {
        if (($account['role'] ?? '') !== 'School Directress' || ($account['status'] ?? '') !== 'Active') {
            return 'Only the school directress can review submissions.';
        }
        if ($method !== 'POST') return 'Review decisions must be submitted using POST.';
        if (!is_string($token) || !is_string($expected) || $expected === '' || !hash_equals($expected, $token)) {
            return 'Please refresh the page and try again.';
        }
        return null;
    }
}
