# QUANLICS FRONTEND HANDOFF GUIDE
## Complete Frontend Implementation Reference

**Backend Status**: 165/165 tests passing ?  
**Ready for**: React Web + React Native Mobile Development  
**Last Updated**: June 16, 2026

---

## SECTION 1: SYSTEM OVERVIEW

### What is QUANLICS?
QUANLICS digitizes the Vietnamese university scholarship and financial aid workflow. Students submit applications during collection periods, faculty and staff review them, and the rector issues a final decision.

### Main Workflow
Announcement ? Collection Period OPEN ? Student Submits BM01/BM02 ? Faculty Reviews ? Student Affairs Reviews ? Decision Generated ? Rector Approves

### Three Core Forms

**BM01 - Ðon d? ngh? mi?n gi?m h?c phí (Tuition Waiver Request)**
- Students request fee waiver or reduction
- Auto-filled: Name, ID, Faculty, Class, Date
- Student fills: Reason, income level, circumstances
- Attachments: Supporting documents
- Legal basis: Decree 81/2021

**BM02 - Ðon xin hu?ng tr? c?p xã h?i (Social Support Request)**
- Students request financial aid (emergency, disaster, hardship)
- Same structure as BM01
- Different support types: Emergency aid, disaster relief, etc.

**BM03 - Quy?t d?nh do Hi?u tru?ng ký (Rector's Decision)**
- System generates automatically after all approvals
- Contains: List of approved students, support levels, statistics
- Rector reviews and approves (digital signature placeholder)
- Students can download final decision
- Read-only document

### Critical Rule: Application Periods Control
**Students CANNOT create applications at any time.**
- Applications only created when collection period is OPEN
- Once period ends (date passes or manually closed), period becomes read-only
- No applications accepted after end date
- This enforces the paper workflow structure

---

## SECTION 2: ACTORS & RESPONSIBILITIES

### Student (Sinh Viên)
**Permissions:**
- View active collection periods
- Create BM01 and BM02 (only during OPEN periods)
- Upload supporting documents
- Edit draft applications before submission
- Submit application
- View application status and reviewer comments
- Receive requests for additional documents
- View final decision (BM03)
- Download results

**Pages:** Home, Periods, Create BM01, Create BM02, My Applications, Application Details, Timeline, Results, Notifications

**Web:** Full desktop experience  
**Mobile:** Full mobile experience (primary interface for students)

---

### Faculty Reviewer (Phòng Khoa)
**Responsibilities:**
- Review applications from their faculty
- Verify student information
- Review supporting documents
- Add verification comments
- Approve or request additional documents
- Mark applications "Faculty Verified"

**Pages:** Dashboard, Review Queue, Application Detail, Document Viewer, Approval Form

**Device:** Web only

---

### Student Affairs Office - CTSV (Phòng Công tác Sinh viên)
**Responsibilities:**
- Manage collection periods (create, open, close)
- Review applications after faculty
- Verify completeness
- Request additional documents
- Approve applications
- Coordinate with Finance and Rector

**Pages:** Dashboard, Period Management, Review Queue, Batch Approval, Document Requests, Analytics

**Device:** Web only

---

### Head of Student Affairs (Tru?ng Phòng CTSV)
**Responsibilities:**
- Final review before decision
- Approve or reject decision
- Submit decision to Rector
- View comprehensive reports

**Pages:** Dashboard, Final Review, Decision Approval, Reports

**Device:** Web only

---

### Finance Office (Phòng Tài Chính)
**Responsibilities:**
- View approved applications
- Calculate support amounts per student
- Export payment lists
- Generate financial reports

**Pages:** Dashboard, Approved List, Support Calculation, Export Reports

**Device:** Web only

---

### Rector / University President (Hi?u Tru?ng)
**Responsibilities:**
- Review final decision (BM03)
- Approve or reject entire decision
- Add digital signature (placeholder)
- Issue final decision

**Pages:** Dashboard, Decision Review, Signature, Issued Decisions

**Device:** Web only

---

### Administrator (Qu?n Tr? Viên)
**Responsibilities:**
- Manage users and roles
- Create/manage collection periods
- Create announcements
- System settings and monitoring
- View audit logs

**Pages:** Dashboard, User Management, Period Management, Announcements, Settings, Audit Logs

**Device:** Web only

---

### AI System (Intelligent Components)
**Capabilities:**
- Document OCR and analysis
- Student chatbot for FAQs
- Recommendation engine for support levels
- Information extraction from documents

**Integration:** Web only, supports CTSV and Faculty reviewers

---

## SECTION 3: COLLECTION PERIODS (Ð?t Thu H? So)

### Entity Fields
id: MaDot (Primary Key)
name: TenDot (e.g., "Ð?t 1 - HK1 Nam h?c 2025-2026")
semester: Extracted from name (HK1, HK2)
academic_year: MaNamHoc (FK to NamHoc table)
start_date: NgayBatDau (ISO date)
end_date: NgayKetThuc (ISO date)
status: TrangThaiDot (0=CLOSED, 1=OPEN)

### Business Rules
1. **OPEN Period**: status=1 AND today <= end_date
   - Students see "T?o Ðon M?i" button
   - Can create new applications
   - Can upload documents
   - Can modify drafts

2. **CLOSED Period**: status=0 OR today > end_date
   - All applications read-only
   - Cannot create new applications
   - Cannot modify existing
   - Cannot delete documents
   - Show "Ð?t thu h? so dã k?t thúc"

3. **Frontend Calculations**:
   - Show countdown: "Còn X ngày Y gi?"
   - Disable submit if period closed
   - Sort by end_date (closest deadline first)
   - Show status badge: OPEN (green) / CLOSED (gray)

---

## SECTION 4: APPLICATION STATE MACHINE

### States
1. DRAFT - Initial state when student saves form, can edit/delete
2. SUBMITTED - Student clicked "G?i Ðon", form locked, faculty notified
3. FACULTY_REVIEWED - Faculty reviewed and approved, moved to CTSV
4. STUDENT_AFFAIRS_REVIEWED - CTSV reviewed and approved
5. NEEDS_MORE_DOCUMENTS - Reviewer requested additional docs
6. APPROVED - All approvals complete, included in BM03
7. REJECTED - Rejected at any stage, cannot resubmit this period
8. INCLUDED_IN_DECISION - Added to BM03, awaiting Rector approval
9. COMPLETED - Rector approved BM03, decision official

### Transition Rules
From DRAFT: Can go to SUBMITTED (submit), DELETED (delete)
From SUBMITTED: Can go to FACULTY_REVIEWED (approve), NEEDS_MORE_DOCUMENTS (request), REJECTED (reject)
From NEEDS_MORE_DOCUMENTS: Can go to SUBMITTED (resubmit after upload)
From FACULTY_REVIEWED: Can go to STUDENT_AFFAIRS_REVIEWED (CTSV approve)
From STUDENT_AFFAIRS_REVIEWED: Can go to APPROVED (approve), REJECTED (reject)
From APPROVED: Can go to INCLUDED_IN_DECISION (system generates BM03)
From INCLUDED_IN_DECISION: Can go to COMPLETED (Rector approves)

---

## SECTION 5: BM01 - TUITION WAIVER FORM

### Auto-filled Fields (Read-only)
- Student Name: From NguoiDung.HoTen
- Student ID: From NguoiDung.MaNguoiDung
- Faculty: From Faculty FK
- Class: From Class FK
- Course Year: Calculated from enrollment
- Semester: From collection period
- Academic Year: From collection period
- Submission Date: Current date

### Student-filled Fields (Required)
- Reason for Request: Text area (min 20 chars)
- Monthly Family Income: Radio buttons (Du?i 2M / 2-5M / 5-10M / Trên 10M)
- Number of Household Members: Number input
- Circumstances: Checkboxes (multiple: job loss, illness, expenses, disaster, debt, other)

### Supporting Documents (Checkboxes + Upload)
- Student selects which documents to attach
- File upload for each selected document
- Max file size: 5MB per file
- Supported: PDF, JPG, PNG

### Legal Basis (Read-only)
- Legal Reference: "Ði?u 3, Ngh? d?nh 81/2021/NÐ-CP"
- Support Levels: "100% h?c phí ho?c 70% ho?c 50%"

### Declaration & Agreement
- Student reads: "Tôi xác nh?n các thông tin khai báo là dúng s? th?t..."
- Must check: "Tôi d?ng ý"
- Then can submit

### Validation Rules
- All required fields must be filled
- At least 1 document uploaded
- Agreement checkbox must be checked
- Period must be OPEN
- Form data saved as JSON

---

## SECTION 6: BM02 - SOCIAL SUPPORT FORM

### Structure
Identical to BM01 with these differences:

### Student-filled Fields
- Support Type: Dropdown (Emergency aid, Disaster relief, Long-term support)
- Reason for Request: Text area
- Total Damage/Need Amount: Number
- Family Situation: Text area

### Supporting Documents
- Same types as BM01, plus disaster certification, damage photos, insurance docs

### Legal Basis
- Legal Reference: "Ði?u 5, Ngh? d?nh 81/2021/NÐ-CP"
- Support Levels: "1M - 5M depending on case"

### Validation Rules
Same as BM01

---

## SECTION 7: BM03 - RECTOR'S DECISION

### Auto-generated After All Approvals
- Decision Number: QÐ-[ID]/[Year]-HÐH (auto-generated)
- Academic Year: From collection period
- Semester: From collection period
- Issued Date: Current date

### Contents (Read-only)
- List of approved students: Name, ID, support level, policy type
- Summary statistics: Total approved count, breakdown by support level, total amount
- Metadata: Issued by (Head of CTSV), digital signature area, university seal area

### Rector Approval Flow
1. Rector views BM03
2. Reviews summary and list
3. Clicks "Phê duy?t" to approve
4. System generates PDF
5. Decision marked COMPLETED
6. All approved students notified
7. Students can download decision

### Frontend Actions
- View button: Open BM03 for review
- Approve button: Submit to system
- Download button: Generate and download PDF
- History: Show all issued decisions

---

## SECTION 8: WEB APPLICATION

### Technology Stack
Framework: React 18
Build Tool: Vite
Language: TypeScript
Styling: Tailwind CSS
UI Components: shadcn/ui
State Management: TanStack Query
HTTP Client: Axios
Routing: React Router v6
Authentication: Laravel Sanctum tokens

### Key Features
- Role-based access control (RBAC)
- Real-time notifications
- Document upload with preview
- Form auto-save
- Responsive design
- Vietnamese language
- PDF export
- Audit logging

---

## SECTION 9: MOBILE APPLICATION

### Technology Stack
Framework: React Native
Build Tool: Expo
Router: Expo Router
Language: TypeScript
Styling: NativeWind
State Management: TanStack Query
HTTP Client: Axios

### Scope
**Students only** - All other actors use web only.

### Pages
Home, Collection Periods, Create BM01, Create BM02, My Applications, Application Details, Timeline, Results, AI Chatbot, Profile, Notifications

### Key Features
- Mobile-first design
- Offline support (cached applications)
- Camera integration for document upload
- Push notifications
- Form validation
- Auto-save drafts
- Document preview
- Vietnamese date format

---

## SECTION 10: API ENDPOINT MAPPING

### Collection Periods
GET /api/periods - List all periods
GET /api/periods?status=OPEN - List OPEN periods
GET /api/periods/{id} - Get period details
POST /api/periods - Create period
PUT /api/periods/{id} - Update period
DELETE /api/periods/{id} - Delete period

### Applications
GET /api/applications - List my applications
POST /api/applications - Create application
GET /api/applications/{id} - Get application details
PUT /api/applications/{id} - Update application
POST /api/applications/{id}/submit - Submit application
DELETE /api/applications/{id} - Delete draft

### Documents
GET /api/applications/{id}/documents - List documents
POST /api/applications/{id}/documents - Upload document
DELETE /api/applications/{id}/documents/{doc_id} - Delete document
GET /api/documents/{id}/preview - Preview document

### Review & Approval
POST /api/applications/{id}/approve - Approve
POST /api/applications/{id}/reject - Reject
POST /api/applications/{id}/request-documents - Request docs
POST /api/applications/{id}/comments - Add comment

### Decision
GET /api/decisions - List decisions
POST /api/decisions/generate - Generate BM03
GET /api/decisions/{id} - Get decision details
POST /api/decisions/{id}/approve - Rector approves
GET /api/decisions/{id}/pdf - Download PDF

### Authentication
POST /api/auth/login - Login
POST /api/auth/logout - Logout
GET /api/auth/me - Get current user
GET /api/users/{id} - Get user profile
PUT /api/users/{id} - Update profile

---

## SECTION 11: FOLDER STRUCTURE

### Web (src/)
components/ (common, forms, layouts)
features/ (student, faculty, ctsv, rector, admin, shared)
pages/
services/
hooks/
routes/
types/
utils/
App.tsx
main.tsx

### Mobile (app/)
(auth)/ (login, register)
(student)/ (tabs: home, periods, create, applications, results, chat, profile)
components/
services/
hooks/
types/
utils/
app.json

---

## SECTION 12: IMPORTANT NOTES

### Validation
- Client-side: Validate before submit
- Server-side: Re-validate on backend (always trust server)
- Real-time: Show validation errors as user types
- Error messages: In Vietnamese, clear and actionable

### Loading States
- Show spinner during API calls
- Disable buttons during submission
- Show skeleton screens while loading lists
- Disable form while uploading
- Show upload progress bars

### Error Handling
- Display errors in toast/alert
- Retry logic for failed requests
- Handle network offline gracefully
- Log errors for debugging
- User-friendly error messages

### Role-Based Permissions
- Check user role on frontend (UI layer)
- Never trust frontend for authorization (backend enforces)
- Hide/show buttons based on role
- Redirect unauthorized users to 403
- Check user.role in permission constants

### File Upload
- Max 5MB per file
- Accepted types: PDF, JPG, PNG
- Show file preview before upload
- Display upload progress
- Validate on client before sending
- Store in Cloudinary

### Date Formatting
- Display: DD/MM/YYYY (Vietnamese format)
- Store: ISO 8601 (YYYY-MM-DD)
- Countdown: "Còn X ngày Y gi?"
- Timezone: UTC, convert locally
- Locale: Vietnamese (vi-VN)

### Authentication
- Store JWT token in localStorage
- Include in Authorization header
- Refresh on 401 response
- Clear on logout
- Use Sanctum or header-based auth

### Vietnamese Language
- All UI text in Vietnamese
- API responses may include English terms
- Date format: DD/MM/YYYY
- Number format: 1.000.000
- Currency: Ð

### Performance
- Lazy load routes
- Code splitting by feature
- Cache with TanStack Query
- Debounce search inputs
- Paginate large lists
- Optimize images

### Accessibility
- Use semantic HTML
- Include ARIA labels
- Keyboard navigation support
- High contrast colors
- Touch targets: min 44x44px

---

## QUICK REFERENCE

### Environment Variables
VITE_API_URL=http://localhost:8000/api
VITE_APP_NAME=QUANLICS
VITE_UPLOAD_MAX_SIZE=5242880

### Common Tasks
- Create new page: Add component + route
- Add API endpoint: Define type + service + use in component
- Handle auth: Check useAuth() hook + redirect if needed
- Add Vietnamese text: Use constants or inline strings
- Handle file upload: Use DocumentUpload component + call API

---

**This document is complete and serves as the ONLY reference needed for frontend development.**
