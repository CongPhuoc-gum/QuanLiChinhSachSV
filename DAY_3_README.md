# 📋 DAY 3: GEMINI OCR & AUTO-COMPARISON - SETUP & API GUIDE

**Date**: June 9, 2026  
**Branch**: `feature/day-3-gemini-ocr`  
**Status**: ✅ COMPLETE & READY FOR TESTING

---

## 📌 EXECUTIVE SUMMARY

Day 3 implements the **AI-powered OCR & Auto-Comparison** workflow for QUANLICS:
- **Gemini 2.0 Flash Vision API** reads minh chứng images from Cloudinary
- **ComparisonService** compares extracted data with student-submitted form data
- **Auto-status update**: Hồ sơ auto-moves to next workflow stage based on OCR accuracy
- **Full transaction safety** with comprehensive error handling & retry logic

### Key Metrics:
- ✅ **1 new endpoint**: POST `/api/ho-so/{maHoSo}/process-ocr`
- ✅ **2 new services**: `GeminiService@ocrDocument()`, `ComparisonService`
- ✅ **1 new model**: `PhanTichAIHoSo` (with scopes & relations)
- ✅ **1 new migration**: `PHAN_TICH_AI_HO_SO` table
- ✅ **3 import updates**: HoSoController + route registration

---

## 🔧 INSTALLATION & SETUP

### Prerequisites
- ✅ Day 1 & Day 2 completed
- ✅ Gemini API key configured in `.env` (GEMINI_API_KEY, GEMINI_VISION_MODEL)
- ✅ Cloudinary configured (Day 2)
- ✅ MySQL 8.x running

### Step 1: Run Migration

```bash
php artisan migrate
```

This creates the `PHAN_TICH_AI_HO_SO` table with all necessary fields:
- `MaPhanTich` (PK)
- `MaHoSo` (FK)
- `LoaiTaiLieuOCR` (document type)
- `URLAnh` (Cloudinary image URL)
- `KetQuaDoiChieu` (JSON comparison results)
- `TyLeKhop` (0.0-1.0 match rate)
- `DoTinCayOCR` (Gemini confidence)
- `CanBaoLech` (array of discrepancies)
- `TrangThaiXuLy` (APPROVED, WARNING, NEED_REVIEW)

### Step 2: Verify Configuration

Confirm in `.env`:
```
GEMINI_API_KEY=<your-key>
GEMINI_VISION_MODEL=gemini-2.0-flash-vision
CLOUDINARY_CLOUD_NAME=<name>
CLOUDINARY_API_KEY=<key>
CLOUDINARY_API_SECRET=<secret>
```

### Step 3: Test with Postman

See **POSTMAN_DAY3_COLLECTION.json** for complete test scenarios.

---

## 📡 API ENDPOINT DOCUMENTATION

### POST `/api/ho-so/{maHoSo}/process-ocr`

**Purpose**: Trigger OCR processing for all minh chứng of a hồ sơ

**Method**: `POST`  
**Auth**: Required (Bearer token)  
**Content-Type**: `application/json`

#### Request

```json
{
  "process_all": false
}
```

**Parameters**:
- `maHoSo` (URL param, required): Hồ sơ ID
- `process_all` (optional, default: false): Process all minh chứng or only unprocessed

#### Response (Success - 200)

```json
{
  "success": true,
  "data": {
    "ma_ho_so": 1,
    "analysis_count": 2,
    "analysis_results": [
      {
        "ma_minh_chung": 10,
        "ma_phan_tich": 15,
        "ty_le_khop": 0.98,
        "trang_thai": "APPROVED",
        "khuyến_nghị": "✅ Tự động duyệt hồ sơ - Dữ liệu trùng khớp hoàn toàn (98%)"
      },
      {
        "ma_minh_chung": 11,
        "ma_phan_tich": 16,
        "ty_le_khop": 0.85,
        "trang_thai": "WARNING",
        "khuyến_nghị": "⚠️ Xét duyệt có điều kiện - Kiểm tra lại các trường: ho_ten"
      }
    ]
  },
  "message": "Xử lý OCR hoàn thành thành công"
}
```

