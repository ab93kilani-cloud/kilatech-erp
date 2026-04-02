<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    /**
     * Download a protected file from storage with forced download headers.
     */
    public function download(Request $request, $path)
    {
        // Path is usually something like 'bank_statements/xxx.pdf'
        // We ensure it exists in the 'public' disk (which is where we store our accounting files)
        if (!Storage::disk('public')->exists($path)) {
            abort(404, 'Le fichier demandé est introuvable.');
        }

        $fileName = basename($path);
        
        return Storage::disk('public')->download($path, $fileName, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }
}
