<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PhoneVerification;
use App\Services\VerifyMn\PhoneVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * verify.mn fires a GET request here (no body, no signature) once the user's
 * SMS is received. It is only a "check now" hint: the authoritative status is
 * re-fetched from GET /sessions/:sessionId before anything is marked verified.
 * The URL carries a per-verification secret token so it cannot be guessed.
 */
class VerifyMnCallbackController extends Controller
{
    public function __invoke(Request $request, string $verification, PhoneVerificationService $verifications): JsonResponse
    {
        $record = PhoneVerification::where('uuid', $verification)->first();

        if ($record === null || ! hash_equals($record->callback_token, (string) $request->query('token'))) {
            // Always answer 200 quickly — verify.mn retries non-200 up to 5 times.
            return response()->json(['ok' => false]);
        }

        // Bypass the polling throttle: a callback means the status just changed.
        $record->forceFill(['last_checked_at' => null])->save();
        $verifications->check($record);

        return response()->json(['ok' => true]);
    }
}
