<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * LocationCascadingService - Địa chỉ cascading (Thành phố → Quận huyện → Xã phường)
 *
 * Sinh viên chọn địa chỉ theo cấp:
 * 1. Chọn tỉnh/thành phố
 * 2. Chọn quận/huyện (dựa trên tỉnh đã chọn)
 * 3. Chọn xã/phường (dựa trên quận đã chọn)
 * 4. Nhập số nhà/tên đường
 */
class LocationCascadingService
{
    /**
     * LẤY DANH SÁCH TỈNH/THÀNH PHỐ
     *
     * GET /api/location/provinces
     * Response: [{"code": "01", "name": "Thành phố Hà Nội"}, ...]
     */
    public function getProvinces()
    {
        return Cache::remember('locations_provinces', 86400, function () {
            return [
                ['code' => '01', 'name' => 'Thành phố Hà Nội'],
                ['code' => '02', 'name' => 'Thành phố Hồ Chí Minh'],
                ['code' => '03', 'name' => 'Thành phố Đà Nẵng'],
                ['code' => '04', 'name' => 'Tỉnh Hải Phòng'],
                ['code' => '05', 'name' => 'Tỉnh An Giang'],
                ['code' => '06', 'name' => 'Tỉnh Bà Rịa - Vũng Tàu'],
                ['code' => '07', 'name' => 'Tỉnh Bắc Giang'],
                ['code' => '08', 'name' => 'Tỉnh Bắc Kạn'],
                ['code' => '09', 'name' => 'Tỉnh Bạc Liêu'],
                ['code' => '10', 'name' => 'Tỉnh Bến Tre'],
                ['code' => '11', 'name' => 'Tỉnh Bình Định'],
                ['code' => '12', 'name' => 'Tỉnh Bình Dương'],
                ['code' => '13', 'name' => 'Tỉnh Bình Phước'],
                ['code' => '14', 'name' => 'Tỉnh Bình Thuận'],
                ['code' => '15', 'name' => 'Tỉnh Cà Mau'],
                ['code' => '16', 'name' => 'Tỉnh Cao Bằng'],
                ['code' => '17', 'name' => 'Tỉnh Đắk Lắk'],
                ['code' => '18', 'name' => 'Tỉnh Đắk Nông'],
                ['code' => '19', 'name' => 'Tỉnh Điện Biên'],
                ['code' => '20', 'name' => 'Tỉnh Đồng Nai'],
                ['code' => '21', 'name' => 'Tỉnh Đồng Tháp'],
                ['code' => '22', 'name' => 'Tỉnh Gia Lai'],
                ['code' => '23', 'name' => 'Tỉnh Hà Giang'],
                ['code' => '24', 'name' => 'Tỉnh Hà Nam'],
                ['code' => '25', 'name' => 'Tỉnh Hà Tĩnh'],
                ['code' => '26', 'name' => 'Tỉnh Hải Dương'],
                ['code' => '27', 'name' => 'Tỉnh Hậu Giang'],
                ['code' => '28', 'name' => 'Tỉnh Hưng Yên'],
                ['code' => '29', 'name' => 'Tỉnh Kiên Giang'],
                ['code' => '30', 'name' => 'Tỉnh Kon Tum'],
                ['code' => '31', 'name' => 'Tỉnh Lai Châu'],
                ['code' => '32', 'name' => 'Tỉnh Lâm Đồng'],
                ['code' => '33', 'name' => 'Tỉnh Lạng Sơn'],
                ['code' => '34', 'name' => 'Tỉnh Lào Cai'],
                ['code' => '35', 'name' => 'Tỉnh Long An'],
                ['code' => '36', 'name' => 'Tỉnh Nam Định'],
                ['code' => '37', 'name' => 'Tỉnh Nghệ An'],
                ['code' => '38', 'name' => 'Tỉnh Ninh Bình'],
                ['code' => '39', 'name' => 'Tỉnh Ninh Thuận'],
                ['code' => '40', 'name' => 'Tỉnh Phú Thọ'],
                ['code' => '41', 'name' => 'Tỉnh Phú Yên'],
                ['code' => '42', 'name' => 'Tỉnh Quảng Bình'],
                ['code' => '43', 'name' => 'Tỉnh Quảng Nam'],
                ['code' => '44', 'name' => 'Tỉnh Quảng Ngãi'],
                ['code' => '45', 'name' => 'Tỉnh Quảng Ninh'],
                ['code' => '46', 'name' => 'Tỉnh Quảng Trị'],
                ['code' => '47', 'name' => 'Tỉnh Sóc Trăng'],
                ['code' => '48', 'name' => 'Tỉnh Sơn La'],
                ['code' => '49', 'name' => 'Tỉnh Tây Ninh'],
                ['code' => '50', 'name' => 'Tỉnh Thái Bình'],
                ['code' => '51', 'name' => 'Tỉnh Thái Nguyên'],
                ['code' => '52', 'name' => 'Tỉnh Thanh Hóa'],
                ['code' => '53', 'name' => 'Tỉnh Thừa Thiên Huế'],
                ['code' => '54', 'name' => 'Tỉnh Tiền Giang'],
                ['code' => '55', 'name' => 'Tỉnh Trà Vinh'],
                ['code' => '56', 'name' => 'Tỉnh Tuyên Quang'],
                ['code' => '57', 'name' => 'Tỉnh Vĩnh Long'],
                ['code' => '58', 'name' => 'Tỉnh Vĩnh Phúc'],
                ['code' => '59', 'name' => 'Tỉnh Yên Bái'],
            ];
        });
    }

