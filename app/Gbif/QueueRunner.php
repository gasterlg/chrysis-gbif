<?php

namespace App\Gbif;

use Illuminate\Support\Facades\Log;

/**
 * Starts a detached `queue:work --stop-when-empty` after the UI queues imports, so imports run
 * without cron or a resident worker (Mac `artisan serve` and staging php-fpm alike).
 * At most one worker at a time (pid file); the worker exits when the queue is empty.
 */
class QueueRunner
{
    public function pidFile(): string
    {
        return storage_path('framework/gbif-worker.pid');
    }

    public function isRunning(): bool
    {
        $pid = (int) @file_get_contents($this->pidFile());

        return $pid > 0 && function_exists('posix_kill') && @posix_kill($pid, 0);
    }

    /** @return bool true if a worker was started (false: sync queue, disabled, or one already running) */
    public function kick(): bool
    {
        if (! config('gbif.worker.auto') || config('queue.default') === 'sync' || $this->isRunning()) {
            return false;
        }

        $cmd = sprintf('cd %s && nohup %s artisan queue:work --stop-when-empty --tries=1 --timeout=1800 >> %s 2>&1 < /dev/null & echo $!',
            escapeshellarg(base_path()), escapeshellarg($this->phpBinary()), escapeshellarg(storage_path('logs/worker.log')));
        $pid = (int) trim((string) shell_exec($cmd));
        if ($pid <= 0) {
            Log::warning('gbif: could not start queue worker', ['cmd' => $cmd]);

            return false;
        }
        file_put_contents($this->pidFile(), (string) $pid);

        return true;
    }

    public function phpBinary(): string
    {
        if ($bin = config('gbif.worker.php')) {
            return $bin;
        }
        // Under php-fpm PHP_BINARY is the fpm daemon; use the CLI next to it.
        if (str_contains(PHP_BINARY, 'fpm')) {
            foreach (['/usr/bin/php'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION, '/usr/bin/php', '/usr/local/bin/php'] as $c) {
                if (is_executable($c)) {
                    return $c;
                }
            }

            return 'php';
        }

        return PHP_BINARY;
    }
}
