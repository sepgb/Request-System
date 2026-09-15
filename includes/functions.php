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