    /**
     * LẤY DANH SÁCH QUẬN/HUYỆN THEO TỈNH
     *
     * GET /api/location/districts?province_code=01
     * Response: [{"code": "001", "name": "Ba Đình"}, ...]
     */
    public function getDistricts(string $provinceCode)
    {
        $cacheKey = "locations_districts_{$provinceCode}";
        return Cache::remember($cacheKey, 86400, function () use ($provinceCode) {
            $districtsMap = [
                '03' => [  // Đà Nẵng
                    ['code' => '001', 'name' => 'Quận Hải Châu'],
                    ['code' => '002', 'name' => 'Quận Cẩm Lệ'],
                    ['code' => '003', 'name' => 'Quận Thanh Khê'],
                    ['code' => '004', 'name' => 'Quận Sơn Trà'],
                    ['code' => '005', 'name' => 'Quận Ngũ Hành Sơn'],
                    ['code' => '006', 'name' => 'Huyện Hoàng Sa'],
                ],
                '01' => [  // Hà Nội
                    ['code' => '001', 'name' => 'Ba Đình'],
                    ['code' => '002', 'name' => 'Hoàn Kiếm'],
                    ['code' => '003', 'name' => 'Tây Hồ'],
                    ['code' => '004', 'name' => 'Long Biên'],
                    ['code' => '005', 'name' => 'Đống Đa'],
                    ['code' => '006', 'name' => 'Hai Bà Trưng'],
                ],
                '02' => [  // Hồ Chí Minh
                    ['code' => '001', 'name' => 'Quận 1'],
                    ['code' => '002', 'name' => 'Quận 2'],
                    ['code' => '003', 'name' => 'Quận 3'],
                    ['code' => '004', 'name' => 'Quận 4'],
                    ['code' => '005', 'name' => 'Quận 5'],
                ],
            ];

            return $districtsMap[$provinceCode] ?? [];
        });
    }

    /**
     * LẤY DANH SÁCH XÃ/PHƯỜNG THEO QUẬN
     *
     * GET /api/location/wards?province_code=03&district_code=001
     * Response: [{"code": "001", "name": "Phường Thanh Bình"}, ...]
     */
    public function getWards(string $provinceCode, string $districtCode)
    {
        $cacheKey = "locations_wards_{$provinceCode}_{$districtCode}";
        return Cache::remember($cacheKey, 86400, function () use ($provinceCode, $districtCode) {
            $wardsMap = [
                '03_001' => [  // Đà Nẵng - Hải Châu
                    ['code' => '001', 'name' => 'Phường Thanh Bình'],
                    ['code' => '002', 'name' => 'Phường Bình Hiên'],
                    ['code' => '003', 'name' => 'Phường Hải Châu 1'],
                    ['code' => '004', 'name' => 'Phường Hải Châu 2'],
                    ['code' => '005', 'name' => 'Phường Bình Thuận'],
                ],
                '03_002' => [  // Đà Nẵng - Cẩm Lệ
                    ['code' => '001', 'name' => 'Phường Cẩm Lệ'],
                    ['code' => '002', 'name' => 'Phường Hòa Khương'],
                    ['code' => '003', 'name' => 'Phường Hòa Minh'],
                ],
            ];

            $key = "{$provinceCode}_{$districtCode}";
            return $wardsMap[$key] ?? [];
        });
    }

    /**
     * VALIDATE ĐỊA CHỈ CASCADING
     */
    public function validateLocation(array $data): array
    {
        $provinceCode = $data['province_code'] ?? null;
        $districtCode = $data['district_code'] ?? null;
        $wardCode = $data['ward_code'] ?? null;
        $address = $data['address'] ?? null;

        if (!$provinceCode || !$districtCode || !$wardCode || !$address) {
            return ['valid' => false, 'message' => 'Thiếu thông tin địa chỉ'];
        }

        // Verify province exists
        $provinces = $this->getProvinces();
        if (!in_array($provinceCode, array_column($provinces, 'code'))) {
            return ['valid' => false, 'message' => 'Tỉnh/thành phố không hợp lệ'];
        }

        // Verify district exists
        $districts = $this->getDistricts($provinceCode);
        if (!in_array($districtCode, array_column($districts, 'code'))) {
            return ['valid' => false, 'message' => 'Quận/huyện không hợp lệ'];
        }

        // Verify ward exists
        $wards = $this->getWards($provinceCode, $districtCode);
        if (!in_array($wardCode, array_column($wards, 'code'))) {
            return ['valid' => false, 'message' => 'Xã/phường không hợp lệ'];
        }

        return ['valid' => true];
    }

    /**
     * BUILD FULL ADDRESS STRING
     */
    public function buildFullAddress(array $data): string
    {
        $address = $data['address'] ?? '';

        $wardName = '';
        if (!empty($data['ward_code'])) {
            $wards = $this->getWards($data['province_code'] ?? '', $data['district_code'] ?? '');
            $ward = collect($wards)->firstWhere('code', $data['ward_code']);
            $wardName = $ward['name'] ?? '';
        }

        $districtName = '';
        if (!empty($data['district_code'])) {
            $districts = $this->getDistricts($data['province_code'] ?? '');
            $district = collect($districts)->firstWhere('code', $data['district_code']);
            $districtName = $district['name'] ?? '';
        }

        $provinceName = '';
        if (!empty($data['province_code'])) {
            $provinces = $this->getProvinces();
            $province = collect($provinces)->firstWhere('code', $data['province_code']);
            $provinceName = $province['name'] ?? '';
        }

        return trim("{$address}, {$wardName}, {$districtName}, {$provinceName}");
    }
}
