<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;

class UserController extends Controller
{
    /**
     * Display a listing of non-admin users.
     */
    public function index()
    {
        $users = User::where('role', '!=', 'admin')
            ->latest()
            ->paginate(5);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب المستخدمين بنجاح.',
            'data' => UserResource::collection($users->items()),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Display the specified non-admin user.
     */
    public function show($id)
    {
        $user = User::where('role', '!=', 'admin')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير موجود.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم جلب المستخدم بنجاح.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Update the status of the specified non-admin user.
     */
    public function updateStatus(UpdateUserStatusRequest $request, $id)
    {
        $user = User::where('role', '!=', 'admin')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير موجود.',
            ], 404);
        }

        $user->status = $request->boolean('status');
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة المستخدم بنجاح.',
            'data' => new UserResource($user),
        ]);
    }
}
