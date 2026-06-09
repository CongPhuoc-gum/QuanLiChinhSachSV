<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * CloudinaryService
 *
 * Xử lý upload file (ảnh minh chứng) lên Cloudinary
 * Trả về secure_url để lưu vào database
 */
class CloudinaryService
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;
    private string $uploadPreset;
    private string $uploadUrl;

    public function __construct()
    {
        $this->cloudName = env('CLOUDINARY_CLOUD_NAME');
        $this->apiKey = env('CLOUDINARY_API_KEY');
        $this->apiSecret = env('CLOUDINARY_API_SECRET');
        $this->uploadPreset = env('CLOUDINARY_UPLOAD_PRESET', 'quanlics_default');
        $this->uploadUrl = "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload";

        if (!$this->cloudName || !$this->apiKey || !$this->apiSecret) {
            throw new Exception('Cloudinary credentials not configured in .env');
        }
    }

    /**
     * Upload ảnh minh chứng lên Cloudinary
     *
     * @param \Illuminate\Http\UploadedFile $file File ảnh từ request
     * @param string $folder Folder trong Cloudinary (vd: "quanlics/ho_so/2024")
     * @param string $publicId Tên file gợi ý (vd: "minh_chung_cccd_123")
     * @return array Kết quả upload: ['success' => bool, 'url' => string, 'public_id' => string, 'error' => string]
     *
     * @throws Exception
     */
    public function uploadMinhChung(
        $file,
        string $folder = 'quanlics/minh_chung',
        ?string $publicId = null
    ): array {
        try {
            // Validate file
            if (!$file || !$file->isValid()) {
                return [
                    'success' => false,
                    'error' => 'File không hợp lệ hoặc không được upload'
                ];
            }

            // Validate MIME type
            $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                return [
                    'success' => false,
                    'error' => 'Chỉ chấp nhận file ảnh định dạng JPEG, PNG (MIME: ' . $file->getMimeType() . ')'
                ];
            }

            // Validate file size (< 5MB)
            $maxSize = 5 * 1024 * 1024;  // 5MB
            if ($file->getSize() > $maxSize) {
                return [
                    'success' => false,
                    'error' => 'Kích thước file vượt quá 5MB'
                ];
            }

            // Tạo publicId nếu không được cung cấp
            if (!$publicId) {
                $publicId = 'minh_chung_' . uniqid() . '_' . time();
            }

            // Gửi request upload lên Cloudinary
            $response = Http::timeout(30)
                ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
                ->post($this->uploadUrl, [
                    'public_id' => $publicId,
                    'folder' => $folder,
                    'upload_preset' => $this->uploadPreset,
                    'api_key' => $this->apiKey,
                    'timestamp' => time(),
                ]);

            if ($response->failed()) {
                $errorMsg = $response->json('error.message') ?? 'Upload to Cloudinary failed';
                Log::error('CloudinaryService::uploadMinhChung - HTTP Error', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);

                return [
                    'success' => false,
                    'error' => $errorMsg
                ];
            }

            $data = $response->json();

            // Kiểm tra kết quả từ Cloudinary
            if (!isset($data['secure_url'])) {
                Log::error('CloudinaryService::uploadMinhChung - Missing secure_url', [
                    'response' => $data
                ]);

                return [
                    'success' => false,
                    'error' => 'Cloudinary upload thành công nhưng không trả về URL'
                ];
            }

            Log::info('CloudinaryService::uploadMinhChung - Success', [
                'public_id' => $data['public_id'] ?? null,
                'url' => $data['secure_url']
            ]);

            return [
                'success' => true,
                'url' => $data['secure_url'],
                'public_id' => $data['public_id'] ?? $publicId,
                'width' => $data['width'] ?? null,
                'height' => $data['height'] ?? null,
                'format' => $data['format'] ?? null,
                'size' => $data['bytes'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error('CloudinaryService::uploadMinhChung - Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Lỗi upload file: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete file từ Cloudinary (cleanup)
     *
     * @param string $publicId Public ID của file trong Cloudinary
     * @return bool True nếu xóa thành công
     */
    public function deleteFile(string $publicId): bool
    {
        try {
            $timestamp = time();
            $signature = hash('sha1', "public_id={$publicId}&timestamp={$timestamp}" . $this->apiSecret);

            $response = Http::timeout(30)
                ->post("https://api.cloudinary.com/v1_1/{$this->cloudName}/image/destroy", [
                    'public_id' => $publicId,
                    'api_key' => $this->apiKey,
                    'timestamp' => $timestamp,
                    'signature' => $signature,
                ]);

            if ($response->successful() && $response->json('result') === 'ok') {
                Log::info('CloudinaryService::deleteFile - Success', ['public_id' => $publicId]);
                return true;
            }

            Log::warning('CloudinaryService::deleteFile - Failed', [
                'public_id' => $publicId,
                'response' => $response->json()
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('CloudinaryService::deleteFile - Exception', [
                'public_id' => $publicId,
                'message' => $e->getMessage()
            ]);

            return false;
        }
    }
}
