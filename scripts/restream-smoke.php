<?php

/**
 * Restream module smoke test (local-only, gitignored).
 * Run with: php scripts/restream-smoke.php
 *
 * Exercises the full lifecycle: create quota → create target → toggle
 * enabled → cap enforcement → downgrade rejection → disable quota.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\RestreamQuota;
use App\Models\RestreamTarget;
use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== Restream smoke ===\n";

$admin = User::where('role', 'admin')->first();
$client = User::where('role', 'client')->whereNull('owner_id')->first();
$channel = Channel::first();

if (! $admin || ! $client || ! $channel) {
    echo "Missing seed data: need admin, client (titular), and at least one channel.\n";
    exit(1);
}

echo "Admin : {$admin->email}\n";
echo "Client: {$client->email}\n";
echo "Channel: {$channel->display_name}\n";

// Clean previous smoke leftovers
RestreamTarget::where('user_id', $client->id)->delete();
RestreamQuota::where('user_id', $client->id)->delete();

DB::transaction(function () use ($client, $admin) {
    RestreamQuota::create([
        'user_id' => $client->id,
        'enabled' => true,
        'max_outputs' => 2,
        'granted_by' => $admin->id,
        'granted_at' => now(),
        'notes' => 'smoke',
    ]);
});

$quota = $client->fresh()->restreamQuota;
echo "Quota created: max_outputs={$quota->max_outputs} enabled=" . var_export((bool) $quota->enabled, true) . "\n";
echo "  remaining slots: {$client->fresh()->restreamRemainingSlots()}\n";

$t = RestreamTarget::create([
    'user_id' => $client->id,
    'channel_id' => $channel->id,
    'platform' => 'facebook',
    'name' => 'Smoke FB',
    'destination_url' => 'rtmps://live-api-s.facebook.com:443/rtmp/smoke',
    'stream_key' => 'smoke-secret-key-1234',
    'enabled' => true,
    'status' => 'idle',
    'created_by' => $client->id,
]);
echo "Target created: enabled={$t->enabled} status={$t->status}\n";

$t->update(['enabled' => false]);
echo "Target disabled: enabled=" . var_export((bool) $t->fresh()->enabled, true) . "\n";
echo "  remaining slots: {$client->fresh()->restreamRemainingSlots()}\n";

// Cap test: create two enabled targets and try a third
$t->update(['enabled' => true]);
RestreamTarget::create([
    'user_id' => $client->id, 'channel_id' => $channel->id,
    'platform' => 'tiktok', 'name' => 'Smoke TT',
    'destination_url' => 'rtmps://rtmp-push.tiktok.com/smoke',
    'stream_key' => 'smoke-key-tiktok-9999',
    'enabled' => true, 'status' => 'idle', 'created_by' => $client->id,
]);
echo "Two active targets. remaining=" . $client->fresh()->restreamRemainingSlots() . "\n";

$guard = app(App\Services\Restream\RestreamQuotaGuard::class);
echo "canEnableAnother? " . var_export($guard->canEnableAnother($client->fresh()), true) . "\n";

// Downgrade rejection: quota is 2 and we have 2 active targets, try to set max_outputs=1
$quota->refresh();
$rejected = false;
try {
    (new App\Http\Controllers\Admin\RestreamQuotaController($guard))
        ->update(new Illuminate\Http\Request(['max_outputs' => 1]), $client);
} catch (Illuminate\Validation\ValidationException $e) {
    $rejected = true;
    echo "Downgrade rejected (expected): " . collect($e->errors())->flatten()->first() . "\n";
}
echo "Downgrade rejected: " . var_export($rejected, true) . "\n";

// Disable quota
$quota->update(['enabled' => false]);
echo "Quota disabled. hasRestreamEnabled=" . var_export($client->fresh()->hasRestreamEnabled(), true) . "\n";

// Audit count
$auditCount = AuditLog::whereIn('action', [
    'create.restream_quota', 'update.restream_quota', 'delete.restream_quota',
    'create.restream_target', 'update.restream_target', 'delete.restream_target',
])->count();
echo "Audit entries (module): {$auditCount}\n";

// Cleanup
RestreamTarget::where('user_id', $client->id)->delete();
RestreamQuota::where('user_id', $client->id)->delete();
echo "Cleanup OK\n";