<?php

namespace App\Libraries;

use CodeIgniter\Log\Handlers\FileHandler;
use CodeIgniter\Log\Logger;
use Config\Logger as LoggerConfig;

class SlowQueryLogger
{
    protected $logger;
    protected $threshold;

    public function __construct()
    {
        try {
            $this->threshold = (float) (env('database.slowQueryThreshold') ?? 1.0);

            $path = WRITEPATH . 'logs/slow_queries/';
            if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
                throw new \RuntimeException('Folder slow query tidak bisa dibuat: ' . $path);
            }

            // Logger CI 4.4 wajib menerima objek config, bukan array. Clone
            // supaya threshold/handler logger utama tidak ikut berubah.
            $config            = clone config(LoggerConfig::class);
            $config->threshold = 9;
            $config->handlers  = [
                FileHandler::class => [
                    'handles'         => ['warning'],
                    'path'            => $path,
                    'fileExtension'   => 'log',
                    'filePermissions' => 0644,
                ],
            ];

            $this->logger = new Logger($config, false);
        } catch (\Throwable $e) {
            // Fallback ke system log jika error
            log_message('error', 'Failed to initialize SlowQueryLogger: ' . $e->getMessage());
            $this->logger    = service('logger');
            $this->threshold = 1.0;
        }
    }

    public function logQuery(float $duration, string $query, array $bindings = [])
    {
        try {
            if ($duration > $this->threshold) {
                $message = sprintf(
                    "[%s] Slow Query (%.3fs): %s",
                    date('Y-m-d H:i:s'),
                    $duration,
                    $this->formatQuery($query, $bindings)
                );

                $this->logger->log('warning', $message);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Failed to log slow query: ' . $e->getMessage());
        }
    }


    protected function formatQuery(string $query, array $bindings): string
    {
        if (empty($bindings)) {
            return $query;
        }

        $indexed = array_values($bindings) === $bindings;
        foreach ($bindings as $key => $value) {
            $value = is_string($value) ? "'{$value}'" : $value;
            if ($indexed) {
                $query = preg_replace('/\?/', $value, $query, 1);
            } else {
                $query = str_replace(':' . $key, $value, $query);
            }
        }

        return $query;
    }
}
