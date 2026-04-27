<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * List users (Owner only)
     */
    public function index()
    {
        $this->authorizeOwner();

        return response()->json(
            User::select('id', 'name', 'email', 'role', 'is_active')
                ->where('name', '!=', 'Deleted User')
                ->get()
        );
    }

    /**
     * Create user (owner only)
     */
    public function store(Request $request)
    {
        $this->authorizeOwner();

        $validated = $request->validate([
            'name'          => 'required|string',
            'email'         => 'required|email|unique:users',
            'password'      => 'required|min:8',
            'role'          => 'required|in:owner,manager,cashier',
        ]);

        $user = User::create([
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'password'      => Hash::make($validated['password']),
            'role'          => $validated['role'],
        ]);

        return response()->json($user, 201);
    }

    /**
     * Update user (role / status / profile)
     */
    public function update(Request $request, User $user)
    {
        $this->authorizeOwner();

        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'email'         => 'sometimes|email|unique:users,email,' . $user->id,
            'password'      => 'nullable|min:8',
            'role'          => 'sometimes|in:owner,manager,cashier',
            'is_active'     => 'boolean',
        ]);

        // Hash password if provided
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json($user);
    }

    /**
     * Soft deactivate user
     */
    public function destroy(User $user)
    {
        $this->authorizeOwner();

        // Prevent self-deactivation
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'You cannot deactivate yourself'], 422);
        }

        $user->update(['is_active' => false]);

        return response()->json(['message' => 'User deactivated']);
    }

    /**
     * Permanently delete user (Owner only)
     *
     * Anonymizes the user instead of hard-deleting to preserve
     * foreign key references in sales, purchase orders, etc.
     */
    public function forceDelete(User $user)
    {
        $this->authorizeOwner();

        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'You cannot delete your own account'], 422);
        }

        // Anonymize and deactivate rather than hard-delete,
        // because the user row is referenced by purchase_orders,
        // sales, stock_movements, audit_logs, etc.
        $user->update([
            'name'      => 'Deleted User',
            'email'     => 'deleted_' . $user->id . '@deleted.local',
            'password'  => Hash::make(str()->random(40)),
            'is_active' => false,
        ]);

        // Revoke all tokens so they're immediately logged out
        $user->tokens()->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Owner Gate
     */
    private function authorizeOwner(): void
    {
        abort_unless(
            auth()->user()?->role === 'owner',
            403,
            'Unauthorized'
        );
    }
}