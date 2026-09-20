<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $serveur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->serveur = User::where('email', 'serveur@demo.test')->firstOrFail();
        User::whereKey($this->serveur->id)->update([
            'password' => Hash::make('test-password'),
        ]);
        $this->serveur->refresh();
    }

    public function test_login_page_is_available(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Connexion / تسجيل الدخول');
    }

    public function test_active_staff_user_can_login(): void
    {
        $response = $this->post(route('login'), [
            'email' => 'serveur@demo.test',
            'password' => 'test-password',
        ]);

        $response->assertRedirect('/staff');
        $this->assertAuthenticatedAs($this->serveur);
        $this->assertNotNull($this->serveur->fresh()->last_login_at);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $response = $this->from(route('login'))->post(route('login'), [
            'email' => 'serveur@demo.test',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $this->actingAs($this->serveur)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
