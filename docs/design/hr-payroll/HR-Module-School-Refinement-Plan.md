---
status: Proposed
date: 2026-08-08
author: AI Development Team
relates-to: ADR-008, ADR-015, ADR-024, Appendix-C v1.1, Appendix-G v1.0
---

# HRMS Module — Indian School Context Audit & Refinement Plan

## 1. Executive Summary

This document presents a comprehensive audit of the **HR & Payroll (HRMS)** module in the School Management System (CI4 backend) against the operational requirements of K-12 educational institutions in the **Indian context** (CBSE, ICSE, State Boards).

While the initial Phase 1/Phase 2 implementation established foundational master data (`Department`, `Designation`), staff lifecycle (`Employee`), leave management (`LeaveRequest`), staff attendance closure (`attendance_closures`), and payroll processing (`PayrollRun`), a complete School HRMS requires specific extensions for **staff classification (Teaching vs. Non-Teaching), statutory compliance (PF, ESI, TDS, PT), Indian KYC (Aadhaar, PAN, Bank details), expanded school leave categories (LWP, Duty Leave, Maternity Leave), and structured payslip generation.**

All proposed changes strictly maintain existing file structures, architectural standards (ADR-024 RBAC, Service-layer authorization, BaseModel audit tracking), and backward database compatibility through additive migrations.

---

## 2. Current Implementation Audit

### 2.1 Codebase & Schema Inventory

The current HRMS implementation consists of the following components:

| Layer | Component File | Current Responsibility |
|---|---|---|
| **Entities** | `Department.php` | Department master data |
| | `Designation.php` | Designation master data |
| | `Employee.php` | Staff profile with `employee_code`, `joining_date`, `exit_date`, `salary_structure_json`, `status` |
| | `LeaveRequest.php` | Leave records with types `CL`, `SL`, `EL` and status `Pending`, `Approved`, `Rejected` |
| | `PayrollRun.php` | Monthly payroll with `pay_period` (`YYYY-MM`), `gross_pay`, `deductions_json`, `net_pay`, `status` |
| | `AttendanceClosure.php` | Month-end attendance lock record gating payroll runs |
| **Services** | `DepartmentService.php` | Gated by `hr_payroll.manage` |
| | `DesignationService.php` | Gated by `hr_payroll.manage` |
| | `EmployeeService.php` | Profile CRUD, auto user account deactivation on `exit_date` |
| | `LeaveRequestService.php` | Request creation, approval with annual balance tracking (CL:12, SL:10, EL:15) and HR override |
| | `PayrollRunService.php` | Gated by `attendance_closures` check, computes `net_pay = gross_pay - sum(deductions)` |
| **Controllers** | `DepartmentController.php` | REST API `/api/v1/hr-payroll/departments` |
| | `DesignationController.php` | REST API `/api/v1/hr-payroll/designations` |
| | `EmployeeController.php` | REST API `/api/v1/hr-payroll/employees` |
| | `LeaveRequestController.php` | REST API `/api/v1/hr-payroll/leave-requests` |
| | `PayrollRunController.php` | REST API `/api/v1/hr-payroll/payroll-runs` |

---

## 3. Gap Analysis (Indian School HRMS Requirements)

Comparing the current codebase against real-world Indian School HR Operations reveals 6 key operational gaps:

### Gap 1: Staff Classification (Teaching vs. Non-Teaching vs. Support)
- **School Need**: Schools distinguish between:
  - **Teaching Staff**: PGT (Post Graduate Teacher), TGT (Trained Graduate Teacher), PRT (Primary Teacher), NTT (Nursery/Pre-Primary Teacher).
  - **Non-Teaching Staff**: Administrative Staff, Accountants, IT Staff, Librarians, Lab Assistants.
  - **Support / Auxiliary Staff**: Security Guards, Housekeeping, Transport Drivers & Conductors.
- **Current State**: Employees only link to `department_id` and `designation_id`. There is no `staff_type` or qualification field.

### Gap 2: Statutory Compliance & Indian KYC Identifiers
- **School Need**: Indian labor and tax compliance requires storing:
  - **Aadhaar Number** (12-digit UIDAI)
  - **PAN Card** (10-character Permanent Account Number for TDS)
  - **UAN / PF Account Number** (Universal Account Number for EPFO)
  - **ESI IP Number** (Employee State Insurance)
  - **Bank Details**: Bank Name, Account Number, IFSC Code (for direct bank salary transfer via NEFT/RTGS).
- **Current State**: None of these statutory compliance fields exist on the `employees` table.

### Gap 3: Expanded School Leave Categories & LWP
- **School Need**: Indian school leave policies include:
  - **CL**: Casual Leave
  - **SL**: Sick / Medical Leave
  - **EL**: Earned Leave / Privilege Leave
  - **ML**: Maternity Leave
  - **LWP / LOP**: Loss of Pay / Leave Without Pay (deducts salary during payroll calculation)
  - **DL**: Duty Leave / On-Duty (for CBSE/ICSE board exam duty, evaluation, sports tournaments).
- **Current State**: `leave_type` ENUM only supports `['CL', 'SL', 'EL']`.

