<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Хэрэглэгч талын зурвас: бизнес рүү бичих, өөрийн thread-үүдээ харах.
 */
class MessageController extends Controller
{
    public function threads(Request $request): JsonResponse
    {
        $threads = Message::query()
            ->where('user_id', $request->user()->id)
            ->with('business:id,name,slug,logo_path')
            ->latest()
            ->get()
            ->groupBy('business_id')
            ->map(fn ($messages) => [
                'business' => [
                    'id' => $messages->first()->business->id,
                    'name' => $messages->first()->business->name,
                    'slug' => $messages->first()->business->slug,
                ],
                'last_message' => $messages->first()->body,
                'last_at' => $messages->first()->created_at,
                'unread' => $messages->where('sender', 'business')->whereNull('read_at')->count(),
            ])
            ->values();

        return response()->json(['data' => $threads]);
    }

    public function show(Request $request, Business $business): JsonResponse
    {
        $messages = Message::where('business_id', $business->id)
            ->where('user_id', $request->user()->id)
            ->oldest()
            ->get(['id', 'sender', 'body', 'created_at']);

        Message::where('business_id', $business->id)
            ->where('user_id', $request->user()->id)
            ->where('sender', 'business')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['data' => $messages]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $message = Message::create([
            'business_id' => $business->id,
            'user_id' => $request->user()->id,
            'sender' => 'user',
            'body' => $data['body'],
        ]);

        return response()->json(['data' => $message], 201);
    }
}
