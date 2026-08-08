<?php

declare(strict_types=1);

namespace App\Modules\Sis\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Http\RequestContext;
use App\Core\Pdf\PdfRenderer;
use App\Modules\Administration\DTOs\DocumentResponse;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Entities\Document;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Administration\Services\DocumentService;
use App\Modules\Sis\DTOs\StudentResponse;
use App\Modules\Sis\DTOs\StudentSectionTransferRequest;
use App\Modules\Sis\DTOs\StudentStatusChangeRequest;
use App\Modules\Sis\DTOs\UpdateStudentRequest;
use App\Modules\Sis\DTOs\UploadStudentPhotoRequest;
use App\Modules\Sis\Entities\Student;
use App\Modules\Sis\Mappers\StudentMapper;
use App\Modules\Sis\Models\StudentGuardianLinkModel;
use App\Modules\Sis\Models\StudentModel;
use Config\Services as AppServices;

/**
 * docs/design/sis/Phase-4.6-Service-Design.md
 */
class StudentService
{
    /**
     * Forward-only lifecycle (Phase 4.2's Student Lifecycle section):
     * DRAFT -> ACTIVE -> PROMOTED -> EXITED -> ARCHIVED.
     *
     * @var list<string>
     */
    private const STATUS_ORDER = [
        Student::STATUS_DRAFT,
        Student::STATUS_ACTIVE,
        Student::STATUS_PROMOTED,
        Student::STATUS_EXITED,
        Student::STATUS_ARCHIVED,
    ];

    /**
     * ADR-023 §2 — accepted student-photo upload extensions.
     *
     * @var list<string>
     */
    private const ALLOWED_PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    public function __construct(
        private readonly StudentModel $studentModel,
        private readonly StudentGuardianLinkModel $studentGuardianLinkModel,
        private readonly StudentMapper $studentMapper,
        private readonly AuditService $auditService,
        private readonly DocumentService $documentService,
        private readonly PdfRenderer $pdfRenderer,
    ) {
    }

    /**
     * docs/ADR/ADR-004-student-stub-creation-shape-and-section-id-timing.md
     * §3 — internal only, called by AdmissionService's Confirm Enrollment
     * orchestration within Admission's own transaction. Never called from
     * a public SIS endpoint; does not open its own transaction.
     *
     * @param array{application_id: int, admission_number: string, full_name: string, dob: string, category: string, aadhaar_number?: ?string} $data
     *
     * @return array<string, mixed>
     */
    public function createStudentStub(array $data): array
    {
        if ($this->studentModel->existsByApplicationId($data['application_id'])) {
            throw new BusinessRuleException(
                'STUDENT_ALREADY_EXISTS_FOR_APPLICATION',
                'A student record already exists for this application.',
            );
        }

        if ($this->studentModel->existsByAdmissionNumber($data['admission_number'])) {
            throw new BusinessRuleException(
                'STUDENT_ADMISSION_NUMBER_ALREADY_TAKEN',
                'This admission number is already taken.',
            );
        }

        $aadhaarNumber = $data['aadhaar_number'] ?? null;

        if ($aadhaarNumber !== null && $this->studentModel->existsByAadhaarNumber($aadhaarNumber)) {
            throw new BusinessRuleException(
                'STUDENT_AADHAAR_NUMBER_ALREADY_TAKEN',
                'This Aadhaar number is already linked to another student.',
            );
        }

        $entity = $this->studentMapper->toEntityFromStub($data);
        $id     = $this->studentModel->insert($entity, true);

        $student = $this->studentModel->find($id);

        $this->auditService->record('Student', $id, AuditLog::ACTION_CREATE, null, $student->toRawArray());

        return $this->studentMapper->toResponse($student)->toArray();
    }

    public function updateStudent(int $id, UpdateStudentRequest $request): StudentResponse
    {
        $student = $this->requireStudent($id);
        $before  = $student->toRawArray();

        if (
            $request->aadhaarNumber !== null
            && $this->studentModel->existsByAadhaarNumberExceptId($request->aadhaarNumber, $id)
        ) {
            throw new BusinessRuleException(
                'STUDENT_AADHAAR_NUMBER_ALREADY_TAKEN',
                'This Aadhaar number is already linked to another student.',
            );
        }

        $this->studentMapper->updateEntity($request, $student);
        $this->studentModel->save($student);

        $after = $this->studentModel->find($id);

        $this->auditService->record('Student', $id, AuditLog::ACTION_UPDATE, $before, $after->toRawArray());

        return $this->studentMapper->toResponse($after);
    }

