<?php
/**
 * ===== Slots Helpers =====
 */

// الدوال موجودة في api/index.php
// هذا الملف للتوافق فقط

function timeShort(?string $time): ?string
{
    if ($time === null) {
        return null;
    }
    return substr($time, 0, 5);
}
