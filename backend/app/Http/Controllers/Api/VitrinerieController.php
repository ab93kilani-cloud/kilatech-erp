<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Models\Client;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class VitrinerieController extends Controller
{
    public function getQuotes()
    {
        return response()->json(
            Quote::with(['client', 'lines.variant'])->where('source', 'web')->get()
        );
    }

    public function getClients()
    {
        return response()->json(
            Client::where('source', 'web')->get()
        );
    }

    public function getMessages()
    {
        return response()->json(
            ContactMessage::orderBy('created_at', 'desc')->get()
        );
    }

    public function markMessageAsRead($id)
    {
        $msg = ContactMessage::findOrFail($id);
        $msg->update(['status' => 'read']);
        return response()->json(['message' => 'Status updated']);
    }

    public function deleteMessage($id)
    {
        $msg = ContactMessage::findOrFail($id);
        $msg->delete();
        return response()->json(['message' => 'Message deleted']);
    }
}
