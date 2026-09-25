<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Mail\OtpMail;
use App\Models\Cart;
use App\Models\PasswordOtp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $imageName = null;

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = 'user_' . rand(100000, 999999) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('img/users'), $imageName);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'image' => $imageName,
            'role' => 'user',
            'status' => true,
        ]);

        Cart::firstOrCreate([
            'user_id' => $user->id,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الحساب بنجاح.',
            'token' => $token,
            'data' => new UserResource($user),
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $login = $request->login;

        $user = User::where('email', $login)
            ->orWhere('phone', $login)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'البريد الإلكتروني أو رقم الهاتف أو كلمة المرور غير صحيحة.',
            ], 401);
        }

        if (!$user->status) {
            return response()->json([
                'success' => false,
                'message' => 'حسابك غير مفعل.',
            ], 403);
        }

        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        Cart::firstOrCreate([
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح.',
            'token' => $token,
            'data' => new UserResource($user),
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => new UserResource($request->user()),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $request->user();

        $imageName = $user->image;

        if ($request->hasFile('image')) {
            if ($user->image && File::exists(public_path('img/users/' . $user->image))) {
                File::delete(public_path('img/users/' . $user->image));
            }

            $image = $request->file('image');
            $imageName = 'user_' . rand(100000, 999999) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('img/users'), $imageName);
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'image' => $imageName,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الملف الشخصي بنجاح.',
            'data' => new UserResource($user->fresh()),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'كلمة المرور الحالية غير صحيحة.',
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح، يرجى تسجيل الدخول مرة أخرى.',
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح.',
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'البريد الإلكتروني غير موجود.',
            ], 404);
        }

        $oldOtp = PasswordOtp::where('email', $request->email)->first();

        if ($oldOtp && $oldOtp->created_at->diffInSeconds(now()) < 60) {
            return response()->json([
                'success' => false,
                'message' => 'يرجى الانتظار 60 ثانية قبل طلب رمز تحقق جديد.',
            ], 429);
        }

        PasswordOtp::where('email', $request->email)->delete();

        $otp = rand(100000, 999999);

        PasswordOtp::create([
            'email' => $request->email,
            'otp' => $otp,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        Mail::to($request->email)->send(new OtpMail($otp));

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال رمز التحقق بنجاح.',
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        $otp = PasswordOtp::where('email', $request->email)
            ->where('otp', $request->otp)
            ->first();

        if (!$otp) {
            return response()->json([
                'success' => false,
                'message' => 'رمز التحقق غير صحيح.',
            ], 400);
        }

        if ($otp->expires_at->isPast()) {
            $otp->delete();

            return response()->json([
                'success' => false,
                'message' => 'انتهت صلاحية رمز التحقق.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'رمز التحقق صحيح.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $otp = PasswordOtp::where('email', $request->email)
            ->where('otp', $request->otp)
            ->first();

        if (!$otp) {
            return response()->json([
                'success' => false,
                'message' => 'رمز التحقق غير صحيح.',
            ], 400);
        }

        if ($otp->expires_at->isPast()) {
            $otp->delete();

            return response()->json([
                'success' => false,
                'message' => 'انتهت صلاحية رمز التحقق.',
            ], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير موجود.',
            ], 404);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        $otp->delete();

        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح.',
        ]);
    }
}
