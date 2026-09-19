<?php
function calculateClaimDate(DateTime $requestDate): DateTime
{
    $claimDate = clone $requestDate;
    $dayOfWeek = (int)$requestDate->format('N'); // 1 = Monday ... 7 = Sunday

    if ($dayOfWeek <= 4) {
        $claimDate->modify('+7 days');
    } else {
        $daysUntilMonday = 8 - $dayOfWeek; // Fri:3, Sat:2, Sun:1
        $claimDate->modify("+{$daysUntilMonday} days +7 days");
    }

    return $claimDate;
}

function avatarContent(?string $photo, string $initials, string $basePath = ''): string
{
    if (!empty($photo)) {
        $src = $basePath . $photo;
        return '<img src="' . htmlspecialchars($src) . '" alt="" class="avatar-img">';
    }
    return htmlspecialchars($initials);
}

/**
 * CSRF protection. Requires session_start() to already have run.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
    return !empty($_SESSION['csrf_token']) && !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Writes a permanent audit record BEFORE a status change or a claim/delete
 * happens, so history survives even though claimed requests get removed
 * from the `requests` table.
 * $request must contain: id, reference_no, student_number, full_name, document_type.
 */
function logAudit(mysqli $conn, array $request, string $action, ?string $oldStatus, ?string $newStatus): void
{
    $adminId       = $_SESSION['admin_id'] ?? null;
    $adminUsername = $_SESSION['admin_username'] ?? null;

    $stmt = $conn->prepare(
        "INSERT INTO audit_log
            (request_id, reference_no, student_number, full_name, document_type,
             action, old_status, new_status, performed_by_admin_id, performed_by_username)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'isssssssis',
        $request['id'],
        $request['reference_no'],
        $request['student_number'],
        $request['full_name'],
        $request['document_type'],
        $action,
        $oldStatus,
        $newStatus,
        $adminId,
        $adminUsername
    );
    $stmt->execute();
    $stmt->close();
}

/**
 * Best-effort client IP. Not spoof-proof if behind a proxy that doesn't
 * strip client-supplied headers, but sufficient for basic rate limiting.
 */
function getClientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Returns true if this IP is still allowed to attempt a lookup.
 * Default: max 8 attempts per 10-minute rolling window.
 */
function checkRateLimit(mysqli $conn, string $ip, int $maxAttempts = 8, int $windowMinutes = 10): bool
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS c FROM lookup_attempts
         WHERE ip_address = ? AND created_at >= (NOW() - INTERVAL ? MINUTE)"
    );
    $stmt->bind_param('si', $ip, $windowMinutes);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    return $count < $maxAttempts;
}

/**
 * Records one lookup attempt for this IP. Call this every time a
 * reference_no + student_number pair is actually queried against the DB,
 * whether it matches or not.
 */
function recordLookupAttempt(mysqli $conn, string $ip): void
{
    $stmt = $conn->prepare("INSERT INTO lookup_attempts (ip_address) VALUES (?)");
    $stmt->bind_param('s', $ip);
    $stmt->execute();
    $stmt->close();
}
