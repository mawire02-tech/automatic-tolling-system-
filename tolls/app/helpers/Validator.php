<?php
// app/helpers/Validator.php

class Validator {
    private array $errors = [];
    private array $data   = [];

    public function validate(array $data, array $rules): self {
        $this->data   = $data;
        $this->errors = [];

        foreach ($rules as $field => $ruleStr) {
            $rulesList = explode('|', $ruleStr);
            $value     = $data[$field] ?? null;

            foreach ($rulesList as $rule) {
                [$ruleName, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $label = ucfirst(str_replace('_', ' ', $field));

                switch ($ruleName) {
                    case 'required':
                        if ($value === null || $value === '') $this->errors[$field][] = "{$label} is required.";
                        break;
                    case 'email':
                        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) $this->errors[$field][] = "Invalid email address.";
                        break;
                    case 'min':
                        if ($value !== null && strlen((string)$value) < (int)$param) $this->errors[$field][] = "{$label} must be at least {$param} characters.";
                        break;
                    case 'max':
                        if ($value !== null && strlen((string)$value) > (int)$param) $this->errors[$field][] = "{$label} must not exceed {$param} characters.";
                        break;
                    case 'numeric':
                        if ($value !== null && $value !== '' && !is_numeric($value)) $this->errors[$field][] = "{$label} must be a number.";
                        break;
                    case 'min_val':
                        if ($value !== null && (float)$value < (float)$param) $this->errors[$field][] = "{$label} must be at least {$param}.";
                        break;
                    case 'alpha_num':
                        if ($value && !ctype_alnum(str_replace(['-','_',' '], '', $value))) $this->errors[$field][] = "{$label} contains invalid characters.";
                        break;
                    case 'in':
                        $allowed = explode(',', $param);
                        if ($value && !in_array($value, $allowed)) $this->errors[$field][] = "{$label} has an invalid value.";
                        break;
                        case 'alpha':
                        if ($value && !ctype_alpha(str_replace(' ', '', $value))) {
                            $this->errors[$field][] = "{$label} should contain alphabetic characters only.";
                        }
                        break;
                    case 'phone':
                        if ($value && !preg_match('/^07\d{8}$/', $value)) {
                            $this->errors[$field][] = "{$label} must be a valid phone number starting with 07 followed by 8 digits.";
                        }
                        break;
                        case 'rfid':
                        if ($value && !preg_match('/^[A-Fa-f0-9]{2}(:[A-Fa-f0-9]{2}){3}$/', $value)) {
                            $this->errors[$field][] = "{$label} must be a valid RFID tag in the format AA:BB:CC:DD.";
                        }
                        break;
                        case 'year':
                        $currentYear = date('Y');

                        if ($value && (!ctype_digit($value) || $value > $currentYear)) {
                            $this->errors[$field][] = "{$label} must not be greater than the current year.";
                        }
                        break;
                    case 'password':
                        if ($value && strlen($value) < 8) $this->errors[$field][] = "Password must be at least 8 characters.";
                        if ($value && !preg_match('/[A-Z]/', $value)) $this->errors[$field][] = "Password must contain at least one uppercase letter.";
                        if ($value && !preg_match('/[0-9]/', $value)) $this->errors[$field][] = "Password must contain at least one number.";
                        break;
                }
            }
        }
        return $this;
    }

    public function fails(): bool   { return !empty($this->errors); }
    public function passes(): bool  { return empty($this->errors); }
    public function errors(): array { return $this->errors; }
    public function firstError(): string {
        foreach ($this->errors as $msgs) return reset($msgs);
        return '';
    }
    public function allErrors(): array {
        $flat = [];
        foreach ($this->errors as $msgs) foreach ($msgs as $m) $flat[] = $m;
        return $flat;
    }
}

// ── Currency helper available in all views ─────────────────
function currency(float $amount = null): string {
    $sym = Security::currency();
    if ($amount === null) return $sym;
    return $sym . number_format($amount, 2);
}
