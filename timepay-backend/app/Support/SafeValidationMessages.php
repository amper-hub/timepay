<?php

namespace App\Support;

class SafeValidationMessages
{
    public static function sanitize(array $errors): array
    {
        $safeErrors = [];
        $unsafeContent = '~SQLSTATE|SQL syntax|PDOException|Illuminate\\\\|Symfony\\\\|\b[A-Za-z0-9_]+Exception\b|\bER_[A-Z0-9_]+\b|\b(?:MySQL|MariaDB|PostgreSQL|SQLite)\b|\b(?:select\s+.+\s+from|insert\s+into|update\s+\w+\s+set|delete\s+from|alter\s+table|drop\s+table)\b|\b(?:unknown column|no such (?:table|column)|duplicate entry|connection refused|could not connect to (?:database|host))\b|stack trace|traceback|https?://|file://|(?:[A-Za-z]:[\\\\/]|/(?:var|home|srv|opt|app|vendor|Users)/)|\b(?:\d{1,3}\.){3}\d{1,3}\b|\b(?:localhost|[\w.-]+\.(?:internal|local|lan))(?::\d{2,5})?\b|\b(?:[A-Z][A-Z0-9_]*(?:PASSWORD|SECRET|TOKEN|API_KEY|APP_KEY|DB_HOST))\s*=|\b(?:api[_ -]?key|token|password|secret|credential|authorization)\s*[:=]|\bBearer\s+[A-Za-z0-9._-]{12,}\b|\beyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\b|^[\s]*[\[{]~i';

        foreach ($errors as $field => $fieldMessages) {
            if (! preg_match('/^[A-Za-z][A-Za-z0-9_.]*$/', (string) $field)) {
                continue;
            }

            foreach ((array) $fieldMessages as $fieldMessage) {
                $fieldMessage = is_string($fieldMessage) ? trim($fieldMessage) : '';
                $safeErrors[$field][] = $fieldMessage !== ''
                    && strlen($fieldMessage) <= 240
                    && ! preg_match($unsafeContent, $fieldMessage)
                    ? $fieldMessage
                    : 'Please check this field and try again.';
            }
        }

        return $safeErrors;
    }
}
