<?php

namespace Tests\Feature\Auth;

use App\Mail\PasswordChangedNotification;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_service_writes_audit_row(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $row = AuditLog::where('action', 'update.user.password')
            ->where('entity_id', $user->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertNull($row->before);
        $this->assertSame('self', $row->after['changed_by']);
        $this->assertArrayNotHasKey('password', $row->after);
    }

    public function test_self_service_dispatches_mailable(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])->assertSessionHasNoErrors();

        Mail::assertQueued(PasswordChangedNotification::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->changedBy === 'self'
                && $mail->actor === null;
        });
    }

    public function test_self_service_invalidates_other_sessions(): void
    {
        $user = User::factory()->create();

        \DB::table('sessions')->insert([
            [
                'id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'user_id' => $user->id,
                'payload' => 'a',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                'user_id' => $user->id,
                'payload' => 'b',
                'last_activity' => now()->timestamp,
            ],
        ]);

        $this->withSession(['_token' => 'test'])->actingAs($user);
        session()->setId('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');

        $user->setRememberToken('oldtoken');
        $user->save();

        $this
            ->actingAs($user)
            ->withSession([])
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])->assertSessionHasNoErrors();

        $remaining = \DB::table('sessions')->where('user_id', $user->id)
            ->where('id', '!=', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')
            ->count();

        $this->assertSame(0, $remaining);
    }

    public function test_self_service_rotates_remember_token(): void
    {
        $user = User::factory()->create(['remember_token' => 'oldtoken']);

        $this
            ->actingAs($user)
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotSame('oldtoken', $user->remember_token);
        $this->assertNotNull($user->remember_token);
    }

    public function test_profile_endpoint_rejects_password_field_empty(): void
    {
        $user = User::factory()->create();
        $originalHash = $user->password;

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
                'password' => '',
            ]);

        $response->assertSessionHasErrors('password');
        $this->assertSame($originalHash, $user->fresh()->password);
    }

    public function test_profile_endpoint_rejects_password_field_with_value(): void
    {
        $user = User::factory()->create();
        $originalHash = $user->password;

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
                'password' => 'wannabe-new-pass',
                'current_password' => 'password',
            ]);

        $response->assertSessionHasErrors(['password', 'current_password']);
        $this->assertSame($originalHash, $user->fresh()->password);
    }

    public function test_profile_endpoint_rejects_password_confirmation(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
                'password_confirmation' => 'foo',
            ]);

        $response->assertSessionHasErrors('password_confirmation');
    }

    public function test_profile_endpoint_without_password_fields_works(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');
    }
}
