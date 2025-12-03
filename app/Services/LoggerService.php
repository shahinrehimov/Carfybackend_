<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class LoggerService
{
    private static ?LoggerService $instance = null;
    private bool $enabled;

    private function __construct() {
        $this->enabled = config('logger.enabled');
    }

    public static function instance(): LoggerService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function log(string $message, array $context = [], bool $required = false, $type = 'info'): void
    {
        if (!in_array($type, ['alert', 'info', 'error', 'warning'])) {
            $type = 'info';
        }

        if ($required || $this->enabled) {
            Log::{$type}($message, $context);
        }
    }
}
