<?php

namespace App\Http\Controllers\Api\V1\Console;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Message;
use App\Notifications\NewMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Бизнес талын зурвасын inbox.
 */
class ConsoleMessageController extends Controller
{
    public function threads(Request $request, Business $business): JsonResponse
    {
        $this->authorizeOwner($request, $business);

        // Thread бүрийн сүүлийн мөр window function-ээр, unread aggregate-аар
        $lastMessages = Message::query()->fromSub(
            Message::query()
                ->select('*')
                ->selectRaw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY created_at DESC, id DESC) as rn')
                ->where('business_id', $business->id),
            'm',
        )->where('rn', 1)->orderByDesc('created_at')->limit(100)->with('user:id,name')->get();

        $unread = Message::query()
            ->where('business_id', $business->id)
            ->where('sender', 'user')
            ->whereNull('read_at')
            ->selectRaw('user_id, count(*) as cnt')
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id');

        $threads = $lastMessages->map(fn (Message $m) => [
            'user' => [
                'id' => $m->user->id,
                'name' => $m->user->name,
            ],
            'last_message' => $m->body,
            'last_at' => $m->created_at,
            'unread' => (int) ($unread[$m->user_id] ?? 0),
        ]);

        return response()->json(['data' => $threads]);
    }

    public function show(Request $request, Business $business, User $user): JsonResponse
    {
        $this->authorizeOwner($request, $business);

        $messages = Message::where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->oldest()
            ->get(['id', 'sender', 'body', 'created_at']);

        Message::where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->where('sender', 'user')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['data' => $messages]);
    }

    public function reply(Request $request, Business $business, User $user): JsonResponse
    {
        $this->authorizeOwner($request, $business);

        // Зөвхөн өмнө нь бичсэн хэрэглэгчид хариулж болно (спамаас сэргийлнэ)
        abort_unless(
            Message::where('business_id', $business->id)->where('user_id', $user->id)->exists(),
            404,
        );

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $message = Message::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'sender' => 'business',
            'body' => $data['body'],
        ]);

        $user->notify(new NewMessage($business, toOwner: false));

        return response()->json(['data' => $message], 201);
    }

    protected function authorizeOwner(Request $request, Business $business): void
    {
        abort_unless($business->organization->owner_id === $request->user()->id, 403);
    }
}
