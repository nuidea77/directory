<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\VerificationResource;
use App\Models\PhoneVerification;
use App\Models\User;
use App\Services\VerifyMn\PhoneVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(protected PhoneVerificationService $verifications)
    {
    }

    /**
     * Step 1 of registration: validate input, start a verify.mn session.
     * The user record is only created once the phone is verified.
     */
    public function registerStart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^[0-9]{8}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ], [
            'phone.regex' => 'Утасны дугаар 8 оронтой тоо байх ёстой.',
            'phone.unique' => 'Энэ дугаараар аль хэдийн бүртгүүлсэн байна.',
        ]);

        $verification = $this->verifications->start($data['phone'], 'register', [
            'name' => $data['name'],
            'password_hash' => Hash::make($data['password']),
        ]);

        return $this->verificationStatusResponse($verification);
    }

    /**
     * Poll a verification's status. When a "register" verification flips to
     * verified, the user is created exactly once and an API token returned.
     * Frontend polls this every >= 3 seconds until verified or expired.
     */
    public function verificationStatus(Request $request, string $uuid): JsonResponse
    {
        $verification = PhoneVerification::where('uuid', $uuid)->firstOrFail();
        $verification = $this->verifications->check($verification);

        return $this->verificationStatusResponse($verification);
    }

    protected function verificationStatusResponse(PhoneVerification $verification): JsonResponse
    {
        $payload = ['verification' => new VerificationResource($verification)];

        if ($verification->isVerified() && $verification->purpose === 'register') {
            $meta = $verification->meta ?? [];

            if (empty($meta['consumed'])) {
                $user = User::firstOrCreate(
                    ['phone' => $verification->phone],
                    [
                        'name' => $meta['name'] ?? 'Хэрэглэгч',
                        'password' => $meta['password_hash'] ?? Hash::make(str()->random(32)),
                        'phone_verified_at' => now(),
                    ],
                );

                if ($user->phone_verified_at === null) {
                    $user->forceFill(['phone_verified_at' => now()])->save();
                }

                $verification->forceFill(['meta' => array_merge($meta, ['consumed' => true])])->save();

                $payload['token'] = $user->createToken('auth')->plainTextToken;
                $payload['user'] = new UserResource($user);
            }
        }

        return response()->json($payload);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('phone', $data['phone'])->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'phone' => 'Утасны дугаар эсвэл нууц үг буруу байна.',
            ]);
        }

        if (! $user->hasVerifiedPhone()) {
            throw ValidationException::withMessages([
                'phone' => 'Утасны дугаар баталгаажаагүй байна. Дахин бүртгүүлнэ үү.',
            ]);
        }

        return response()->json([
            'token' => $user->createToken($data['device_name'] ?? 'auth')->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Системээс гарлаа.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function updateProfile(Request $request): UserResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190', 'unique:users,email,'.$request->user()->id],
        ]);

        $request->user()->update($data);

        return new UserResource($request->user()->refresh());
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Одоогийн нууц үг буруу байна.',
            ]);
        }

        $request->user()->update(['password' => $data['password']]);

        return response()->json(['message' => 'Нууц үг солигдлоо.']);
    }

    /**
     * Step 1 of password reset: start a verify.mn session for an existing user.
     */
    public function resetStart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^[0-9]{8}$/', 'exists:users,phone'],
        ], [
            'phone.exists' => 'Энэ дугаараар бүртгэл олдсонгүй.',
        ]);

        $verification = $this->verifications->start($data['phone'], 'reset_password');

        return response()->json(['verification' => new VerificationResource($verification)]);
    }

    /**
     * Step 2 of password reset: once the verification is VERIFIED, set the new password.
     */
    public function resetConfirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'verification_uuid' => ['required', 'uuid'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ]);

        $verification = PhoneVerification::where('uuid', $data['verification_uuid'])
            ->where('purpose', 'reset_password')
            ->firstOrFail();

        $verification = $this->verifications->check($verification);

        if (! $verification->isVerified()) {
            throw ValidationException::withMessages([
                'verification_uuid' => 'Утасны баталгаажуулалт хийгдээгүй байна.',
            ]);
        }

        if (! empty(($verification->meta ?? [])['consumed'])) {
            throw ValidationException::withMessages([
                'verification_uuid' => 'Энэ баталгаажуулалт аль хэдийн ашиглагдсан байна.',
            ]);
        }

        $user = User::where('phone', $verification->phone)->firstOrFail();
        $user->update(['password' => $data['password']]);
        $user->tokens()->delete();

        $verification->forceFill(['meta' => array_merge($verification->meta ?? [], ['consumed' => true])])->save();

        return response()->json([
            'message' => 'Нууц үг шинэчлэгдлээ.',
            'token' => $user->createToken('auth')->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }
}