#### Response (Error - 400)

```json
{
  "success": false,
  "message": "Hồ sơ không có minh chứng để xử lý OCR"
}
```

#### Response (Error - 500)

```json
{
  "success": false,
  "message": "Lỗi xử lý OCR: <error-details>"
}
```

---

## 🏗️ ARCHITECTURE & DATA FLOW

### Request Flow Diagram

```
POST /api/ho-so/{id}/process-ocr
    ↓
HoSoController@processOcr()
    ├─ Get all MinhChungFile for hồ sơ
    ├─ Loop through each minh chứng:
    │   ├─ GeminiService@ocrDocument(URL)
    │   │   ├─ Download image from Cloudinary
    │   │   ├─ Convert to base64
    │   │   ├─ Call Gemini Vision API (with retry)
    │   │   └─ Return JSON extracted data
    │   ├─ ComparisonService@compareOcrWithForm(ocrData, formData)
    │   │   ├─ Normalize Vietnamese text (remove accents)
    │   │   ├─ Calculate string similarity (Levenshtein)
    │   │   ├─ Weight scores: ho_ten (35%), id (35%), others (30%)
    │   │   ├─ Determine status: APPROVED (≥95%), WARNING (80-95%), NEED_REVIEW (<80%)
    │   │   └─ Return comparison result with recommendation
    │   └─ Save to PHAN_TICH_AI_HO_SO table
    ├─ Auto-update HoSo.MaTrangThai:
    │   ├─ All APPROVED → Status 4 (Chờ Trưởng phòng)
    │   ├─ Has errors → Status 3 (Đang bổ sung)
    │   └─ Has warnings → Status 2 (Chờ thẩm định)
    └─ Return results

Response: { success, analysis_results, message }
```

### Data Models

#### PhanTichAIHoSo (PHAN_TICH_AI_HO_SO table)

```php
class PhanTichAIHoSo extends Model {
    // Fields
    MaPhanTich (PK)
    MaHoSo (FK) → HoSo
    LoaiTaiLieuOCR (cccd|ho_khau|ho_ngheo|khai_sinh)
    URLAnh (Cloudinary URL)
    KetQuaDoiChieu (JSON) {
        overall_match_rate: 0-1,
        status: APPROVED|WARNING|NEED_REVIEW,
        field_comparisons: [{ field, original, ocr, match, weight }],
        discrepancies: [{ field, severity, message }],
        recommendation: "..."
    }
    TyLeKhop (0.0-1.0)
    DoTinCayOCR (0.0-1.0)
    CanBaoLech (JSON array)
    TrangThaiXuLy (PENDING|APPROVED|WARNING|NEED_REVIEW)
    ThoiGianPhanTich (timestamp)
    GhiChuAdmin (text)
    
    // Scopes
    hopLe() → TyLeKhop >= 0.95
    canhBao() → TyLeKhop between 0.8-0.95
    canThamDinh() → TyLeKhop < 0.8
}
```

---

## 📊 COMPARISON ALGORITHM

### String Similarity Calculation

1. **Normalization**:
   - Trim whitespace
   - Convert to lowercase
   - Remove Vietnamese accents (á→a, é→e, etc.)
   - Remove extra spaces

2. **Comparison Methods**:
   - **Exact Match**: Return 1.0
   - **Levenshtein Distance**: `1 - (distance / max_length)`
   - **Date Matching**: Special handling for DD/MM/YYYY format

3. **Weighted Scoring**:
   ```
   overall_match = (ho_ten_match × 0.35) + (id_match × 0.35) + (others_match × 0.30)
   ```

4. **Status Determination**:
   ```
   if overall_match >= 0.95:
       status = APPROVED  (tự động duyệt)
   elif overall_match >= 0.80:
       status = WARNING   (cảnh báo - cần kiểm tra)
   else:
       status = NEED_REVIEW  (cần thẩm định lại)
   ```

### Example Comparison Result

