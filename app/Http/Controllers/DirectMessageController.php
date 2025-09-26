<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\DirectMessage;
use App\Models\User;
use App\Traits\BroadcastsAdminEvents;
use Illuminate\Http\Request;

class DirectMessageController extends Controller
{
    use BroadcastsAdminEvents;

    // Send Message
    public function send(Request $request)
    {
        $validated = $request->validate([
            'receiver_id' => 'required|exists:users_registration,id',
            'content'     => 'nullable|string|max:255',
            'images.*'    => 'nullable|file|mimes:jpg,png,gif,jpeg|max:2048',
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $storedImages = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('direct_message_images', 'public');
                $storedImages[] = $path;
            }
        }

        $message = DirectMessage::create([
            'sender_id'   => $user->id,
            'receiver_id' => $validated['receiver_id'],
            'content'     => $validated['content'] ?? null,
            'image'       => $storedImages,
        ]);

        // Log sender activity
        $user->logActivity(
            action: 'send_message',
            title: 'Message sent',
            detail: "You sent a message to user {$validated['receiver_id']}",
            meta: ['message_id' => $message->id]
        );

        // Notify receiver
        $receiver = User::find($validated['receiver_id']);
        if ($receiver) {
            $receiver->sendNotification(
                type: 'direct_message',
                title: 'New message received',
                content: "{$user->first_name} {$user->last_name} sent you a message.",
                meta: ['message_id' => $message->id, 'sender_id' => $user->id]
            );
        }

        return response()->json($message, 201);
    }

    // Edit Message
    public function edit(Request $request, $id)
    {
        $user = auth()->user();
        $message = DirectMessage::where('id', $id)
                          ->where('sender_id', $user->id)
                          ->firstOrFail();

        $request->validate(['content' => 'required|string']);
        $message->update(['content' => $request->content]);

        // Activity log
        $user->logActivity(
            action: 'edit_message',
            title: 'Message edited',
            detail: "You edited a message #{$message->id}",
            meta: ['message_id' => $message->id]
        );

        // Notify receiver about edit
        $receiver = $message->receiver;
        if ($receiver) {
            $receiver->sendNotification(
                type: 'direct_message_edit',
                title: 'Message edited',
                content: "{$user->first_name} edited a message in your chat.",
                meta: ['message_id' => $message->id, 'sender_id' => $user->id]
            );
        }

        return response()->json(['message' => 'Message updated', 'data' => $message]);
    }

    // ✅ Delete Message
    public function destroy($id)
    {
        $user = auth()->user();
        $message = DirectMessage::where('id', $id)
                          ->where('sender_id', $user->id)
                          ->firstOrFail();

        $message->delete();

        // Activity log
        $user->logActivity(
            action: 'delete_message',
            title: 'Message deleted',
            detail: "You deleted message #{$message->id}",
            meta: ['message_id' => $message->id]
        );

        // Notify receiver about delete
        $receiver = $message->receiver;
        if ($receiver) {
            $receiver->sendNotification(
                type: 'direct_message_delete',
                title: 'Message deleted',
                content: "{$user->first_name} deleted a message in your chat.",
                meta: ['message_id' => $message->id, 'sender_id' => $user->id]
            );
        }

        return response()->json(['message' => 'Message deleted']);
    }

    // ✅ Flag Message
    public function flag($id)
    {
        $user = auth()->user();
        $message = DirectMessage::where('id', $id)
            ->where('receiver_id', $user->id) // only receiver can flag
            ->firstOrFail();

        $message->update(['is_flagged' => true]);

        // 📝 Activity log
        $user->logActivity(
            action: 'flag_message',
            title: 'Message flagged',
            detail: "You flagged message #{$message->id} from {$message->sender_id}",
            meta: ['message_id' => $message->id]
        );

        // Notify sender (their msg was flagged)
        // $sender = $message->sender;
        // if ($sender) {
        //     $sender->sendNotification(
        //         type: 'direct_message_flagged',
        //         title: 'Your message was flagged',
        //         content: "{$user->first_name} flagged your message.",
        //         meta: ['message_id' => $message->id, 'receiver_id' => $user->id]
        //     );
        // }

        // Notify admins (like before)
        $admins = Admin::all();
        foreach ($admins as $admin) {
            $this->createAdminNotification(
                adminId: $admin->id,
                title: "Message flagged",
                type: "direct_message_flagged",
                content: "Message #{$message->id} was flagged by {$user->first_name} {$user->last_name}",
                meta: ['message_id' => $message->id]
            );
            $this->createAdminActivity(
                adminId: $admin->id,
                action: "message_flagged",
                title: "Direct message flagged",
                detail: "Message #{$message->id} flagged by user {$user->id}.",
                meta: ['message_id' => $message->id]
            );
        }

        return response()->json(['message' => 'Message flagged, admins and sender notified']);
    }

    // get messages between auth user and another user
    public function conversation($userId)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $messages = DirectMessage::where(function ($query) use ($user, $userId) {
            $query->where('sender_id', $user->id)
                  ->where('receiver_id', $userId);
        })->orWhere(function ($query) use ($user, $userId) {
            $query->where('sender_id', $userId)
                  ->where('receiver_id', $user->id);
        })->orderBy('created_at', 'asc')->get();

        // mark messages as read if receiver is auth user
        DirectMessage::where('receiver_id', $user->id)
            ->where('sender_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json($messages);
    }
}
