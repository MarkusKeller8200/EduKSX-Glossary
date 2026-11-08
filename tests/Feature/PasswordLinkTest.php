<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\UserAudit;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Passwort-Link durch die Verwaltung (#147). */
class PasswordLinkTest extends TestCase
{
    public function test_admin_sendet_link_protokolliert(): void
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@eduksx.ch']);
        Notification::fake();
        $user = $this->userWithRoles([Role::USER], 'b@example.org', twoFactor: false);

        $this->actingAs($this->admin());
        $this->post("/admin/benutzer/{$user->id}/passwort-link")->assertRedirect()->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $this->assertTrue(UserAudit::query()->where('action', 'password.reset_sent')->where('target_user_id', $user->id)->where('result', 'ok')->exists());
    }

    public function test_ohne_mailversand_oder_ohne_recht_abgewiesen(): void
    {
        Notification::fake();
        $user = $this->userWithRoles([Role::USER], 'b@example.org', twoFactor: false);
        $this->actingAs($this->admin());
        $this->post("/admin/benutzer/{$user->id}/passwort-link")->assertSessionHasErrors('konto');
        Notification::assertNothingSent();

        $this->actingAs($this->userWithRoles([Role::MODERATOR], 'm@example.org'));
        $this->post("/admin/benutzer/{$user->id}/passwort-link")->assertForbidden();
    }
}
