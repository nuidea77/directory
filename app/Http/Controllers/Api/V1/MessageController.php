<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Message;
use App\Notifications\NewMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Хэрэглэгч талын зурвас: бизнес рүү бичих, өөрийн thread-үүдээ харах.
 */
class MessageController extends Controller
{
    public function threads(Request $request): JsonResponse
    {
        // Бүх мессежийг ачаалахгүй: thread бүрийн сүүлийн мөрийг window function-ээр
        // (MySQL 8 / SQLite 3.25+ хоёулаа дэмжинэ), unread-ийг aggregate-аар авна
        $userId = $request->user()->id;

        $lastMessages = Message::query()->fromSub(
            Message::query()
                ->select('*')
                ->selectRaw('ROW_NUMBER() OVER (PARTITION BY business_id ORDER BY created_at DESC, id DESC) as rn')
                ->where('user_id', $userId),
            'm',
        )->where('rn', 1)->orderByDesc('created_at')->limit(50)->with('business:id,name,slug,logo_path')->get();

        $unread = Message::query()
            ->where('user_id', $userId)
            ->where('sender', 'business')
            ->whereNull('read_at')
            ->selectRaw('business_id, count(*) as cnt')
            ->groupBy('business_id')
            ->pluck('cnt', 'business_id');

        $threads = $lastMessages->map(fn (Message $m) => [
            'business' => [
                'id' => $m->business->id,
                'name' => $m->business->name,
                'slug' => $m->business->slug,
            ],
            'last_message' => $m->body,
            'last_at' => $m->created_at,
            'unread' => (int) ($unread[$m->business_id] ?? 0),
        ]);

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

        $business->organization?->owner?->notify(new NewMessage($business, toOwner: true));

        return response()->json(['data' => $message], 201);
    }
}