    /**
     * docs/design/admission/Phase-4/ADR-004 §4 — serves both a stub's
     * first section assignment (DRAFT) and any later transfer (ACTIVE);
     * BR-SIS-005's capacity check applies identically either way.
     */
    public function transferSection(int $id, StudentSectionTransferRequest $request): StudentResponse
    {
        $student = $this->requireStudent($id);
        $before  = $student->toRawArray();

        // Validates the target section exists (cross-module, via
        // Academic's SectionService — throws SECTION_NOT_FOUND if not).
        $section = AppServices::sectionService()->getSection($request->newSectionId);

        $occupancy = $this->studentModel->countBySectionIdAndStatus($request->newSectionId, Student::STATUS_ACTIVE);

        if ($occupancy >= $section->capacity) {
            throw new BusinessRuleException(
                'SECTION_CAPACITY_EXCEEDED',
                'The destination section has no open capacity.',
            );
        }

        $this->studentMapper->updateSection($request, $student);
        $this->studentModel->save($student);

        $after = $this->studentModel->find($id);

        $this->auditService->record('Student', $id, AuditLog::ACTION_UPDATE, $before, $after->toRawArray());

        return $this->studentMapper->toResponse($after);
    }

    public function changeStatus(int $id, StudentStatusChangeRequest $request): StudentResponse
    {
        $student = $this->requireStudent($id);
        $before  = $student->toRawArray();

        $fromIndex = array_search($student->status, self::STATUS_ORDER, true);
        $toIndex   = array_search($request->status, self::STATUS_ORDER, true);

        if ($toIndex === false || $fromIndex === false || $toIndex <= $fromIndex) {
            throw new BusinessRuleException(
                'STUDENT_INVALID_STATUS_TRANSITION',
                "Cannot transition a student from {$student->status} to {$request->status}.",
            );
        }

        if ($request->status === Student::STATUS_ACTIVE) {
            // BR-SIS-003: full_name/dob/category/admission_number are
            // guaranteed non-null since stub creation — section_id is the
            // one field FR-06 completion is responsible for.
            if ($student->section_id === null) {
                throw new BusinessRuleException(
                    'STUDENT_PROFILE_INCOMPLETE',
                    'A section must be assigned before this student can become ACTIVE.',
                );
            }

            // BR-SIS-006.
            if (! $this->studentGuardianLinkModel->existsByStudentId($id)) {
                throw new BusinessRuleException(
                    'STUDENT_NO_GUARDIAN_LINKED',
                    'At least one guardian must be linked before this student can become ACTIVE.',
                );
            }
        }

        $this->studentMapper->updateStatus($request, $student);
        $this->studentModel->save($student);

        $after = $this->studentModel->find($id);

        $this->auditService->record('Student', $id, AuditLog::ACTION_UPDATE, $before, $after->toRawArray());

        return $this->studentMapper->toResponse($after);
    }

    public function getStudent(int $id): StudentResponse
    {
        return $this->studentMapper->toResponse($this->requireStudent($id));
    }

    /**
     * docs/ADR/ADR-023-sis-id-card-generation.md §2 — stores the photo via
     * DocumentService (Document.owner_type = Student) and points
     * Student.photo_document_id at the new row. Regenerating a photo
     * creates a new Document row (matches Document's own no-overwrite
     * history rule, ADR-012 §1) and simply repoints the FK; the previous
     * Document row is left in place, not deleted.
     */
    public function uploadPhoto(int $id, UploadStudentPhotoRequest $request): StudentResponse
    {
        $student = $this->requireStudent($id);
        $before  = $student->toRawArray();

        $extension = strtolower($request->extension);

        if (! in_array($extension, self::ALLOWED_PHOTO_EXTENSIONS, true)) {
            throw new BusinessRuleException(
                'STUDENT_PHOTO_INVALID_EXTENSION',
                'Student photo must be one of: ' . implode(', ', self::ALLOWED_PHOTO_EXTENSIONS) . '.',
            );
        }

        $imageBytes = base64_decode($request->imageBase64, true);

        if ($imageBytes === false || $imageBytes === '') {
            throw new BusinessRuleException('STUDENT_PHOTO_INVALID_DATA', 'image_base64 is not valid base64-encoded image data.');
        }

        $document = $this->documentService->store(
            Document::OWNER_STUDENT,
            $id,
            'Student Photo',
            $imageBytes,
            (int) RequestContext::userId(),
            $extension,
        );

        $student->photo_document_id = $document->documentId;
        $this->studentModel->save($student);

        $after = $this->studentModel->find($id);

        $this->auditService->record('Student', $id, AuditLog::ACTION_UPDATE, $before, $after->toRawArray());

        return $this->studentMapper->toResponse($after);
    }

