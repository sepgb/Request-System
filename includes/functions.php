<?php
/**
 * Works out the earliest claim date for a request.
 * Registrar only releases documents Monday-Thursday:
 *  - Requested Monday-Thursday -> claim date is the same weekday, one week later.
 *  - Requested Friday-Sunday   -> one week later would still land on a Friday-Sunday,
 *    so it's pushed to the Monday of the week after next instead.
 */
function calculateClaimDate(DateTime $requestDate): DateTime {
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
