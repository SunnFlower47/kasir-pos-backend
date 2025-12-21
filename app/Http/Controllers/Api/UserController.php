<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->can('users.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $query = User::with(['outlet', 'roles']);

        // Search functionality
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->has('role')) {
            $role = $request->get('role');
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        // Filter by outlet
        if ($request->has('outlet_id')) {
            $query->where('outlet_id', $request->get('outlet_id'));
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->get('per_page', 15);
        $users = $query->paginate($perPage);

        // Transform users to include role as string
        $users->getCollection()->transform(function ($user) {
            $user->role = $user->roles->first()?->name ?? 'No Role';
            return $user;
        });

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->can('users.create')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/'],
            'role' => 'required|string|exists:roles,name',
            'outlet_id' => 'nullable|exists:outlets,id',
            'is_active' => 'boolean'
        ]);

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'outlet_id' => $request->outlet_id,
            'is_active' => $request->is_active ?? true
        ];

        $newUser = User::create($userData);

        // Assign role
        if ($request->role) {
            $newUser->assignRole($request->role);
        }

        // Load relationships
        $newUser->load(['outlet', 'roles']);
        $newUser->role = $newUser->roles->first()?->name ?? 'No Role';

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $newUser
        ], 201);
    }

    /**
     * Display the specified user
     */
    public function show(User $user): JsonResponse
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        if (!$authUser || !$authUser->can('users.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $user->load(['outlet', 'roles.permissions']);
        $user->role = $user->roles->first()?->name ?? 'No Role';

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user): JsonResponse
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        if (!$authUser || !$authUser->can('users.edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|string|exists:roles,name',
            'outlet_id' => 'nullable|exists:outlets,id',
            'is_active' => 'boolean'
        ]);

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'outlet_id' => $request->outlet_id,
            'is_active' => $request->is_active ?? $user->is_active
        ];

        // Only update password if provided
        if ($request->password) {
            $userData['password'] = Hash::make($request->password);
        }

        $user->update($userData);

        // Update role
        if ($request->role) {
            $user->syncRoles([$request->role]);
        }

        // Load relationships
        $user->load(['outlet', 'roles']);
        $user->role = $user->roles->first()?->name ?? 'No Role';

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user): JsonResponse
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        if (!$authUser || !$authUser->can('users.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Prevent deleting self
        if ($user->id === $authUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete your own account'
            ], 400);
        }

        // Prevent deleting Super Admin if not Super Admin
        if ($user->hasRole('Super Admin') && !$authUser->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete Super Admin account'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Get available roles
     */
    public function getRoles(): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->can('users.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $roles = Role::with('permissions')->get();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Get user permissions
     */
    public function getPermissions(User $user): JsonResponse
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        if (!$authUser || !$authUser->can('users.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $permissions = $user->getAllPermissions();

        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }

    /**
     * Get all permissions
     */
    public function getAllPermissions(): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->can('users.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $permissions = Permission::all();

        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }

    /**
     * Update role permissions
     */
    public function updateRolePermissions(Request $request, $roleId): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->can('users.edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        $role = Role::findOrFail($roleId);
        $permissions = Permission::whereIn('id', $request->permissions)->get();

        $role->syncPermissions($permissions);

        return response()->json([
            'success' => true,
            'message' => 'Role permissions updated successfully',
            'data' => $role->load('permissions')
        ]);
    }
}
