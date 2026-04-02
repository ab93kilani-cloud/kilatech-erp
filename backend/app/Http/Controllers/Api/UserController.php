<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct()
    {
        // Automatically check roles for all methods in this controller
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            $roleName = $user->role->name ?? '';
            
            // Allow Admin and Gérant to view lists
            if (!in_array($roleName, ['Admin', 'Gérant'])) {
                abort(403, 'Accès non autorisé.');
            }

            // ONLY Admin can create, edit or delete accounts
            if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']) && $roleName !== 'Admin') {
                abort(403, 'Seul un Administrateur peut modifier les comptes.');
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        return response()->json(User::with('role')->get());
    }

    public function roles()
    {
        return response()->json(Role::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role_id' => 'required|exists:roles,id',
            'is_active' => 'boolean'
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password_hash' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json($user->load('role'), 201);
    }

    public function show(User $user)
    {
        return response()->json($user->load('role'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'role_id' => 'sometimes|required|exists:roles,id',
            'is_active' => 'boolean'
        ]);

        if (isset($validated['password']) && !empty($validated['password'])) {
            $validated['password_hash'] = Hash::make($validated['password']);
        }
        unset($validated['password']);

        $user->update($validated);

        return response()->json($user->load('role'));
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            abort(403, 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        $user->delete();
        return response()->json(null, 204);
    }
}
