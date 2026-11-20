<?php

namespace App\Console\Commands;

use App\Models\RestreamTarget;
use Illuminate\Console\Command;

/**
 * Daemons de restream huérfanos: procesos `restream_daemon/main.py` cuyo
 * destino ya no existe en la base (borrado, forzado) o cuyo PID se perdió.
 *
 * Un daemon huérfano sigue empujando a YouTube (o a la plataforma que sea)
 * sin que nadie lo pueda ver en el panel: la emisión cobra penalización y
 * consume ancho de banda. Este comando los encuentra y los detiene.
 */
class ReapOrphanRestreamDaemonsCommand extends Command
{
    protected $signature = 'restream:reap-orphan-daemons
        {--dry-run : Only report the orphan daemons without stopping them}';

    protected $description = 'Detiene los daemons de restream cuyo destino ya no existe (emisiones huérfanas).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $daemons = $this->runningDaemons();
        if ($daemons === []) {
            $this->line('No hay daemons de restream en ejecución.');

            return self::SUCCESS;
        }

        $reaped = 0;
        foreach ($daemons as $pid => $targetId) {
            $target = RestreamTarget::find($targetId);

            if ($target) {
                // El destino existe: solo sincronizamos el PID perdido para
                // que el botón "Detener" pueda alcanzarlo.
                if ((int) $target->pipeline_pid !== $pid) {
                    $target->update(['pipeline_pid' => $pid]);
                    $this->line("Target {$targetId}: PID real {$pid} adoptado (la fila tenía " . var_export($target->getOriginal('pipeline_pid'), true) . ').');
                }
                continue;
            }

            $this->warn("Daemon huérfano detectado: pid={$pid} target_id={$targetId} (el destino no existe).");

            if ($dryRun) {
                continue;
            }

            $stopped = $this->stopDaemon($pid, $targetId);
            $reaped++;

            $this->info($stopped
                ? "Daemon huérfano detenido: pid={$pid} target_id={$targetId}."
                : "No se pudo detener el daemon huérfano pid={$pid} target_id={$targetId} (permisos). Se dejó el flag de parada.");
        }

        if ($dryRun && $reaped === 0) {
            $this->line('Dry-run: no se detuvo nada.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string> pid => target_id
     */
    private function runningDaemons(): array
    {
        $found = [];

        foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $file) {
            $pid = (int) basename(dirname($file));
            if ($pid === getmypid()) {
                continue;
            }

            $raw = @file_get_contents($file);
            if ($raw === false) {
                continue;
            }

            $cmdline = str_replace("\0", ' ', $raw);
            if (! preg_match('~restream_daemon/main\.py --target-id=([0-9a-f-]{36})~', $cmdline, $m)) {
                continue;
            }

            $found[$pid] = $m[1];
        }

        return $found;
    }

    private function stopDaemon(int $pid, string $targetId): bool
    {
        $signalled = function_exists('posix_kill') && @posix_kill($pid, SIGTERM);

        if (! $signalled) {
            // Sin permisos para señalarlo (corre como otro usuario): se le
            // deja el flag de parada que el daemon sondea cada 5 s.
            $path = storage_path('app/restream-daemons/' . $targetId . '.stop');
            if (! is_dir(dirname($path))) {
                @mkdir(dirname($path), 0775, true);
            }
            @file_put_contents($path, (string) now()->getTimestamp());
        }

        $deadline = time() + 12;
        while (time() < $deadline) {
            if (! $this->processAlive($pid)) {
                return true;
            }
            usleep(500_000);
        }

        if (function_exists('posix_kill')) {
            @posix_kill($pid, SIGKILL);
            usleep(300_000);
        }

        return ! $this->processAlive($pid);
    }

    /**
     * Un zombie ya no ejecuta nada: cuenta como muerto.
     */
    private function processAlive(int $pid): bool
    {
        if ($pid <= 0 || ! @file_exists("/proc/{$pid}")) {
            return false;
        }

        $stat = @file_get_contents("/proc/{$pid}/stat");
        if ($stat === false) {
            return true;
        }

        return substr($stat, strrpos($stat, ')') + 2, 1) !== 'Z';
    }
}
