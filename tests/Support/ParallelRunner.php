<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * Runs PHP scripts as truly parallel processes with proc_open, which works on Windows
 * (no pcntl). Output goes to temp files rather than pipes: Windows pipes can't be read
 * without blocking, and a full pipe would stall a child.
 *
 * Start barrier: each child gets PARALLEL_BARRIER (a directory). Once booted it calls
 * ParallelRunner::awaitStart(), which reports ready and waits until every child is ready,
 * so slow boots on a busy machine don't turn a race into a queue.
 */
final class ParallelRunner
{
    /**
     * @param  list<list<string>>  $argumentSets  one process per set
     * @param  array<string, string>  $env  added to the current environment
     * @return list<array{exit: int, stdout: string, stderr: string}>
     */
    public static function run(string $script, array $argumentSets, array $env = [], int $timeoutSeconds = 120): array
    {
        $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'parallel-'.bin2hex(random_bytes(6));
        mkdir($barrier);
        $environment = array_merge(getenv(), ['XDEBUG_MODE' => 'off', 'PARALLEL_BARRIER' => $barrier], $env);
        $processes = [];

        foreach ($argumentSets as $i => $arguments) {
            $stdout = (string) tempnam(sys_get_temp_dir(), 'par');
            $stderr = (string) tempnam(sys_get_temp_dir(), 'par');

            $process = proc_open(
                [PHP_BINARY, $script, ...$arguments],
                [0 => ['pipe', 'r'], 1 => ['file', $stdout, 'w'], 2 => ['file', $stderr, 'w']],
                $pipes,
                base_path(),
                $environment,
            );

            if (! is_resource($process)) {
                throw new RuntimeException("Could not start worker {$i}.");
            }

            fclose($pipes[0]);
            $processes[$i] = ['process' => $process, 'stdout' => $stdout, 'stderr' => $stderr, 'exit' => null];
        }

        $deadline = microtime(true) + $timeoutSeconds;

        // Release everyone once all are ready, or as soon as one dies before getting there.
        while (count(glob($barrier.DIRECTORY_SEPARATOR.'ready-*') ?: []) < count($processes) && self::poll($processes) === count($processes)) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Parallel workers never became ready.');
            }

            usleep(5_000);
        }
        touch($barrier.DIRECTORY_SEPARATOR.'go');

        while (true) {
            $running = self::poll($processes);

            if ($running === 0) {
                break;
            }

            if (microtime(true) > $deadline) {
                foreach ($processes as $p) {
                    proc_terminate($p['process']);
                }
                throw new RuntimeException('Parallel workers timed out.');
            }

            usleep(20_000);
        }

        $results = [];
        foreach ($processes as $p) {
            proc_close($p['process']);
            $results[] = [
                'exit' => (int) $p['exit'],
                'stdout' => (string) file_get_contents($p['stdout']),
                'stderr' => (string) file_get_contents($p['stderr']),
            ];
            @unlink($p['stdout']);
            @unlink($p['stderr']);
        }

        array_map('unlink', glob($barrier.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($barrier);

        return $results;
    }

    /**
     * Called by a child once it has booted: blocks until the parent says go.
     */
    public static function awaitStart(): void
    {
        $barrier = getenv('PARALLEL_BARRIER');
        if ($barrier === false || ! is_dir($barrier)) {
            return;
        }

        touch($barrier.DIRECTORY_SEPARATOR.'ready-'.getmypid());
        $go = $barrier.DIRECTORY_SEPARATOR.'go';
        $deadline = microtime(true) + 60;

        while (! file_exists($go) && microtime(true) < $deadline) {
            usleep(500);
            clearstatcache(true, $go);
        }
    }

    /**
     * Records exit codes (proc_get_status reports one only once) and counts running children.
     *
     * @param  array<int, array{process: resource, stdout: string, stderr: string, exit: int|null}>  $processes
     */
    private static function poll(array &$processes): int
    {
        $running = 0;

        foreach ($processes as $i => $p) {
            if ($p['exit'] !== null) {
                continue;
            }

            $status = proc_get_status($p['process']);
            if ($status['running']) {
                $running++;
            } else {
                $processes[$i]['exit'] = $status['exitcode'];
            }
        }

        return $running;
    }
}
