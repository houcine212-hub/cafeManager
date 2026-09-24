<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $manager;
    protected User $serveur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->manager = User::where('email', 'manager@demo.test')->firstOrFail();
        $this->serveur = User::where('email', 'serveur@demo.test')->firstOrFail();
    }

    public function test_manager_can_view_and_add_a_team_member(): void
    {
        $this->actingAs($this->manager)
            ->get(route('staff.team'))
            ->assertOk()
            ->assertSee('Gérez votre équipe');

        $this->actingAs($this->manager)
            ->post(route('staff.team.store'), [
                'name' => 'Nadia Serveur',
                'email' => 'nadia@demo.test',
                'password' => 'strong-password',
                'password_confirmation' => 'strong-password',
            ])
            ->assertRedirect(route('staff.team'));

        $this->assertDatabaseHas('users', [
            'cafe_id' => $this->manager->cafe_id,
            'name' => 'Nadia Serveur',
            'email' => 'nadia@demo.test',
            'is_active' => true,
        ]);
    }

    public function test_manager_can_deactivate_and_reactivate_a_serveur(): void
    {
        $this->actingAs($this->manager)
            ->patch(route('staff.team.status', $this->serveur->id), [
                'is_active' => false,
            ])
            ->assertRedirect(route('staff.team'));

        $this->assertDatabaseHas('users', [
            'id' => $this->serveur->id,
            'is_active' => false,
        ]);

        $this->actingAs($this->manager)
            ->patch(route('staff.team.status', $this->serveur->id), [
                'is_active' => true,
            ])
            ->assertRedirect(route('staff.team'));

        $this->assertDatabaseHas('users', [
            'id' => $this->serveur->id,
            'is_active' => true,
        ]);
    }

    public function test_serveur_cannot_manage_the_team(): void
    {
        $this->actingAs($this->serveur)
            ->get(route('staff.team'))
            ->assertForbidden();

        $this->actingAs($this->serveur)
            ->post(route('staff.team.store'), [
                'name' => 'Unauthorized Member',
                'email' => 'unauthorized@demo.test',
                'password' => 'strong-password',
                'password_confirmation' => 'strong-password',
            ])
            ->assertForbidden();
    }
}
