<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get unread notifications for the auth user (or global admins ones)
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $userRole = $request->user()->role->name;

        $query = Notification::where('is_read', false);
        
        // Admins can see global null alerts too
        if ($userRole === 'Admin') {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhereNull('user_id'); 
            });
        } else {
            $query->where('user_id', $userId);
        }

        return response()->json($query->orderByDesc('created_at')->get());
    }

    /**
     * Mark as read
     */
    public function markAsRead(Notification $notification, Request $request)
    {
        if ($notification->user_id !== null && $notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Notification marquée comme lue.']);
    }

    /**
     * Mark all as read
     */
    public function markAllAsRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)->update(['is_read' => true]);
        
        if ($request->user()->role->name === 'Admin') {
            Notification::whereNull('user_id')->update(['is_read' => true]);
        }

        return response()->json(['message' => 'Toutes les notifications marquées comme lues.']);
    }
}
