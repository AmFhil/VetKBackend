<?php

namespace App\Http\Controllers;

use App\Events\DirectMessageDeleted;
use App\Events\DirectMessageEdited;
use App\Events\DirectMessageSent;
use App\Models\Admin;
use App\Models\DirectMessage;
use App\Models\User;
use App\Traits\BroadcastsAdminEvents;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DirectMessageController extends Controller
{
    use BroadcastsAdminEvents;

    // Send Message
    public function send(Request $request)
    {
        $validated = $request->validate([
            'receiver_id' => 'required|exists:users_registration,id',
            'content'     => 'nullable|string|max:255',
            'images' => 'nullable|array',
            'images.*'    => 'nullable|image|mimes:jpg,png,gif,jpeg|max:2048',
        ]);

        $user = auth('user')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized.'], 403);

        $storedImages = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $storedImages[] = $image->store('direct_message_images', 'public');
            }
        }

        $message = DirectMessage::create([
            'sender_id'   => $user->id,
            'receiver_id' => $validated['receiver_id'],
            'content'     => $validated['content'] ?? null,
            'image'       => $storedImages,
            'type'  => 'text',
            'meta'  => []
        ]);

        event(new DirectMessageSent($message));

        // Notify receiver
        $receiver = User::find($validated['receiver_id']);

        // Log sender activity
        $user->logActivity(
            'send_message',
            'Message sent',
            "You sent a message to user {$receiver->first_name}",
            ['message_id' => $message->id]
        );

        if ($receiver) {
            $receiver->sendNotification(
                'direct_message',
                'New message received',
                "{$user->first_name} {$user->last_name} sent you a message.",
                ['message_id' => $message->id, 'sender_id' => $user->id]
            );
        }

        return response()->json($message, 201);
    }

    // Edit Message
    public function edit(Request $request, $id)
    {
        $user = auth('user')->user();
        $message = DirectMessage::where('id', $id)
            ->where('sender_id', $user->id)
            ->firstOrFail();

        if ($message->type = 'appointment' || $message->type = 'order') {
            return response()->json(['message' => 'unauthorized'], 400);
        }

        $request->validate(['contents' => 'required|string']);
        $message->update(['content' => $request->contents]);

        event(new DirectMessageEdited($message));

        $user->logActivity(
            'edit_message',
            'Message edited',
            "You edited message #{$message->id}",
            ['message_id' => $message->id]
        );

        $receiver = $message->receiver;
        if ($receiver) {
            $receiver->sendNotification(
                'direct_message_edit',
                'Message edited',
                "{$user->first_name} edited a message in your chat.",
                ['message_id' => $message->id, 'sender_id' => $user->id]
            );
        }

        return response()->json(['message' => 'Message updated', 'data' => $message]);
    }

    // Delete Message
    public function destroy($id)
    {
        $user = auth('user')->user();
        $message = DirectMessage::where('id', $id)
            ->where('sender_id', $user->id)
            ->firstOrFail();

        if ($message->type = 'appointment' || $message->type = 'order') {
            return response()->json(['message' => 'unauthorized'], 400);
        }

        event(new DirectMessageDeleted($message->id, $user->id, $message->receiver_id));


        $message->delete();

        $user instanceof User && $user->logActivity(
            'delete_message',
            'Message deleted',
            "You deleted message #{$id}",
            ['message_id' => $id]
        );

        $receiver = $message->receiver;
        if ($receiver) {
            $receiver->sendNotification(
                'direct_message_delete',
                'Message deleted',
                "{$user->first_name} deleted a message in your chat.",
                ['message_id' => $id, 'sender_id' => $user->id]
            );
        }

        return response()->json(['message' => 'Message deleted']);
    }

    // Flag Message
    public function flag($id)
    {
        $user = auth('user')->user();
        $message = DirectMessage::where('id', $id)
            ->where('receiver_id', $user->id) // only receiver can flag
            ->firstOrFail();

        $message->update(['is_flagged' => true]);


        $user instanceof User && $user->logActivity(
            'flag_message',
            'Message flagged',
            "You flagged message #{$message->id} from {$message->sender_id}",
            ['message_id' => $message->id]
        );

        // Notify admins
        foreach (Admin::all() as $admin) {
            $this->createAdminNotification(
                $admin->id,
                "Message flagged",
                "direct_message_flagged",
                "Message #{$message->id} was flagged by {$user->first_name} {$user->last_name}",
                ['message_id' => $message->id]
            );
        }

        return response()->json(['message' => 'Message flagged, admins notified']);
    }

    // Conversation with caching
    public function conversation($userId)
    {
        $user = auth('user')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized.'], 403);

        $cacheKey = "conversation_{$user->id}_{$userId}";
        $messages = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user, $userId) {
            return DirectMessage::with(['sender', 'receiver'])
                ->where(function ($q) use ($user, $userId) {
                    $q->where('sender_id', $user->id)
                        ->where('receiver_id', $userId);
                })
                ->orWhere(function ($q) use ($user, $userId) {
                    $q->where('sender_id', $userId)
                        ->where('receiver_id', $user->id);
                })
                ->orderBy('created_at', 'asc')
                ->get();
        });

        // mark messages as read
        DirectMessage::where('receiver_id', $user->id)
            ->where('sender_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json($messages);
    }

    // Sent Messages (cached)
    public function getAllSentMessage()
    {
        $user = auth('user')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized.'], 403);
        $cacheKey = "sent_messages_{$user->id}";

        $messages = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user) {
            return DirectMessage::where('sender_id', $user->id)
                ->with('receiver')
                ->orderBy('created_at', 'desc')
                ->get();
        });

        return response()->json($messages, 200);
    }

    // ✅ Received Messages (cached)
    public function getAllReceivedMessage()
    {
        $user = auth('user')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized.'], 403);
        $cacheKey = "received_messages_{$user->id}";

        $messages = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user) {
            return DirectMessage::where('receiver_id', $user->id)
                ->with('sender')
                ->orderBy('created_at', 'desc')
                ->get();
        });

        return response()->json($messages, 200);
    }

    public function getChatList()
    {
        $user = auth('user')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized.'], 403);
        $cacheKey = "chat_list_{$user->id}";

        $chatList = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user) {
            return DirectMessage::with(['sender.profile', 'receiver.profile'])
                ->where(function ($q) use ($user) {
                    $q->where('sender_id', $user->id)
                        ->orWhere('receiver_id', $user->id);
                })
                ->orderBy('created_at', 'desc')
                ->get();
        });

        $conversations = collect();

        foreach ($chatList as $msg) {
            $partnerId = $msg->sender_id === $user->id ? $msg->receiver_id : $msg->sender_id;

            // skip duplicates
            if ($conversations->has($partnerId)) continue;

            $partner = $msg->sender_id === $user->id ? $msg->receiver : $msg->sender;

            // count unread messages
            $unreadCount = DirectMessage::where('sender_id', $partnerId)
                ->where('receiver_id', $user->id)
                ->where('is_read', false)
                ->count();

            // format the time
            $formattedTime = Carbon::parse($msg->created_at)->diffForHumans(null, true);

            $conversations->put($partnerId, [
                'user' => [
                    'id' => $partner->id,
                    'name' => trim("{$partner->first_name} {$partner->last_name}"),
                    'email' => $partner->email,
                    'location' => trim("{$partner->country} {$partner->state}"),
                    'profile_image' => $partner->profile->profile_picture ?? null,
                    'role' => $partner->activeRole?->name

                ],
                'last_message' => $msg->content,
                'last_message_images' => $msg->image_urls ?? [],
                'last_message_time' => $msg->created_at,
                'last_message_display_time' => $formattedTime,
                'unread_count' => $unreadCount
            ]);
        }

        // sort chats to the top
        $conversation = $conversations->sortByDesc('last_message_time')->values();

        return response()->json($conversation, 200);
    }
}
