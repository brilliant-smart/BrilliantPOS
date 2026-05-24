<?php

namespace App\Traits;

/**
 * Converts YYYY-MM date strings to the last day of that month (YYYY-MM-DD).
 * Full YYYY-MM-DD dates pass through unchanged.
 */
trait ConvertsDateToMonthEnd
{
    protected function convertToLastDayOfMonth(?string $date): ?string
    {
        if (empty($date)) {
            return $date;
        }

        // If already a full date (YYYY-MM-DD), return as is
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        // If month/year format (YYYY-MM), convert to last day of month
        if (preg_match('/^\d{4}-\d{2}$/', $date)) {
            try {
                $dateObj = \DateTime::createFromFormat('Y-m', $date);
                if ($dateObj) {
                    return $dateObj->format('Y-m-t');
                }
            } catch (\Exception $e) {
                return $date;
            }
        }

        return $date;
    }
}