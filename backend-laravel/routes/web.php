<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Directly serve uploaded storage files (KYC documents, commodity images, etc.) reliably
Route::get('/storage/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);
    if (!file_exists($filePath)) {
        // Fallback to app/
        $altPath = storage_path('app/' . $path);
        if (file_exists($altPath)) {
            $filePath = $altPath;
        } else {
            abort(404, 'File not found');
        }
    }
    $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
    return response()->file($filePath, [
        'Content-Type' => $mimeType,
        'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*');
