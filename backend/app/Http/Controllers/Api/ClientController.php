<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index()
    {
        return response()->json(Client::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:company,individual',
            'company_name' => 'required_if:type,company|nullable|string|max:255',
            'contact_name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'required_if:type,individual|nullable|string|max:50',
            'telephone_fix' => 'nullable|string|max:50',
            'fax' => 'nullable|string|max:50',
            'address' => 'required_if:type,individual|nullable|string',
            'cin' => 'required_if:type,individual|nullable|string|max:50',
            'ice' => 'required_if:type,company|nullable|string|max:100',
            'rc' => 'nullable|string|max:100',
            'if' => 'nullable|string|max:100',
            'patente' => 'nullable|string|max:100',
        ]);

        $client = Client::create($validated);

        return response()->json($client, 201);
    }

    public function show(Client $client)
    {
        return response()->json($client->load(['quotes', 'orders']));
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'type' => 'required|in:company,individual',
            'company_name' => 'required_if:type,company|nullable|string|max:255',
            'contact_name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'required_if:type,individual|nullable|string|max:50',
            'telephone_fix' => 'nullable|string|max:50',
            'fax' => 'nullable|string|max:50',
            'address' => 'required_if:type,individual|nullable|string',
            'cin' => 'required_if:type,individual|nullable|string|max:50',
            'ice' => 'required_if:type,company|nullable|string|max:100',
            'rc' => 'nullable|string|max:100',
            'if' => 'nullable|string|max:100',
            'patente' => 'nullable|string|max:100',
        ]);

        $client->update($validated);

        return response()->json($client);
    }

    public function destroy(Client $client)
    {
        $client->delete();
        return response()->json(null, 204);
    }
}
