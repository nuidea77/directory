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
     * Бүртгүүлэх: verify.mn баталгаажуулалт ЗААВАЛ — хэрэглэгч үүснэ,
     * гэхдээ токен зөвхөн дугаар нь баталгаажсаны дараа
     * (verificationStatus-аас) олгогдоно.
     */
    public function register(Request $request): JsonResponse
    {
        $existing = User::where('phone', (string) $request->input('phone'))->first();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^[0-9]{8}$/'],
            'email' => ['nullable', 'email', 'max:190', 'unique:users,email,'.($existing?->id ?? 'NULL')],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ], [
            'phone.regex' => 'Утасны дугаар 8 оронтой тоо байх ёстой.',
        ]);

        if ($existing !== null && $existing->hasVerifiedPhone()) {
            throw ValidationException::withMessages([
                'phone' => 'Энэ дугаараар аль хэдийн бүртгүүлсэн байна.',
            ]);
        }

        if ($existing !== null) {
            // Баталгаажуулалт дуусаагүй орхигдсон бүртгэл — нууц үг таарвал шинэчилж дахин эхлүүлнэ
            if (! Hash::check($data['password'], $existing->password)) {
                throw ValidationException::withMessages([
                    'phone' => 'Энэ дугаараар эхэлсэн бүртгэл байна. Өмнө оруулсан нууц үгээ ашиглана уу.',
                ]);
            }

            $existing->update(['name' => $data['name'], 'email' => $data['email'] ?? $existing->email]);
            $user = $existing;
        } else {
            $user = User::create($data);
        }

        $verification = $this->verifications->start($user->phone, 'register');

        return response()->json([
            'user' => new UserResource($user->refresh()),
            'verification' => new VerificationResource($verification),
        ], 201);
    }

    /**
     * Нэвтэрсэн (гэхдээ баталгаажаагүй) хэрэглэгч баталгаажуулалтаа дахин эхлүүлэх.
     */
    public function startVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedPhone()) {
            return response()->json(['message' => 'Дугаар аль хэдийн баталгаажсан.']);
        }

        $verification = $this->verifications->start($user->phone, 'register');

        return response()->json(['verification' => new VerificationResource($verification)]);
    }

    /**
     * Баталгаажуулалтын төлөв poll хийх (клиент ≥3 секунд тутам).
     * login/reset_password purpose-ууд verified болмогц нэг удаа token олгоно.
     */
    public function verificationStatus(Request $request, string $uuid): JsonResponse
    {
        $verification = PhoneVerification::where('uuid', $uuid)->firstOrFail();
        $verification = $this->verifications->check($verification);

        $payload = ['verification' => new VerificationResource($verification)];

        if ($verification->isVerified() && in_array($verification->purpose, ['register', 'login'], true)) {
            $user = User::where('phone', $verification->phone)->first();

            if ($user !== null && empty(($verification->meta ?? [])['consumed'])) {
                if ($user->phone_verified_at === null) {
                    $user->forceFill(['phone_verified_at' => now()])->save();
                }

                $verification->forceFill(['meta' => array_merge($verification->meta ?? [], ['consumed' => true])])->save();

                $payload['user'] = new UserResource($user);
                // Баталгаажилт дуусмагц нэвтрэх токен олгоно (бүртгэл + мессежээр нэвтрэх)
                $payload['token'] = $user->createToken('auth')->plainTextToken;
            }
        }

        return response()->json($payload);
    }

    /**
     * Нэвтрэх — нууц үгээр.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        // Утас эсвэл и-мэйлээр
        $user = User::where('phone', $data['phone'])
            ->orWhere('email', $data['phone'])
            ->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Нууц үг таарсангүй. 3 удаа буруу оруулбал 15 минут хаагдана.',
            ]);
        }

        return response()->json([
            'token' => $user->createToken($data['device_name'] ?? 'auth')->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Нэвтрэх — мессежээр (verify.mn session эхлүүлнэ).
     */
    public function loginBySms(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^[0-9]{8}$/', 'exists:users,phone'],
        ], [
            'phone.exists' => 'Энэ дугаараар бүртгэл олдсонгүй.',
        ]);

        $verification = $this->verifications->start($data['phone'], 'login');

        return response()->json(['verification' => new VerificationResource($verification)]);
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
     * Нууц үг сэргээх — 1-р шат (хүсэлт).
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
     * Нууц үг сэргээх — 2-р шат (шинэ нууц үг).
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
                'verification_uuid' => 'Энэ баталгаажуулалт аль хэдийн ашиглагдсан.',
            ]);
        }

        $user = User::where('phone', $verification->phone)->firstOrFail();
        $user->update(['password' => $data['password']]);
        $user->tokens()->delete(); // бүх төхөөрөмжөөс гаргана

        $verification->forceFill(['meta' => array_merge($verification->meta ?? [], ['consumed' => true])])->save();

        return response()->json([
            'message' => 'Нууц үг шинэчлэгдлээ.',
            'token' => $user->createToken('auth')->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }
}
