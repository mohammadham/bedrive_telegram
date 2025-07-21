<?php

namespace App\Http\Controllers;

use App\Models\FileEntry;
use App\Models\ShortLink;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Common\Core\BaseController;

class ShortLinkController extends BaseController
{
    /**
     * Create a short link for a file
     */
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_id' => 'required|integer|exists:file_entries,id',
            'password' => 'nullable|string|min:4|max:50',
            'expires_at' => 'nullable|date|after:now',
            'max_downloads' => 'nullable|integer|min:1|max:10000',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        try {
            $user = Auth::user();
            $fileEntry = FileEntry::where('id', $request->input('file_id'))
                                 ->where('user_id', $user->id)
                                 ->firstOrFail();

            // Check if user already has a short link for this file
            $existingLink = ShortLink::where('file_entry_id', $fileEntry->id)
                                   ->where('user_id', $user->id)
                                   ->where('is_active', true)
                                   ->first();

            if ($existingLink) {
                return $this->error('A short link already exists for this file. Delete it first to create a new one.', 409);
            }

            $shortLink = ShortLink::create([
                'short_code' => ShortLink::generateShortCode(),
                'file_entry_id' => $fileEntry->id,
                'user_id' => $user->id,
                'password' => $request->input('password') ? Hash::make($request->input('password')) : null,
                'expires_at' => $request->input('expires_at') ? Carbon::parse($request->input('expires_at')) : null,
                'max_downloads' => $request->input('max_downloads'),
            ]);

            return $this->success([
                'short_link' => $shortLink,
                'short_url' => $shortLink->short_url,
            ]);

        } catch (\Exception $e) {
            Log::error('Short link creation error: ' . $e->getMessage());
            return $this->error('Failed to create short link: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's short links
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $perPage = $request->input('per_page', 20);

            $shortLinks = ShortLink::with('fileEntry')
                                  ->where('user_id', $user->id)
                                  ->orderBy('created_at', 'desc')
                                  ->paginate($perPage);

            // Add statistics to each link
            $shortLinks->getCollection()->transform(function ($link) {
                $link->stats = $link->getAccessStats();
                $link->short_url = $link->short_url;
                return $link;
            });

            return $this->success($shortLinks);

        } catch (\Exception $e) {
            Log::error('Short links list error: ' . $e->getMessage());
            return $this->error('Failed to get short links: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get short link details
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $shortLink = ShortLink::with('fileEntry')
                                 ->where('id', $id)
                                 ->where('user_id', $user->id)
                                 ->firstOrFail();

            $shortLink->stats = $shortLink->getAccessStats();
            $shortLink->short_url = $shortLink->short_url;

            return $this->success($shortLink);

        } catch (\Exception $e) {
            Log::error('Short link show error: ' . $e->getMessage());
            return $this->error('Short link not found', 404);
        }
    }

    /**
     * Update short link
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'nullable|string|min:4|max:50',
            'expires_at' => 'nullable|date|after:now',
            'max_downloads' => 'nullable|integer|min:1|max:10000',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        try {
            $user = Auth::user();
            $shortLink = ShortLink::where('id', $id)
                                 ->where('user_id', $user->id)
                                 ->firstOrFail();

            $updateData = [];

            if ($request->has('password')) {
                $updateData['password'] = $request->input('password') ? Hash::make($request->input('password')) : null;
            }

            if ($request->has('expires_at')) {
                $updateData['expires_at'] = $request->input('expires_at') ? Carbon::parse($request->input('expires_at')) : null;
            }

            if ($request->has('max_downloads')) {
                $updateData['max_downloads'] = $request->input('max_downloads');
            }

            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->input('is_active');
            }

            $shortLink->update($updateData);

            return $this->success([
                'short_link' => $shortLink->fresh(),
                'message' => 'Short link updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Short link update error: ' . $e->getMessage());
            return $this->error('Failed to update short link: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete short link
     */
    public function delete($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $shortLink = ShortLink::where('id', $id)
                                 ->where('user_id', $user->id)
                                 ->firstOrFail();

            $shortLink->delete();

            return $this->success(['message' => 'Short link deleted successfully']);

        } catch (\Exception $e) {
            Log::error('Short link delete error: ' . $e->getMessage());
            return $this->error('Failed to delete short link: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Access short link (public endpoint)
     */
    public function access($shortCode): JsonResponse
    {
        try {
            $shortLink = ShortLink::with('fileEntry')
                                 ->where('short_code', $shortCode)
                                 ->firstOrFail();

            if (!$shortLink->isValid()) {
                return $this->error('This link has expired or is no longer available', 410);
            }

            // Return file info and requirements
            return $this->success([
                'file' => [
                    'name' => $shortLink->fileEntry->name,
                    'size' => $shortLink->fileEntry->file_size,
                    'mime_type' => $shortLink->fileEntry->mime,
                ],
                'requires_password' => $shortLink->requiresPassword(),
                'download_count' => $shortLink->download_count,
                'max_downloads' => $shortLink->max_downloads,
                'expires_at' => $shortLink->expires_at,
            ]);

        } catch (\Exception $e) {
            Log::error('Short link access error: ' . $e->getMessage());
            return $this->error('Link not found', 404);
        }
    }

    /**
     * Download file via short link
     */
    public function download(Request $request, $shortCode)
    {
        try {
            $shortLink = ShortLink::with('fileEntry')
                                 ->where('short_code', $shortCode)
                                 ->firstOrFail();

            if (!$shortLink->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This link has expired or is no longer available'
                ], 410);
            }

            // Check password if required
            if ($shortLink->requiresPassword()) {
                $password = $request->input('password');
                if (!$password || !$shortLink->verifyPassword($password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid password'
                    ], 401);
                }
            }

            // Log access
            $shortLink->logAccess([
                'password_provided' => $shortLink->requiresPassword(),
            ]);

            // Get file
            $fileEntry = $shortLink->fileEntry;
            $disk = Storage::disk($fileEntry->disk_prefix ?? 'uploads');
            
            if (!$disk->exists($fileEntry->path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found on storage'
                ], 404);
            }

            return $disk->download($fileEntry->path, $fileEntry->name);

        } catch (\Exception $e) {
            Log::error('Short link download error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Download failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get short link statistics
     */
    public function stats($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $shortLink = ShortLink::where('id', $id)
                                 ->where('user_id', $user->id)
                                 ->firstOrFail();

            $stats = $shortLink->getAccessStats();

            return $this->success($stats);

        } catch (\Exception $e) {
            Log::error('Short link stats error: ' . $e->getMessage());
            return $this->error('Failed to get statistics: ' . $e->getMessage(), 500);
        }
    }
}

