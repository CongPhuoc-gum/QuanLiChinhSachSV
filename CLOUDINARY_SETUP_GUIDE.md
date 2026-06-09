# 🌥️ Cloudinary Setup Guide for QUANLICS Day 2

**Purpose**: Configure Cloudinary cloud storage for minh chứng (evidence) upload

---

## Step 1: Create Cloudinary Account

1. Go to [cloudinary.com/users/register/free](https://cloudinary.com/users/register/free)
2. Sign up with email (recommended: use organization email)
3. Verify email
4. Create free account (5GB storage + 25 monthly requests - sufficient for testing)

---

## Step 2: Get API Credentials

After login, navigate to **Dashboard** (https://cloudinary.com/console):

### Find Your Credentials:
```
Cloud Name: xxxxxxx
API Key: 1234567890
API Secret: abcdefghijklmnop
```

**Location**: Top-right corner → Account settings → API Keys tab

---

## Step 3: Update .env File

Edit `.env` and fill in your credentials:

```env
CLOUDINARY_CLOUD_NAME=your_cloud_name_here
CLOUDINARY_API_KEY=your_api_key_here
CLOUDINARY_API_SECRET=your_api_secret_here
CLOUDINARY_UPLOAD_PRESET=quanlics_default
```

**Example**:
```env
CLOUDINARY_CLOUD_NAME=myschool-cloud
CLOUDINARY_API_KEY=847362918465
CLOUDINARY_API_SECRET=9xK_vP2qL-m8nR5tY3uW6sA4bC
CLOUDINARY_UPLOAD_PRESET=quanlics_default
```

---

## Step 4: Create Upload Preset

Upload presets allow unsigned uploads (frontend can upload directly).

### In Cloudinary Dashboard:

1. **Settings** → **Upload**
2. Click **Add upload preset**
3. Fill form:
   - **Preset name**: `quanlics_default`
   - **Unsigned**: Enable (checkbox)
   - **Folder**: `quanlics/minh_chung`
   - **Resource type**: Image
   - **Public ID**: `mc_${folder}_${timestamp}`

4. **Save preset**

### Preset Settings Recommended:
- ✅ Auto-tag: `minh_chung, quanlics`
- ✅ Quality: 85 (balance quality vs size)
- ✅ Format: Auto (preserve original)
- ✅ Transformation: Resize max 2000x2000

---

## Step 5: Test with Postman

### Using Postman Collection

1. Import `POSTMAN_DAY2_COLLECTION.json`
2. Set variables:
   - `access_token`: Get from `/api/login`
   - Base URL: `http://localhost:8000`

3. **Test Request**: POST /api/ho-so/store
   - Body → form-data
   - Add files to `minh_chungs`
   - Fill required fields

4. **Expected Response** (201 Created):
```json
{
  "success": true,
  "data": {
    "ma_ho_so": 1,
    "minh_chungs": [
      {
        "url": "https://res.cloudinary.com/..../upload/v.../mc_1_0_1718001234.jpg"
      }
    ]
  }
}
```

---

## Step 6: Verify Upload in Cloudinary Dashboard

1. Go to **Media Library**
2. Look for images in folder: `quanlics/minh_chung`
3. Click image → copy **Secure URL**
4. Verify URL is accessible (paste in browser)

---

## File Size & Format Reference

### Supported Formats
- ✅ JPEG (.jpg, .jpeg)
- ✅ PNG (.png)
- ❌ GIF, WebP, BMP, TIFF (rejected by backend)

### Size Limits
- Per file: **< 5MB**
- Max per upload: No limit (practical: 10 files)
- Free tier limit: 5GB total storage

### Recommended Settings
```
Image Quality: 85% (good balance)
Max Width: 2000px
Max Height: 2000px
Auto-compress: Enabled
```

---

## Troubleshooting

### "Cloudinary credentials not configured"
→ Check .env variables are set correctly (no typos)
→ Restart Laravel server after updating .env

### "Upload failed: Invalid upload preset"
→ Verify preset name matches `CLOUDINARY_UPLOAD_PRESET`
→ Ensure preset is set to **Unsigned**
→ Check preset is **Active** (not disabled)

### "File rejected - invalid MIME type"
→ Only JPEG/PNG accepted
→ Convert GIF/WebP to PNG: `convert image.gif image.png`
→ Check file extension matches content

### "File too large"
→ Max 5MB per file
→ Compress with: `convert image.jpg -quality 85 image-compressed.jpg`

### "Secure URL not returning"
→ Check Cloudinary Dashboard → Settings → Security
→ Ensure URLs are **HTTPS** (automatic)
→ Verify delivery is working

---

## Security Best Practices

### ✅ Do's
- Store API credentials in `.env` (never in code)
- Use unsigned upload presets for frontend
- Enable signed uploads for backend-only operations
- Keep API Secret confidential
- Use HTTPS for all URLs

### ❌ Don'ts
- Don't commit `.env` with credentials to git
- Don't expose API Secret in frontend code
- Don't allow arbitrary folder paths
- Don't disable HTTPS requirement

---

## API Usage Monitoring

### Track Usage in Dashboard:
1. **Account** → **Media Storage**
2. View current usage vs limits
3. Monitor monthly transformations

### Free Tier Limits:
- 5GB storage
- 25k monthly transformations
- Sufficient for small-scale testing

---

## Integration Flow Diagram

```
┌─────────────────────────────────────┐
│ React Native / Web Client           │
└──────────────┬──────────────────────┘
               │ upload multipart/form
               ▼
┌─────────────────────────────────────┐
│ Laravel HoSoController@store         │
│ - Validate file (MIME, size)        │
│ - Call CloudinaryService            │
└──────────────┬──────────────────────┘
               │ POST HTTP (gzip)
               ▼
┌─────────────────────────────────────┐
│ Cloudinary API                      │
│ https://api.cloudinary.com/...      │
│ - Store in quanlics/minh_chung/...  │
│ - Return secure_url                 │
└──────────────┬──────────────────────┘
               │ secure_url response
               ▼
┌─────────────────────────────────────┐
│ Laravel (Backend)                   │
│ - Save URL to MINH_CHUNG_FILE       │
│ - Trigger OCR marker (Day 3)        │
│ - Return response to client         │
└──────────────┬──────────────────────┘
               │ JSON response
               ▼
┌─────────────────────────────────────┐
│ Client receives:                    │
│ - HoSo ID, status                   │
│ - Minh Chung URLs (Cloudinary)      │
└─────────────────────────────────────┘
```

---

## MySQL Storage

Images are **NOT stored in MySQL**:
- ✅ Only URL saved: `https://res.cloudinary.com/...jpg`
- ✅ Only metadata: filename, size, timestamp
- ✅ Public ID for future deletion

Example `MINH_CHUNG_FILE` row:
```sql
MaMinhChung: 5
MaHoSo: 1
TenFile: "cccd.jpg"
DuongDanFile: "https://res.cloudinary.com/.../mc_1_0_1718001234.jpg"
PublicIdCloudinary: "quanlics/minh_chung/2024/06/09/1/mc_1_0_1718001234"
KichThuoc: 2048000
KieuFile: "image/jpeg"
ThoiGianUpload: "2026-06-09 14:30:00"
```

---

## Performance Optimization

### Image Delivery:
- Cloudinary auto-optimizes (CDN)
- Cached globally
- Fast loading from any location

### Transformation Examples (for future use):
```
Original: https://res.cloudinary.com/cloud/image/upload/v1/file.jpg
Thumbnail: https://res.cloudinary.com/cloud/image/upload/w_200,h_200,c_crop/v1/file.jpg
Quality: https://res.cloudinary.com/cloud/image/upload/q_auto:eco/v1/file.jpg
Responsive: https://res.cloudinary.com/cloud/image/upload/w_auto,dpr_auto/v1/file.jpg
```

---

## Cost Estimation (Day 2-4 Sprint)

| Metric | Free Tier | Paid |
|--------|-----------|------|
| Storage | 5GB | Up to 4TB |
| Uploads | 25k/month | Unlimited |
| Bandwidth | 5GB/month | Depends on plan |
| Cost | **FREE** | $99+/month |

**For testing**: Free tier is more than sufficient

---

## FAQ

**Q: Can multiple users upload simultaneously?**
→ Yes, Cloudinary handles concurrent uploads automatically

**Q: What if a file fails to upload?**
→ Backend catches error, rolls back transaction, returns warning

**Q: How long do images persist?**
→ Indefinitely (until manually deleted)

**Q: Can images be deleted?**
→ Yes, `CloudinaryService@deleteFile()` handles it

**Q: Is there rate limiting?**
→ Free tier: 25k uploads/month (sufficient)
→ No per-minute limit for small files

**Q: Can I download uploaded images?**
→ Yes, direct URL is accessible (https)

**Q: Is there a web interface to view uploads?**
→ Yes, Cloudinary Media Library dashboard

---

## Quick Reference

```bash
# After setting .env, test with:
php artisan tinker
> config('app.cloudinary_cloud_name')
# Should output your cloud name

# Check if credentials are loaded:
> env('CLOUDINARY_CLOUD_NAME')
# Should output your cloud name
```

---

## Support & Resources

- [Cloudinary Documentation](https://cloudinary.com/documentation)
- [Laravel HTTP Client](https://laravel.com/docs/11.x/http-client)
- [Laravel File Upload](https://laravel.com/docs/11.x/requests#files)
- [Community Forum](https://cloudinary.com/community/forums)

---

**Last Updated**: June 9, 2026  
**Status**: Ready for Day 2 Testing  
**Next Step**: Configure credentials and run Postman collection
