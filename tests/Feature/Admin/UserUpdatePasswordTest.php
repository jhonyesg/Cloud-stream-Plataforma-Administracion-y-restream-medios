<?php

namespace Tests\Feature\Admin;

use App\Mail\PasswordChangedNotification;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserUpdatePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    public function test_admin_password_reset_writes_audit_row(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $target = User::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->putJson("/admin/users/{$target->id}", [
                'username' => 'audit_target_1',
                'email' => $target->email,
                'display_name' => $target->display_name,
                'password' => 'new-admin-pass-123',
                'role' => 'client',
                'status' => 'active',
                'owner_id' => null,
            ]);

        $response->assertOk();

        $row = AuditLog::where('action', 'update.user.password')
            ->where('entity_id', $target->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame($admin->id, $row->user_id);
        $this->assertSame('admin', $row->after['changed_by']);
        $this->assertNull($row->before);
    }

    public function test_admin_password_reset_kills_all_sessions(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        \DB::table('sessions')->insert([
            ['id' => 'ses1', 'user_id' => $target->id, 'payload' => 'a', 'last_activity' => now()->timestamp],
            ['id' => 'ses2', 'user_id' => $target->id, 'payload' => 'b', 'last_activity' => now()->timestamp],
        ]);

        $this
            ->actingAs($admin)
            ->putJson("/admin/users/{$target->id}", [
                'username' => 'onesession_target',
                'email' => $target->email,
                'display_name' => $target->display_name,
                'password' => 'new-admin-pass-123',
                'role' => 'client',
                'status' => 'active',
                'owner_id' => $target->owner_id,
            ])->assertOk();

        $this->assertSame(0, \DB::table('sessions')->where('user_id', $target->id)->count());
    }

    public function test_admin_password_reset_dispatches_mailable_to_affected_user(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $target = User::factory()->create();

        $this
            ->actingAs($admin)
            ->putJson("/admin/users/{$target->id}", [
                'username' => 'mailable_target',
                'email' => $target->email,
                'display_name' => $target->display_name,
                'password' => 'new-admin-pass-123',
                'role' => 'client',
                'status' => 'active',
                'owner_id' => $target->owner_id,
            ])->assertOk();

        Mail::assertQueued(PasswordChangedNotification::class, function ($mail) use ($target, $admin) {
            return $mail->hasTo($target->email)
                && $mail->changedBy === 'admin'
                && $mail->actor?->id === $admin->id;
        });
    }

    public function test_admin_password_reset_with_empty_password_does_not_audit(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->putJson("/admin/users/{$target->id}", [
                'username' => 'emptypw_target',
                'email' => $target->email,
                'display_name' => 'Updated Name',
                'password' => '',
                'role' => 'client',
                'status' => 'active',
                'owner_id' => $target->owner_id,
            ]);

        $response->assertOk();

        $this->assertSame(0, AuditLog::where('action', 'update.user.password')
            ->where('entity_id', $target->id)->count());
    }

    public function test_admin_password_reset_rotates_remember_token(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['remember_token' => 'oldtoken']);

        $this
            ->actingAs($admin)
            ->putJson("/admin/users/{$target->id}", [
                'username' => 'rotatetoken_target',
                'email' => $target->email,
                'display_name' => $target->display_name,
                'password' => 'new-admin-pass-123',
                'role' => 'client',
                'status' => 'active',
                'owner_id' => $target->owner_id,
            ])->assertOk();

        $this->assertNotSame('oldtoken', $target->fresh()->remember_token);
    }
}
