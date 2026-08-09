<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Pdf\PdfRenderer;
use App\Modules\HrPayroll\Models\EmployeeModel;
use App\Modules\HrPayroll\Models\OnboardingChecklistModel;
use CodeIgniter\I18n\Time;

/**
 * Handles onboarding checklist management and HR document generation
 * (Appointment Letter + Staff ID Card PDFs).
 */
class OnboardingChecklistService
{
    public const DEFAULT_ITEMS = [
        ['Offer Letter Signed', 1],
        ['ID Proof Submitted (Aadhaar/PAN)', 2],
        ['Bank Details Verified', 3],
        ['PF Nomination Form Submitted', 4],
        ['Medical Fitness Certificate', 5],
        ['Previous Employment Experience Letter', 6],
        ['Qualification Certificates Submitted', 7],
        ['System Account Created', 8],
        ['Department Induction Done', 9],
        ['ID Card Issued', 10],
    ];

    public function __construct(
        private readonly OnboardingChecklistModel $checklistModel,
        private readonly EmployeeModel $employeeModel,
        private readonly PdfRenderer $pdfRenderer,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    /**
     * Auto-create default checklist for a new employee.
     * Called by EmployeeService::createEmployee after insert.
     */
    public function createDefaultChecklist(int $employeeId): void
    {
        $now = Time::now()->toDateTimeString();
        foreach (self::DEFAULT_ITEMS as [$name, $order]) {
            $this->checklistModel->insert([
                'employee_id' => $employeeId,
                'item_name'   => $name,
                'is_done'     => 0,
                'sort_order'  => $order,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    /**
     * List checklist items for an employee.
     * @return array<string,mixed>
     */
    public function getChecklist(int $employeeId): array
    {
        $this->moduleAuthorizer->assertManage('hr_payroll.manage');
        $items = $this->checklistModel->forEmployee($employeeId);
        $done  = 0;
        $result = [];
        foreach ($items as $item) {
            if ($item->is_done) {
                $done++;
            }
            $result[] = [
                'checklist_id' => $item->checklist_id,
                'item_name'    => $item->item_name,
                'is_done'      => $item->is_done,
                'done_at'      => $item->done_at,
                'done_by'      => $item->done_by,
                'remarks'      => $item->remarks,
                'sort_order'   => $item->sort_order,
            ];
        }
        return [
            'items'    => $result,
            'done'     => $done,
            'total'    => count($result),
            'percent'  => count($result) > 0 ? round($done / count($result) * 100) : 0,
        ];
    }

    /**
     * Toggle / update a single checklist item.
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function updateChecklistItem(int $employeeId, int $checklistId, array $data): array
    {
        $this->moduleAuthorizer->assertManage('hr_payroll.manage');

        $item = $this->checklistModel->where('employee_id', $employeeId)->find($checklistId);
        if ($item === null) {
            throw new BusinessRuleException('CHECKLIST_ITEM_NOT_FOUND', 'Checklist item not found.');
        }

        $isDone  = (bool) ($data['is_done'] ?? $item->is_done);
        $remarks = isset($data['remarks']) ? (string) $data['remarks'] : $item->remarks;
        $doneby  = $isDone ? ($data['done_by'] ?? $item->done_by) : null;
        $doneAt  = $isDone ? ($item->done_at ?? Time::now()->toDateTimeString()) : null;

        $this->checklistModel->update($checklistId, [
            'is_done'    => $isDone ? 1 : 0,
            'done_at'    => $doneAt,
            'done_by'    => $doneby,
            'remarks'    => $remarks,
            'updated_at' => Time::now()->toDateTimeString(),
        ]);

        $updated = $this->checklistModel->find($checklistId);
        return [
            'checklist_id' => $updated->checklist_id,
            'item_name'    => $updated->item_name,
            'is_done'      => $updated->is_done,
            'done_at'      => $updated->done_at,
            'done_by'      => $updated->done_by,
            'remarks'      => $updated->remarks,
        ];
    }

    /**
     * Update verification status of one or more documents in employee's documents_json.
     * @param array<int,array<string,mixed>> $updates  e.g. [{index:0, status:'Verified', remark:'OK'}]
     * @return array<string,mixed>
     */
    public function verifyDocuments(int $employeeId, array $updates): array
    {
        $this->moduleAuthorizer->assertManage('hr_payroll.manage');

        $employee = $this->employeeModel->find($employeeId);
        if ($employee === null) {
            throw new BusinessRuleException('EMPLOYEE_NOT_FOUND', 'Employee not found.');
        }

        $docs = is_array($employee->documents_json) ? $employee->documents_json : [];

        foreach ($updates as $upd) {
            $idx = (int) ($upd['index'] ?? -1);
            if (isset($docs[$idx])) {
                if (isset($upd['status'])) {
                    $docs[$idx]['status'] = $upd['status']; // Pending|Verified|Rejected
                }
                if (isset($upd['remark'])) {
                    $docs[$idx]['remark'] = $upd['remark'];
                }
                $docs[$idx]['verified_at'] = Time::now()->toDateTimeString();
            }
        }

        $this->employeeModel->update($employeeId, [
            'documents_json' => json_encode($docs),
        ]);

        return ['documents_json' => $docs];
    }

    /**
     * Generate Appointment Letter PDF bytes for an employee.
     */
    public function generateAppointmentLetter(int $employeeId): string
    {
        $this->moduleAuthorizer->assertManage('hr_payroll.manage');

        $employee = $this->employeeModel->find($employeeId);
        if ($employee === null) {
            throw new BusinessRuleException('EMPLOYEE_NOT_FOUND', 'Employee not found.');
        }

        $salaryRows = '';
        $totalSalary = 0;
        if (is_array($employee->salary_structure_json)) {
            foreach ($employee->salary_structure_json as $component => $amount) {
                $amount = (float) $amount;
                $totalSalary += $amount;
                $salaryRows .= sprintf(
                    '<tr><td style="padding:6px 8px;border-bottom:1px solid #eee;">%s</td><td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:right;">&#8377;%s</td></tr>',
                    htmlspecialchars(ucwords(str_replace('_', ' ', $component))),
                    number_format($amount)
                );
            }
        }
        $salaryRows .= sprintf(
            '<tr style="background:#f8fafc;font-weight:bold;"><td style="padding:6px 8px;">Total CTC (Monthly)</td><td style="padding:6px 8px;text-align:right;">&#8377;%s</td></tr>',
            number_format($totalSalary)
        );

        $date = date('d F Y');
        $joiningDate = $employee->joining_date ?? 'N/A';
        $probationEnd = $employee->probation_end_date ?? 'N/A';

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 13px; color: #1e293b; margin: 40px; }
  .header { text-align: center; border-bottom: 3px solid #1e40af; padding-bottom: 16px; margin-bottom: 24px; }
  .school-name { font-size: 22px; font-weight: bold; color: #1e40af; }
  .school-sub { font-size: 11px; color: #64748b; margin-top: 4px; }
  .title { font-size: 16px; font-weight: bold; text-align: center; margin: 20px 0; text-decoration: underline; }
  .body-text { line-height: 1.8; }
  table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  .info-table td { padding: 5px 8px; }
  .info-table .label { font-weight: bold; width: 180px; color: #374151; }
  .salary-table td { border: 1px solid #e2e8f0; }
  .footer { margin-top: 60px; display: flex; justify-content: space-between; }
  .sign-block { text-align: center; width: 200px; }
  .sign-line { border-top: 1px solid #1e293b; margin-top: 40px; padding-top: 6px; font-size: 11px; }
</style>
</head>
<body>
<div class="header">
  <div class="school-name">SCHOOL NAME</div>
  <div class="school-sub">School Address • City, State • Phone: +91-XXXXXXXXXX</div>
</div>

<div style="text-align:right; color:#64748b; font-size:11px;">Date: {$date}</div>

<div class="title">APPOINTMENT LETTER</div>

<p class="body-text">
Dear <strong>{$employee->full_name}</strong>,
</p>
<p class="body-text">
We are pleased to appoint you as <strong>{$employee->designation_id}</strong> in our institution, subject to the terms and conditions mentioned below. We welcome you to our team and look forward to a long and productive association.
</p>

<table class="info-table" style="margin-top:16px;">
  <tr><td class="label">Employee Code</td><td>{$employee->employee_code}</td></tr>
  <tr><td class="label">Full Name</td><td>{$employee->full_name}</td></tr>
  <tr><td class="label">Department</td><td>As per assignment</td></tr>
  <tr><td class="label">Date of Joining</td><td>{$joiningDate}</td></tr>
  <tr><td class="label">Probation Period</td><td>Till {$probationEnd}</td></tr>
  <tr><td class="label">Employment Type</td><td>Full-time, {$employee->staff_type}</td></tr>
</table>

<p style="margin-top:20px;font-weight:bold;">Salary Structure (Monthly):</p>
<table class="salary-table">{$salaryRows}</table>

<p class="body-text" style="margin-top:20px;">
This appointment is subject to verification of your original documents, satisfactory reference checks, and successful completion of probation. The school reserves the right to terminate this appointment with appropriate notice as per institutional policy.
</p>

<p class="body-text">
Please sign and return a copy of this letter as confirmation of your acceptance of the above terms.
</p>

<div style="margin-top:50px; display:table; width:100%;">
  <div style="display:table-cell; width:50%;">
    <p style="margin-top:40px; border-top:1px solid #1e293b; padding-top:6px; display:inline-block; min-width:200px; font-size:11px; text-align:center;">Authorised Signatory<br><em>(Principal / HR Head)</em></p>
  </div>
  <div style="display:table-cell; width:50%; text-align:right;">
    <p style="margin-top:40px; border-top:1px solid #1e293b; padding-top:6px; display:inline-block; min-width:200px; font-size:11px; text-align:center;">Employee Signature<br><em>{$employee->full_name}</em></p>
  </div>
</div>
</body>
</html>
HTML;

        return $this->pdfRenderer->render($html);
    }

    /**
     * Generate Staff ID Card PDF bytes for an employee.
     * Landscape A6 card format.
     */
    public function generateIdCard(int $employeeId): string
    {
        $this->moduleAuthorizer->assertManage('hr_payroll.manage');

        $employee = $this->employeeModel->find($employeeId);
        if ($employee === null) {
            throw new BusinessRuleException('EMPLOYEE_NOT_FOUND', 'Employee not found.');
        }

        $joiningYear = $employee->joining_date ? substr($employee->joining_date, 0, 4) : date('Y');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  @page { size: A6 landscape; margin: 0; }
  body { margin: 0; padding: 0; font-family: Arial, sans-serif; }
  .card {
    width: 148mm; height: 105mm;
    background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 50%, #172554 100%);
    color: #fff;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
    box-sizing: border-box;
  }
  .card-header {
    background: rgba(255,255,255,0.1);
    padding: 8px 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 2px solid rgba(255,255,255,0.2);
  }
  .logo-box {
    width: 36px; height: 36px;
    background: rgba(255,255,255,0.2);
    border: 2px dashed rgba(255,255,255,0.5);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 8px;
    color: rgba(255,255,255,0.7);
    text-align: center;
  }
  .school-info { flex: 1; }
  .school-name { font-size: 13px; font-weight: bold; color: #fff; }
  .school-sub { font-size: 8px; color: rgba(255,255,255,0.7); }
  .card-body { display: flex; flex: 1; padding: 10px 12px; gap: 12px; }
  .photo-box {
    width: 56px; height: 68px;
    background: rgba(255,255,255,0.15);
    border: 2px dashed rgba(255,255,255,0.4);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 8px;
    color: rgba(255,255,255,0.6);
    text-align: center;
    flex-shrink: 0;
  }
  .emp-details { flex: 1; }
  .emp-name { font-size: 14px; font-weight: bold; color: #fff; margin-bottom: 4px; }
  .emp-designation { font-size: 10px; color: #93c5fd; margin-bottom: 6px; }
  .detail-row { display: flex; font-size: 9px; margin-bottom: 3px; gap: 4px; }
  .detail-label { color: rgba(255,255,255,0.6); min-width: 60px; }
  .detail-value { color: #fff; font-weight: bold; }
  .card-footer {
    background: rgba(0,0,0,0.3);
    padding: 5px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid rgba(255,255,255,0.1);
  }
  .footer-text { font-size: 7px; color: rgba(255,255,255,0.5); }
  .barcode-placeholder {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 2px;
    padding: 2px 6px;
    font-size: 7px;
    color: rgba(255,255,255,0.6);
    font-family: monospace;
  }
  .badge {
    background: #f59e0b;
    color: #1e293b;
    font-size: 8px;
    font-weight: bold;
    padding: 2px 6px;
    border-radius: 10px;
  }
</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <div class="logo-box">LOGO</div>
    <div class="school-info">
      <div class="school-name">SCHOOL NAME</div>
      <div class="school-sub">Affiliated School • Est. 1990</div>
    </div>
    <div class="badge">STAFF</div>
  </div>
  <div class="card-body">
    <div class="photo-box">PHOTO</div>
    <div class="emp-details">
      <div class="emp-name">{$employee->full_name}</div>
      <div class="emp-designation">As per Designation</div>
      <div class="detail-row">
        <span class="detail-label">Emp Code:</span>
        <span class="detail-value">{$employee->employee_code}</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Department:</span>
        <span class="detail-value">As per Dept.</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Staff Type:</span>
        <span class="detail-value">{$employee->staff_type}</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Joined:</span>
        <span class="detail-value">{$employee->joining_date}</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Valid:</span>
        <span class="detail-value">{$joiningYear} — {$employee->status}</span>
      </div>
    </div>
  </div>
  <div class="card-footer">
    <div class="footer-text">If found, please return to school office.<br>This card is non-transferable.</div>
    <div class="barcode-placeholder">{$employee->employee_code}</div>
  </div>
</div>
</body>
</html>
HTML;

        return $this->pdfRenderer->render($html);
    }
}
