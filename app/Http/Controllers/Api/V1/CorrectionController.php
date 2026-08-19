<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Correction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Залруулга: хэрэглэгч буруу хаяг/утас/цагийг мэдэгдэж,
 * админ хүлээн авч бүртгэлд тусгана.
 */
class CorrectionController extends Controller
{
    public function store(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($branch->status === 'active', 404);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:1000'],
        ], [
            'text.required' => 'Юуг залруулахаа бичнэ үү.',
        ]);

        $correction = Correction::create([
            'branch_id' => $branch->id,
            'user_id' => $request->user()->id,
            'text' => $data['text'],
        ]);

        return response()->json([
            'message' => 'Залруулгын хүсэлт хүлээн авлаа. Редакц шалгаад бүртгэлд тусгана.',
            'data' => $correction,
        ], 201);
    }
}
