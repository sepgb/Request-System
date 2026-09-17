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
