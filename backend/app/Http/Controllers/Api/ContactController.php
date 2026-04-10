<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Store a message from the public contact form.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|max:255',
            'phone'      => 'nullable|string|max:50',
            'country'    => 'nullable|string|max:100',
            'message'    => 'required|string|max:2000',
        ]);

        $message = ContactMessage::create($validated);

        return response()->json([
            'message' => 'Votre message a été envoyé avec succès. Notre équipe vous contactera sous peu.'
        ], 201);
    }

    /**
     * List messages for the ERP (Vitrinerie)
     */
    public function index()
    {
        return response()->json(ContactMessage::orderBy('created_at', 'desc')->get());
    }

    /**
     * Mark as read
     */
    public function markAsRead($id)
    {
        $msg = ContactMessage::findOrFail($id);
        $msg->update(['status' => 'read']);
        return response()->json(['message' => 'Message marqué comme lu']);
    }
    
    /**
     * Delete message
     */
    public function destroy($id)
    {
        $msg = ContactMessage::findOrFail($id);
        $msg->delete();
        return response()->json(['message' => 'Message supprimé']);
    }
}
