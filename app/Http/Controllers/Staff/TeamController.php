<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $this->assertManager($request);

        $members = User::query()
            ->where('cafe_id', $request->user()->cafe_id)
            ->with('role')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('staff.team', [
            'members' => $members,
            'role' => $request->user()->role?->name ?? 'staff',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assertManager($request);
        $cafeId = (int) $request->user()->cafe_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:100',
                Rule::unique('users', 'email'),
            ],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $serveurRole = Role::firstOrCreate(
            ['name' => 'serveur'],
            ['description' => 'Personnel de salle et gestion des commandes']
        );

        User::create([
            'cafe_id' => $cafeId,
            'role_id' => $serveurRole->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_active' => true,
        ]);

        return redirect()->route('staff.team')
    ->with('status', 'Membre ajouté à votre équipe.');
    }

    public function updateStatus(Request $request, int $memberId): RedirectResponse
    {
        $this->assertManager($request);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $member = User::query()
            ->where('cafe_id', $request->user()->cafe_id)
            ->whereKey($memberId)
            ->firstOrFail();

        abort_if($member->isManager(), 422, 'Le compte manager principal ne peut pas être désactivé.');

        $member->forceFill([
            'is_active' => (bool) $validated['is_active'],
        ])->save();

        return redirect()->route('staff.team')
    ->with('status', 'Statut du membre mis à jour.');
    }

    private function assertManager(Request $request): void
    {
        abort_unless($request->user()?->isManager(), 403);
    }
}