    /**
     * docs/ADR/ADR-023-sis-id-card-generation.md §3/§4 — renders a plain,
     * deliberately generic-placeholder ID card (literal "School Name"
     * text, an empty bordered logo box — no real branding asset exists to
     * include, same reasoning ADR-012 §3 already used for the other three
     * PDF templates) via the existing dompdf PdfRenderer, and stores it
     * through DocumentService exactly like the other three PDF
     * generators (ReportCardService::generatePdf,
     * PayrollRunService::generatePayslipPdf, InvoiceService::generateInvoicePdf).
     */
    public function generateIdCardPdf(int $id): DocumentResponse
    {
        $student = $this->requireStudent($id);

        $sectionLabel = 'Not Assigned';

        if ($student->section_id !== null) {
            $section      = AppServices::sectionService()->getSection($student->section_id);
            $class        = AppServices::classService()->getClass($section->classId);
            $sectionLabel = $class->className . ' - ' . $section->sectionName;
        }

        $session      = AppServices::academicSessionService()->getCurrentActiveSession();
        $sessionLabel = $session === null ? 'N/A' : $session->sessionName;

        $photoHtml = '<div style="width:120px;height:150px;border:1px solid #999;'
            . 'display:flex;align-items:center;justify-content:center;font-size:12px;color:#666;">Photo</div>';

        if ($student->photo_document_id !== null) {
            $absolutePath = $this->documentService->getAbsolutePath($student->photo_document_id);
            $mimeType     = match (strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION))) {
                'png'   => 'image/png',
                default => 'image/jpeg',
            };
            $dataUri   = 'data:' . $mimeType . ';base64,' . base64_encode((string) file_get_contents($absolutePath));
            $photoHtml = '<img src="' . $dataUri . '" style="width:120px;height:150px;object-fit:cover;border:1px solid #999;" />';
        }

        // ADR-023 §1: a generic, visibly-placeholder branding block — a
        // literal "School Name" text field and an empty bordered logo
        // box, not an invented school identity. Swappable once a real
        // branded template is provided (RGD v2.0 Appendix-E's own
        // Assumptions field for FR-09).
        $html = '<html><body style="font-family:sans-serif;">'
            . '<table width="100%"><tr>'
            . '<td style="width:60px;height:60px;border:1px dashed #999;text-align:center;vertical-align:middle;font-size:10px;color:#999;">Logo</td>'
            . '<td style="text-align:center;font-size:18px;font-weight:bold;">School Name</td>'
            . '</tr></table>'
            . '<hr />'
            . '<table><tr><td>' . $photoHtml . '</td><td style="padding-left:16px;">'
            . '<p><strong>Name:</strong> ' . htmlspecialchars($student->full_name) . '</p>'
            . '<p><strong>Admission No.:</strong> ' . htmlspecialchars($student->admission_number) . '</p>'
            . '<p><strong>Class:</strong> ' . htmlspecialchars($sectionLabel) . '</p>'
            . '<p><strong>Session:</strong> ' . htmlspecialchars($sessionLabel) . '</p>'
            . '</td></tr></table>'
            . '</body></html>';

        $pdfBytes = $this->pdfRenderer->render($html);

        return $this->documentService->store(
            Document::OWNER_STUDENT,
            $id,
            'IdCard',
            $pdfBytes,
            (int) RequestContext::userId(),
        );
    }

    /**
     * @return list<StudentResponse>
     */
    public function listStudentsBySection(int $sectionId): array
    {
        return array_map(
            fn (Student $student): StudentResponse => $this->studentMapper->toResponse($student),
            $this->studentModel->findBySectionId($sectionId),
        );
    }

    private function requireStudent(int $id): Student
    {
        $student = $this->studentModel->find($id);

        if ($student === null) {
            throw new BusinessRuleException('STUDENT_NOT_FOUND', 'Student not found.');
        }

        return $student;
    }
}