```json
{
  "success": true,
  "overall_match_rate": 0.98,
  "status": "APPROVED",
  "field_comparisons": [
    {
      "field": "ho_ten",
      "original": "Nguyễn Văn A",
      "ocr": "Nguyễn Văn A",
      "match": 1.0,
      "weight": 0.35,
      "note": "Trùng khớp"
    },
    {
      "field": "id",
      "original": "20210001",
      "ocr": "20210001",
      "match": 1.0,
      "weight": 0.35,
      "note": "Trùng khớp"
    },
    {
      "field": "date",
      "original": "15/01/2005",
      "ocr": "15/01/2005",
      "match": 1.0,
      "weight": 0.30,
      "note": "Trùng khớp"
    }
  ],
  "discrepancies": [],
  "recommendation": "✅ Tự động duyệt hồ sơ - Dữ liệu trùng khớp hoàn toàn (98%)"
}
```

---

## 🎯 GEMINI VISION OCR PROMPTS

### CCCD Prompt

```
Đọc ảnh thẻ Căn Cước Công Dân này. Trích xuất CHÍNH XÁC:
- Họ và tên: (lấy chữ thường, chuẩn hóa)
- Số CCCD: (12 chữ số)
- Ngày sinh: (DD/MM/YYYY)
- Giới tính: (Nam/Nữ/Khác)

TRẢ VỀ DỪNG MỘT JSON CẤU TRÚC CỐ ĐỊNH:
{
  "ho_ten": "Nguyễn Văn A",
  "id_number": "001234567890",
  "ngay_sinh": "15/01/2005",
  "gioi_tinh": "Nam",
  "confidence": 0.95
}

CHỈ TRẢ VỀ JSON, KHÔNG TRẢ LỜI KHÁC.
```

### Hộ Khẩu Prompt

```
Đọc ảnh Sổ Hộ Khẩu hoặc Giấy Đăng Ký Thường Trú. Trích xuất CHÍNH XÁC:
- Chủ hộ: (tên chủ hộ)
- Số hộ khẩu: (mã số)
- Địa chỉ thường trú: (đầy đủ)

{
  "chu_ho": "Nguyễn Văn A",
  "so_ho_khau": "123456789",
  "dia_chi": "Số 10 Đường Trần Hưng Đạo, Phường 1, Quận 1, TP. Hồ Chí Minh",
  "confidence": 0.92
}

CHỈ TRẢ VỀ JSON, KHÔNG TRẢ LỜI KHÁC.
```

### Hộ Nghèo Prompt

```
Đọc ảnh Giấy Chứng Nhận Hộ Nghèo. Trích xuất CHÍNH XÁC:
- Chủ hộ: (tên chủ hộ)
- Mã hộ nghèo: (mã số cấp)
- Ngày cấp: (ngày)

{
  "chu_ho": "Nguyễn Văn A",
  "ma_ho_ngheo": "123456789",
  "ngay_cap": "15/01/2024",
  "confidence": 0.95
}

CHỈ TRẢ VỀ JSON, KHÔNG TRẢ LỜI KHÁC.
```

---

## 🔄 WORKFLOW TRANSITIONS

### HoSo Status Updates

```
Before OCR Processing:
├─ Status 2: Chờ thẩm định
└─ Has MinhChungFile (uploaded in Day 2)

↓ (POST /api/ho-so/{id}/process-ocr)

After OCR Processing:
├─ ALL APPROVED (all OCR ≥ 95%)
│   └─ Status → 4 (Chờ Trưởng phòng duyệt)
│   └─ GhiChu: "Tự động duyệt từ OCR AI - Tất cả minh chứng hợp lệ"
├─ HAS ERRORS (any OCR < 80%)
│   └─ Status → 3 (Đang bổ sung)
│   └─ GhiChu: "Phát hiện sai lệch trong OCR - Yêu cầu bổ sung minh chứng"
└─ HAS WARNINGS (some 80-95%)
    └─ Status → 2 (Chờ thẩm định) [No change]
    └─ Admin reviews detailed results
```

---

## 🧪 TESTING WORKFLOW

### Test Scenario 1: Perfect Match (APPROVED)

