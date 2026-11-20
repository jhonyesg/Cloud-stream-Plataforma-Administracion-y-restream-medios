<?php

namespace Tests\Feature\Services;

use App\Models\Channel;
use App\Models\RestreamTarget;
use App\Models\User;
use App\Services\Restream\RestreamOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El botón "Detener" del panel de Restream tenía dos fallos que lo dejaban
 * sin efecto:
 *
 *   1. si `pipeline_pid` estaba en NULL (porque un stop anterior lo vació, o
 *      porque el daemon lo había perdido) el stop() no hacía NADA: solo
 *      marcaba la fila como idle mientras el daemon seguía empujando a
 *      YouTube;
 *   2. si el daemon corría como otro usuario (root, porque el cron del
 *      scheduler corre como root) el `posix_kill()` fallaba con EPERM y el
 *      check de vida (`posix_kill($pid, 0)`) confundía EPERM con "el proceso
 *      ya no existe": el orquestador reportaba "stopped" sin haber parado
 *      nada.
 */
class RestreamOrchestratorStopTest extends TestCase
{
    use RefreshDatabase;

    private function makeTarget(array $overrides = []): RestreamTarget
    {
        $owner = User::factory()->create(['role' => 'client', 'status' => 'active']);
        $channel = Channel::create([
            'owner_id' => $owner->id,
            'display_name' => 'Canal Stop Test',
            'slug' => 'canal-stop-test-' . uniqid(),
            'status' => 'active',
        ]);

        return RestreamTarget::create(array_merge([
            'user_id' => $owner->id,
            'channel_id' => $channel->id,
            'created_by' => $owner->id,
            'platform' => 'youtube',
            'name' => 'Destino Stop Test',
            'destination_url' => 'rtmp://a.rtmp.youtube.com/live2',
            'stream_key' => 'test-stream-key-123456',
            'enabled' => true,
            'status' => 'live',
        ], $overrides));
    }

    /**
     * Proceso falso que se hace pasar por el daemon de este destino.
     */
    private function spawnFakeDaemon(string $targetId): int
    {
        $cmd = sprintf(
            'exec -a "python3 /www/wwwroot/cloudstream.mediaserver.com.co/restream_daemon/main.py --target-id=%s" sleep 120',
            $targetId
        );

        $handle = proc_open(['bash', '-c', $cmd], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        $status = proc_get_status($handle);

        return (int) $status['pid'];
    }

    private function spawnFakePipeline(string $destination, string $key): int
    {
        $cmd = sprintf(
            'exec -a "ffmpeg -re -i rtmp://127.0.0.1/live/x -f flv %s/%s" sleep 120',
            $destination,
            $key
        );

        $handle = proc_open(['bash', '-c', $cmd], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        $status = proc_get_status($handle);

        return (int) $status['pid'];
    }

    private function processAlive(int $pid): bool
    {
        if ($pid <= 0 || ! @file_exists("/proc/{$pid}")) {
            return false;
        }

        $stat = @file_get_contents("/proc/{$pid}/stat");
        if ($stat === false) {
            return true;
        }

        // Un zombie no ejecuta nada: ya está muerto, solo espera a ser
        // recolectado por su padre.
        return substr($stat, strrpos($stat, ')') + 2, 1) !== 'Z';
    }

    public function test_stop_kills_the_daemon_even_when_the_pipeline_pid_was_lost(): void
    {
        $target = $this->makeTarget();
        $pid = $this->spawnFakeDaemon($target->id);

        // El caso real: la fila perdió el PID (stop anterior, o daemon
        // arrancado por el cron) y el daemon sigue vivo.
        $target->update(['pipeline_pid' => null]);
        $this->assertTrue($this->processAlive($pid));

        $result = app(RestreamOrchestrator::class)->stop($target);

        $this->assertTrue($result['stopped'], 'El daemon debió detenerse');
        $this->assertSame($pid, $result['pid']);
        $this->assertFalse($this->processAlive($pid), 'El proceso del daemon debe estar muerto');

        $fresh = $target->fresh();
        $this->assertNull($fresh->pipeline_pid);
        $this->assertSame('idle', $fresh->status);
    }

    public function test_stop_kills_the_daemon_when_the_pipeline_pid_points_to_the_ffmpeg_child(): void
    {
        $target = $this->makeTarget();
        $daemonPid = $this->spawnFakeDaemon($target->id);
        $childPid = $this->spawnFakePipeline($target->destination_url, 'test-stream-key-123456');

        // El PID guardado es el del hijo ffmpeg (bug histórico de los
        // heartbeats): el stop debe encontrar el daemon real, no el hijo.
        $target->update(['pipeline_pid' => $childPid]);

        $result = app(RestreamOrchestrator::class)->stop($target);

        $this->assertTrue($result['stopped']);
        $this->assertSame($daemonPid, $result['pid']);
        $this->assertFalse($this->processAlive($daemonPid));
        $this->assertFalse($this->processAlive($childPid), 'El ffmpeg huérfano también debe morir');
    }

    public function test_stop_without_a_running_daemon_is_a_successful_no_op(): void
    {
        $target = $this->makeTarget(['pipeline_pid' => null]);

        $result = app(RestreamOrchestrator::class)->stop($target);

        $this->assertTrue($result['stopped']);
        $this->assertNull($result['pid']);
        $this->assertSame('none', $result['method']);
        $this->assertSame('idle', $target->fresh()->status);
    }

    public function test_stop_never_leaves_an_orphan_ffmpeg_pushing_to_the_destination(): void
    {
        $target = $this->makeTarget();
        $orphan = $this->spawnFakePipeline($target->destination_url, 'test-stream-key-123456');

        $this->assertTrue($this->processAlive($orphan));

        $result = app(RestreamOrchestrator::class)->stop($target);

        $this->assertTrue($result['stopped']);
        $this->assertFalse($this->processAlive($orphan), 'El ffmpeg huérfano debe morir con el stop');
    }
}
