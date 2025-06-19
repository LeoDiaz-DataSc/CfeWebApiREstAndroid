<?php
/**
 * Simple input validator utility
 */
class Validator {
    public static function required(array $data, array $fields) {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                $missing[] = $field;
            }
        }
        return $missing;
    }
    public static function email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    public static function maxLength($value, $length) {
        return mb_strlen($value) <= $length;
    }
}