**Step 1**: Submit hồ sơ with student data
```json
{
  "ma_loai_cs": 1,
  "ma_dot": 1,
  "form_data": "{\"ho_ten\":\"Nguyễn Văn A\",\"ma_so_sv\":\"20210001\",\"dien_thoai\":\"0901234567\",\"trang_thai_ho_gia_dinh\":\"hộ_nghèo\"}",
  "minh_chungs": [CCCD_image.jpg]
}
```

**Step 2**: Upload returns hồ sơ with Status 2 (Chờ thẩm định)

**Step 3**: Trigger OCR
```bash
POST /api/ho-so/1/process-ocr
```

**Expected Result**:
- Gemini extracts: `{"ho_ten":"Nguyễn Văn A","id_number":"20210001",...}`
- ComparisonService: `overall_match_rate: 1.0, status: APPROVED`
- HoSo auto-updated: Status → 4 (Chờ Trưởng phòng)

### Test Scenario 2: Minor Discrepancy (WARNING)

**Same as Scenario 1**, but OCR extracts:
- `{"ho_ten":"Nguyễn Văn Á","id_number":"20210001",...}`

**Expected Result**:
- ComparisonService: `overall_match_rate: 0.85, status: WARNING`
- HoSo stays: Status 2 (Chờ thẩm định)
- Admin reviews: Sees `CanBaoLech: [{field: "ho_ten", severity: "warning"}]`

### Test Scenario 3: Major Discrepancy (NEED_REVIEW)

**Same as Scenario 1**, but OCR extracts different data:
- `{"ho_ten":"Trần Văn B","id_number":"20210002",...}`

**Expected Result**:
- ComparisonService: `overall_match_rate: 0.1, status: NEED_REVIEW`
- HoSo auto-updated: Status → 3 (Đang bổ sung)
- Student notified to resubmit correct documents

---

## 📝 ERROR HANDLING & RETRY LOGIC

### Gemini Vision API Retry

```php
callGeminiVisionWithRetry($imageUrl, $prompt)
├─ Attempt 1: Initial call
├─ Attempt 2: Retry after 2s backoff
├─ Attempt 3: Retry after 2s backoff
└─ Max 3 attempts with exponential backoff

Handles:
├─ Network timeouts (30s timeout)
├─ Rate limiting (429)
├─ Server errors (5xx)
└─ Image loading failures
```

### Transaction Safety

```php
DB::beginTransaction()
├─ Delete old analyses
├─ Create new PhanTichAIHoSo records
├─ Update HoSo status
├─ Log all activities
└─ DB::commit() or DB::rollBack()
```

### Logging

All operations logged to `storage/logs/laravel.log`:
- OCR process start/completion
- Gemini API calls (request body, response status)
- Comparison results (match rates, discrepancies)
- Status updates (old status → new status)
- Errors & retry attempts

---

## 🚀 PRODUCTION CHECKLIST

- [ ] Database migration ran successfully: `php artisan migrate`
- [ ] `.env` configured with Gemini Vision API key
- [ ] Cloudinary URLs accessible from server
- [ ] Log files writable: `storage/logs/`
- [ ] Error handling tested (network timeout, API errors)
- [ ] Auto-status updates verified
- [ ] Postman tests pass
- [ ] Code diagnostics pass: `php artisan tinker`
- [ ] Database indexes created for fast queries
- [ ] Backup tested before production

---

## 📚 RELATED FILES

- `app/Services/GeminiService.php` - OCR Vision API integration
- `app/Services/ComparisonService.php` - String comparison & scoring
- `app/Http/Controllers/HoSoController.php` - processOcr() endpoint
- `app/Models/PhanTichAIHoSo.php` - Analysis results model
- `database/migrations/2026_06_09_100000_create_phan_tich_ai_ho_so_table.php` - Table creation
- `routes/api.php` - OCR route registration
- `POSTMAN_DAY3_COLLECTION.json` - API test requests
- `.env` - API configuration

---

## 🔗 LINKS & REFERENCES

- **Gemini Vision API Docs**: https://ai.google.dev/docs/vision
- **Laravel Transactions**: https://laravel.com/docs/database#transactions
- **Levenshtein Algorithm**: https://en.wikipedia.org/wiki/Levenshtein_distance

---

**Last Updated**: June 9, 2026  
**Version**: 1.0 (Day 3 Complete)
