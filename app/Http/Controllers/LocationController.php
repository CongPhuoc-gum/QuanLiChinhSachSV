<?php

namespace App\Http\Controllers;

use App\Services\LocationCascadingService;
use Illuminate\Http\Request;

/**
 * LocationController - Location Cascading API
 *
 * Hỗ trợ chọn địa chỉ theo cấp: Thành phố → Quận huyện → Xã phường
 */
class LocationController extends Controller
{
    private LocationCascadingService $locationService;

    public function __construct(LocationCascadingService $locationService)
    {
        $this->locationService = $locationService;
    }

    /**
     * GET /api/location/provinces
     * Lấy danh sách tỉnh/thành phố
     *
     * Response: [{"code": "01", "name": "Thành phố Hà Nội"}, ...]
     */
    public function getProvinces()
    {
        try {
            $provinces = $this->locationService->getProvinces();
            return response()->json([
                'success' => true,
                'data' => $provinces,
                'count' => count($provinces),
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in LocationController@getProvinces', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi lấy danh sách tỉnh/thành phố',
            ], 500);
        }
    }

    /**
     * GET /api/location/districts?province_code=03
     * Lấy danh sách quận/huyện theo tỉnh
     *
     * Query: province_code (bắt buộc)
     * Response: [{"code": "001", "name": "Quận Hải Châu"}, ...]
     */
    public function getDistricts(Request $request)
    {
        try {
            $request->validate([
                'province_code' => 'required|string|size:2',
            ]);

            $provinceCode = $request->input('province_code');
            $districts = $this->locationService->getDistricts($provinceCode);

            if (empty($districts)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tỉnh/thành phố không hợp lệ',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $districts,
                'count' => count($districts),
                'province_code' => $provinceCode,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error in LocationController@getDistricts', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi lấy danh sách quận/huyện',
            ], 500);
        }
    }

    /**
     * GET /api/location/wards?province_code=03&district_code=001
     * Lấy danh sách xã/phường theo quận
     *
     * Query: province_code, district_code (bắt buộc)
     * Response: [{"code": "001", "name": "Phường Thanh Bình"}, ...]
     */
    public function getWards(Request $request)
    {
        try {
            $request->validate([
                'province_code' => 'required|string|size:2',
                'district_code' => 'required|string|size:3',
            ]);

            $provinceCode = $request->input('province_code');
            $districtCode = $request->input('district_code');
            $wards = $this->locationService->getWards($provinceCode, $districtCode);

            if (empty($wards)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quận/huyện không hợp lệ',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $wards,
                'count' => count($wards),
                'province_code' => $provinceCode,
                'district_code' => $districtCode,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error in LocationController@getWards', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi lấy danh sách xã/phường',
            ], 500);
        }
    }
}
