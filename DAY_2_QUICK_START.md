# 🚀 Day 2 Quick Start (5 Minutes)

**Goal**: Get API running and test first upload in 5 minutes

---

## ⚡ 5-Minute Setup

### 1. Configure Cloudinary (2 min)

```bash
# Edit .env
CLOUDINARY_CLOUD_NAME=your_cloud_name
CLOUDINARY_API_KEY=your_api_key
CLOUDINARY_API_SECRET=your_api_secret
CLOUDINARY_UPLOAD_PRESET=quanlics_default
```

**Get credentials from**: https://cloudinary.com/console (Dashboard)

### 2. Run Migration (1 min)

```bash
php artisan migrate
```

Expected output:
```
Running migrations...
2026_06_08_085250_add_du_lieu_form_to_ho_so_table ......... 92.33ms DONE
2026_06_08_134219_create_ai_chatbot_tables ............... 7.17ms DONE
Migration completed successfully
```

### 3. Get Auth Token (1 min)

**Via Postman**:
1. POST http://localhost:8000/api/login
2. Body (JSON):
```json
{
  "email": "sv001@example.com",
  "password": "password"
}
```
3. Copy `access_token` from response

**Via cURL**:
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"sv001@example.com","password":"password"}'
```

### 4. Test Upload Endpoint (1 min)

**Option A: Via Postman** (Easiest)
1. Import `POSTMAN_DAY2_COLLECTION.json`
2. Set `{{access_token}}` from step 3
3. Run: **1. POST - Nộp Hồ Sơ Mới BM.01**
4. Select 2 image files
5. Send

**Option B: Via cURL**
```bash
curl -X POST http://localhost:8000/api/ho-so/store \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "ma_loai_cs=1" \
  -F "ma_dot=1" \
  -F "form_data={\"ho_ten\":\"Nguyen A\",\"ma_so_sv\":\"20210001\",\"dien_thoai\":\"0901234567\",\"trang_thai_ho_gia_dinh\":\"hộ_nghèo\"}" \
  -F "minh_chungs=@/path/to/image1.jpg" \
  -F "minh_chungs=@/path/to/image2.png"
```

---

## ✅ Expected Success Response

```json
{
  "success": true,
  "data": {
    "ma_ho_so": 1,
    "ma_loai_cs": 1,
    "ma_trang_thai": 2,
    "du_lieu_form": {
      "ho_ten": "Nguyen A",
      "ma_so_sv": "20210001",
      "dien_thoai": "0901234567",
      "trang_thai_ho_gia_dinh": "hộ_nghèo"
    },
    "minh_chungs": [
      {
        "ma_minh_chung": 1,
        "ten_file": "image1.jpg",
        "url": "https://res.cloudinary.com/..../mc_1_0_1718001234.jpg",
        "kich_thuoc": 2048000
      },
      {
        "ma_minh_chung": 2,
        "ten_file": "image2.png",
        "url": "https://res.cloudinary.com/..../mc_1_1_1718001235.png",
        "kich_thuoc": 1024000
      }
    ]
  },
  "message": "Nộp hồ sơ thành công. Vui lòng chờ xét duyệt.",
  "warning": null,
  "failed_uploads": []
}
```

---

## 🔍 Verify in Database

```sql
-- Check HoSo created
SELECT MaHoSo, MaNguoiDung, MaLoaiCS, du_lieu_form FROM HO_SO WHERE MaHoSo = 1;

-- Check MinhChung uploaded
SELECT MaMinhChung, DuongDanFile FROM MINH_CHUNG_FILE WHERE MaHoSo = 1;
```

Expected output:
```
MaHoSo: 1
du_lieu_form: {"ho_ten":"Nguyen A",...}

MaMinhChung: 1
DuongDanFile: https://res.cloudinary.com/.../mc_1_0_1718001234.jpg
```

---

## 🐛 Troubleshooting (If It Fails)

### Error: "Cloudinary credentials not configured"
```
Solution: Restart Laravel server after updating .env
Command: Ctrl+C, then php artisan serve
```

### Error: "Upload failed: Invalid upload preset"
```
Solution: Create preset in Cloudinary Dashboard
1. Go to Settings → Upload → Add upload preset
2. Name: quanlics_default
3. Unsigned: Enable
4. Folder: quanlics/minh_chung
5. Save
```

### Error: "File rejected - invalid MIME type"
```
Solution: Use JPEG or PNG only
Supported: *.jpg, *.jpeg, *.png
Rejected: *.gif, *.webp, *.pdf, etc.
```

### Error: "File too large"
```
Solution: File must be < 5MB
Check: Use `ls -lh file.jpg` or Windows Properties
If too large: Compress or resize image
```

### Error: "Unauthorized - invalid token"
```
Solution: Get new token from /api/login
Check: Token in Authorization header: Bearer {token}
```

---

## ✨ Next Steps

After successful upload:

1. **View in Cloudinary**: https://cloudinary.com/console → Media Library
2. **Check Database**: Run SQL verification query
3. **Try Day 2 Endpoints**:
   - GET /api/ho-so (list your submissions)
   - GET /api/ho-so/1 (details with minh chungs)
   - POST /api/ho-so/1/minh-chung-them (add more files)
   - DELETE /api/ho-so/1/minh-chung/2 (delete file)

4. **Ready for Day 3**: Gemini OCR processing

---

## 📝 Checklist Before Day 3

- [ ] All Day 2 endpoints working
- [ ] Cloudinary credentials configured
- [ ] Postman collection tested
- [ ] At least 1 successful submission
- [ ] Images visible in Cloudinary Dashboard
- [ ] Database records visible in MySQL
- [ ] Error handling working (test invalid files)

---

## 🎯 Day 2 Success = 

✅ JSON schema for BM.01/BM.02 validated  
✅ Multipart upload working  
✅ Cloudinary integration tested  
✅ Database schema updated  
✅ 6 API endpoints functional  
✅ Error handling in place  

**You're ready for Day 3!**

---

**Time Estimate**: 5 minutes setup + testing  
**Status**: Production-ready  
**Next**: Day 3 - Gemini OCR & Auto-Comparison