### Gap 4: Standardized Salary Component Structure
- **School Need**: Indian school payslips require clear itemization:
  - **Earnings**: Basic Pay, HRA (House Rent Allowance), DA (Dearness Allowance), Special/Conveyance Allowance.
  - **Statutory Deductions**: EPF (Employee Provident Fund @ 12%), ESI (@ 0.75%), Professional Tax (PT), TDS (Income Tax).
  - **Other Deductions**: LWP deduction, Transport/Quarter charges, Advance recovery.
- **Current State**: `salary_structure_json` and `deductions_json` are unstructured JSON fields without a standardized schema or default calculator helper.

### Gap 5: Onboarding & Probation Management
- **School Need**: Schools track `probation_end_date`, `confirmation_date`, and `qualification` (e.g., B.Ed, M.Ed, Ph.D., CTET/STET qualification status).
- **Current State**: Only `joining_date` and `exit_date` are tracked.

### Gap 6: Structured Payslip & Tax Summary View
- **School Need**: Staff members need formatted digital payslip responses containing School Name, Employee Details, Working Days, LWP Days, Earnings Breakdown, Deductions Breakdown, Net Salary in Words.
- **Current State**: `PayrollRunResponse` returns raw database fields without structured payslip breakdown.

---

## 4. Proposed Technical Enhancements & Architecture

To address these gaps while strictly preserving existing code, contracts, and ADR-024 compliance, we propose the following additive enhancements:

```
                  +-------------------------------------------------------+
                  |                    Employee Entity                    |
                  +-------------------------------------------------------+
                  | + staff_type: ENUM(Teaching, NonTeaching, Support)    |
                  | + qualification: VARCHAR(150)                         |
                  | + aadhaar_number: VARCHAR(12) [Encrypted / Masked]    |
                  | + pan_number: VARCHAR(10)                             |
                  | + pf_uan: VARCHAR(12)                                 |
                  | + esi_number: VARCHAR(17)                             |
                  | + bank_name, bank_account_no, bank_ifsc               |
                  | + probation_end_date, confirmation_date               |
                  +-------------------------------------------------------+
                                              |
                   +--------------------------+--------------------------+
                   |                                                     |
                   v                                                     v
+------------------------------------+                +------------------------------------+
|        LeaveRequest Entity         |                |         PayrollRun Entity          |
+------------------------------------+                +------------------------------------+
| + leave_type: ENUM                 |                | + lwp_days: INT                    |
|   (CL, SL, EL, ML, LWP, DL)        |                | + earnings_json: JSON              |
| + reason: TEXT                     |                | + statutory_deductions_json: JSON  |
| + duty_leave_reference: VARCHAR    |                | + payslip_summary_json: JSON       |
+------------------------------------+                +------------------------------------+
```

### 4.1 Schema Migration Changes (Additive Only)

#### 1. Migration: `AddIndianSchoolFieldsToEmployeesTable`
- Adds:
  - `staff_type` ENUM(`'Teaching'`, `'NonTeaching'`, `'Support'`, `'Administrative'`) DEFAULT `'Teaching'`
  - `qualification` VARCHAR(150) NULL
  - `aadhaar_number` VARCHAR(12) NULL
  - `pan_number` VARCHAR(10) NULL
  - `pf_uan` VARCHAR(12) NULL
  - `esi_number` VARCHAR(17) NULL
  - `bank_name` VARCHAR(100) NULL
  - `bank_account_number` VARCHAR(30) NULL
  - `bank_ifsc_code` VARCHAR(11) NULL
  - `probation_end_date` DATE NULL
  - `confirmation_date` DATE NULL

#### 2. Migration: `AddSchoolLeaveTypesToLeaveRequestsTable`
- Widens `leave_type` ENUM to include `['CL', 'SL', 'EL', 'ML', 'LWP', 'DL']`.
- Adds `reason` TEXT NULL.
- Adds `duty_leave_reference` VARCHAR(100) NULL (for Board duty order / event order reference).

#### 3. Migration: `AddLwpAndEarningsToPayrollRunsTable`
- Adds `lwp_days` INT DEFAULT 0.
- Adds `earnings_json` JSON NULL.

---

## 5. Implementation Roadmap & Milestones

| Phase | Description | Deliverables | Status |
|---|---|---|---|
| **Phase A** | Architecture & Gap Analysis | Document: `HR-Module-School-Refinement-Plan.md` | **Completed** |
| **Phase B** | Database Migrations | Additive migration files with safe rollback (`down()`) | Pending Approval |
| **Phase C** | Domain Entities & DTOs | Updated `Employee`, `LeaveRequest`, `PayrollRun` Entities & DTOs | Pending |
| **Phase D** | Service Layer Logic | Refined `EmployeeService`, `LeaveRequestService`, `PayrollRunService` | Pending |
| **Phase E** | REST API Controllers & OpenAPI | Updated OpenAPI Swagger attributes & Response objects | Pending |
| **Phase F** | Testing & Verification | Unit & Feature tests (`HrPayrollRbacTest`, `PayrollRunTest`, etc.) | Pending |

---

## 6. Verification & Quality Assurance Plan

1. **Static Analysis**: `phpstan analyse` (Level 6) — 0 errors.
2. **Code Linting**: `composer lint` (PHPCS) — 0 errors.
3. **Automated Tests**: `composer test` (PHPUnit) — All test suites passing with 100% assertions.
4. **Dev Server Verification**: Test endpoints via `php spark serve` with `IT Admin` vs `Employee` JWT tokens.
