# ADR-025: Administration Module Feature Scope

## Status
Approved

## Context
Following multiple rounds of feature audits and validation, the functional boundaries, India-specific requirements, and cross-module configuration references of the Administration module have been finalized. This specification establishes a single, comprehensive reference for the 32 Administration features, ensuring the separation of master configurations from operational module execution.

This document serves as the authoritative source of truth for the Administration module requirements and will be integrated into the approved ERP documentation.

---

## 1. School & Institution Profile

### 1.1 School Profile & Branding Specification

This feature defines the school's master identity, address, categories, board references, institutional contacts, and global branding assets.

#### 1.1.1 Feature Structure
* **Basic Identity**: Core details representing the legal name and short identifier of the school.
* **Address (Indian Geographic Structure)**: Hierarchical address setup supporting local government reporting structures.
* **School Classification**: Configurable selectors outlining target demographics and class boundaries.
* **Board & Recognition**: Government and educational board affiliation tracking.
* **Institutional Identifiers**: National/State specific school code references.
* **Official Contact & HR Association**: Points of contact mapping, supporting dynamic linking to employee profiles.
* **Reusable Branding & Document Layout**: Configurations for logo assets and header/footer styling overlays.

#### 1.1.2 Field Specification Table

| #  | Category                  | Field / Feature                   | Priority  | India Specific? | Required? | Conditional? | Description |
| -- | ------------------------- | --------------------------------- | --------- | --------------- | --------- | ------------ | ----------- |
| 1  | Basic Identity            | School Name                       | Core      | No              | Yes       | No           | Legal name of the institution. |
| 2  | Basic Identity            | Short Name / Abbreviation         | Core      | No              | Yes       | No           | Used for short headers, SMS alerts, and prefix generation. |
| 3  | Basic Identity            | School Code                       | Important | No              | No        | Yes          | Internal/Group specific identifier code. |
| 4  | Address                   | Address Line 1 & 2                | Core      | No              | Yes       | No           | Premise door number, street, and locality details. |
| 5  | Address                   | Village / Town / City             | Core      | No              | Yes       | No           | City, town, or village reference name. |
| 6  | Address                   | State                             | Core      | Yes             | Yes       | No           | Selected State or Union Territory dropdown master. |
| 7  | Address                   | District                          | Core      | Yes             | Yes       | Yes          | Selected District lookup matching selected State. |
| 8  | Address                   | Block / Tehsil                    | Important | Yes             | No        | Yes          | Sub-district division. Optional based on State. |
| 9  | Address                   | PIN Code                          | Core      | Yes             | Yes       | No           | 6-digit postal code. Validated. |
| 10 | Address                   | Country                           | Core      | No              | Yes       | No           | Country name. Defaults to India. |
| 11 | Classification            | School Type                       | Core      | Yes             | Yes       | No           | Gender enrollment profile (Boys, Girls, Co-educational). |
| 12 | Classification            | School Levels Offered             | Core      | Yes             | Yes       | No           | Multi-select list: Pre-Primary, Primary, Upper Primary, Secondary, Senior Secondary. Configurable term options. |
| 13 | Classification            | Management Type                   | Core      | Yes             | Yes       | No           | Management structure (Govt, Govt Aided, Private Unaided, etc.). |
| 14 | Classification            | Medium of Instruction             | Core      | Yes             | Yes       | No           | Primary instruction language (English, Hindi, Regional, etc.). |
| 15 | Classification            | Residential Status                | Important | No              | Yes       | No           | Boarding classification (Day, Residential, Day-cum-Residential). |
| 16 | Board & Recognition       | Board Affiliation Ref             | Core      | Yes             | Yes       | No           | Dropdown mapping to active Board Master (CBSE, ICSE, etc.). |
| 17 | Board & Recognition       | Board Affiliation Number          | Core      | Yes             | Yes       | Yes          | Affiliation number issued by the board (conditional on board). |
| 18 | Board & Recognition       | Recognition / Registration Number | Core      | Yes             | No        | Yes          | State Education Dept registration code (conditional on type). |
| 19 | Board & Recognition       | Affiliation Validity Dates        | Important | Yes             | No        | Yes          | Start and end dates of affiliation (conditional on status). |
| 20 | Institutional Identifiers | UDISE+ School Code                | Important | Yes             | No        | Yes          | 11-digit Unified District Information System code (conditional on assignment). |
| 21 | Institutional Identifiers | State Board School Code           | Important | Yes             | No        | Yes          | Code issued by state board (conditional on board affiliation). |
| 22 | Official Contact          | Principal / Head Employee Ref     | Core      | No              | No        | Yes          | Pointer to existing HR employee record. Fallback to free-text name if HR module is inactive. |
| 23 | Official Contact          | Principal Contact Details         | Core      | No              | No        | Yes          | Official email and phone of the institutional head (conditional). |
| 24 | Official Contact          | Official School Email             | Core      | No              | Yes       | No           | Primary email address for general school communication. |
| 25 | Official Contact          | Official School Phone(s)          | Core      | No              | Yes       | No           | Primary and alternate landline/mobile contact numbers. |
| 26 | Official Contact          | Emergency Contact                 | Important | No              | No        | No           | Dedicated emergency contact numbers. |
| 27 | Branding                  | Primary Logo Upload               | Core      | No              | Yes       | No           | High-res branding logo. Validates format (PNG/JPEG) and size (max 2MB). Recommended ratio: 1:1. |
| 28 | Branding                  | Document Logo                     | Core      | No              | Yes       | No           | Optimized logo for black-and-white/PDF rendering. Validates format/size. Recommended ratio: 1:1. |
| 29 | Branding                  | Document Header Text              | Important | No              | No        | No           | Global text block printed on receipt, card, and report headers. |
| 30 | Branding                  | Document Footer Text              | Important | No              | No        | No           | Global text block/disclaimer printed at the bottom of templates. |

#### 1.1.3 Feature Clarifications
* **Recognition / Registration Number**: Set to `Required: No, Conditional: Yes` because specific school types or pre-primary institutes may follow alternative local registration flows.
* **Principal/Head HR Association**: Links to an employee ID inside `HR & Payroll` without duplicating data. If HR integration is inactive, this accepts direct textual info.
* **School Levels Offered**: Configured as a multi-select field (Pre-Primary, Primary, Upper Primary, Secondary, Senior Secondary) rather than a single dropdown to accommodate comprehensive institutions.
* **UDISE+ School Code**: Optional/Conditional so that schools in the setup phase can establish their ERP system before a government UDISE+ code is generated.
* **Logo Validation**: Validators target file extension formats (PNG, JPG, JPEG) and file size (max 2MB) with recommended aspect ratio warnings, rather than enforcing rigid pixel heights/widths that break responsive styling.
* **Document Header/Footer**: Universal variables stored globally in Administration settings, which can be dynamically overlayed onto receipts, certificates, invoice drafts, and report cards generated by other modules.

#### 1.1.4 Feature Scope Boundary
* **Feature #1 Owns**: Core institutional metadata, school-wide address hierarchy, categorization metadata, registration/board numbers, Principal pointers, gateway configs, and branding assets.
* **Feature #1 Does NOT Own**: Board master templates/rules (Feature #2), class/subject mappings (Feature #3), employee databases (HR), RTE seat admissions (Admission), or government compliance reporting pipelines (Reports).

### 1.2 Branch / Campus Management (Advanced)
* Branch/campus configuration
* Campus-specific details & settings
* Multi-campus structure tracking (deferred for SaaS/enterprise scope, not active in single-school deployment)

---

---

## 2. Board & Academic Framework

### 2.1 Feature Tree
```text
Board & Academic Framework
├── Board Master Configuration (Dictionary of Boards)
├── School Board Affiliation (One active Board per Branch + Session)
├── Academic Framework & Track Mapping
├── Grading & Assessment Framework
├── Pass Criteria Configuration (Subject & Overall targets)
├── Configurable Grace Marks Policy (Subject/Overall options, Configurable Rounding)
├── Subject Requirement Framework
├── Language & Medium Master Configuration
├── Framework Versioning & Reusability Mapping
└── Role-based Approval & Activation Workflow (Maker-Checker, Alternate Approver fallback)
```

### 2.2 Feature Master Table

| # | Category | Feature / Field | Priority | Required | Conditional | Configurable | India Specific | Role Controlled | Approval | Versioned |
| :-: | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | Board Master | Board Name & Type | Core | Yes | No | Yes | Yes | Yes | Yes | No |
| 2 | Board Affiliation | Active Board Link | Core | Yes | No | Yes | Yes | Yes | Yes | Yes |
| 3 | Academic Framework | K-12 divisions / Tracks | Core | Yes | No | Yes | Yes | Yes | Yes | Yes |
| 4 | Grading Framework | Grading Scheme Reference | Core | Yes | No | Yes | Yes | Yes | Yes | Yes |
| 5 | Pass Criteria | Min Subject/Overall Pass % | Core | Yes | No | Yes | Yes | Yes | Yes | Yes |
| 6 | Grace Marks Policy | Subject/Overall Grace Rules | Important | No | Yes | Yes | Yes | Yes | Yes | Yes |
| 7 | Grace Marks Policy | Rounding Policy | Important | Yes | No | Yes | No | Yes | Yes | Yes |
| 8 | Subject Req. | Min Mandatory Subjects | Core | Yes | No | Yes | Yes | Yes | Yes | Yes |
| 9 | Versioning | Reusable Session Mappings | Core | Yes | No | Yes | No | Yes | Yes | Yes |
| 10| Config Settings | Configurable Approver Designation | Core | Yes | No | Yes | No | Yes | No | No |
| 11| Config Settings | Configurable Alternate Approver | Core | Yes | No | Yes | No | Yes | No | No |

### 2.3 Role-based Access & Permission Matrix
* **View Board Framework**: Restricted to authorized academic/administrative roles (Default: `Academic Admin`, `Principal/Head`, `Examination/Academic In-charge`). Teachers do not have access.
* **Create/Edit Draft (Maker)**: Mapped to authorized Maker roles (Default: `Academic Admin` / `authorized Administration role`).
* **Approve/Publish (Approver)**: Mapped to primary configured approver designation (Default: `Principal / Head of Institution`).
* **Maker-Checker Block**: System enforces that the creator of a draft cannot be its approver/publisher.
* **Alternate Approver Fallback**: Mapped to configured Alternate Approver Designation if primary is unavailable. Super Admin bypass is disabled.

### 2.4 Approval & Maker-Checker Workflow
1. **Creation**: Maker drafts a framework or updates a version.
2. **Submission**: Draft is locked and submitted for review.
3. **Verification**: Configured primary Approver Designation (or configured Alternate Approver if primary is unavailable) reviews the draft.
4. **Activation**: Approver publishes the framework. The system verifies the creator is not the publisher (Maker-Checker segregation of duties).
5. **No Super Admin Bypass**: Super Admin cannot bypass academic approval gating.

### 2.5 Grace Marks Policy & Rounding Modes
* **Preservation**: Raw marks are always preserved.
* **Rounding Options**: Configurable rounding rules within the framework:
  * *No rounding*
  * *Round before grace calculation*
  * *Round after grace calculation*
* **Rounding Precision**: Determined by the specific board/framework configuration.

### 2.6 Versioning & Session Reusability Rules
* **Reusability**: A single framework can be mapped to multiple Academic Sessions.
* **Immutability**: Once a version is active in any session (or has associated exam data), it becomes immutable.
* **Modification**: Adjustments spawn a new version tag (e.g. `v1` ➔ `v2`).
* **History**: Historical academic reports remain linked to their original version.

### 2.7 India/State Board Configurability
* Generic mechanism supporting CBSE, ICSE/CISCE, and multiple State Boards (e.g. Chhattisgarh State Board) without hardcoding board-specific logic in the source code.

### 2.8 Cross-Module Boundaries & Dependencies
* **Feature #1 Boundary**: Geographic masters and branding elements belong to Feature #1.
* **Feature #3 Boundary**: The actual operational tables (`classes`, `sections`, `subjects`, and `class_subject_maps`) belong to Feature #3. Feature #2 only establishes the rules (e.g., "A class must have at least 5 subjects mapping to CBSE tracks").
* **Examination Dependency**: The exam processing pipeline consumes Feature #2 rules for report card outcomes.

### 2.9 Business Decisions Pending
* **None** (All functional requirements and business decisions resolved).

---

## 3. Academic Master Setup

### 3.1 Academic Session (Core)
* Academic Session Name (e.g., "2026-27")
* Session Start and End Dates
* Current Active Session status resolver

### 3.2 Class (Core)
* Class / Grade Master (e.g., Class 1, Class 10)
* Sequence/order for ranking and promotion
* Board alignment per class

### 3.3 Section (Core)
* Section Master (e.g., Section A, Section B)
* Section capacity boundaries

### 3.4 Subject (Core)
* Subject Master (Subject name, Code, Category - Core/Elective/Co-curricular)

### 3.5 Class-Subject Mapping (Core)
* Allocating specific subjects to classes

### 3.6 Teacher-Class-Subject Mapping (Core)
* Allocating specific teachers to classes and subjects
* **Boundary Note**: Administration only manages master mapping. Timetable scheduling, teaching hours calculation, substitution tasks, and workloads belong to the Academics and Timetable modules.

---

## 4. Organizational Setup

### 4.1 Department & Designation Master (Core)
* Office/teaching department setup (e.g., Science, Admin)
* Work designation structure (e.g., TGT, PGT, Coordinator, Accountant)

### 4.2 House Management (Important / Configurable)
* House setup (e.g., Red House, Blue House)
* House details and operational status (optional depending on school policy)

---

## 5. User & Access Administration

### 5.1 User Account & Status Control (Core)
* User account creation and role linking
* Account status controls (Activate, Lock, Deactivate)
* Account search and status trackers

### 5.2 Roles & Permissions Manager (Core)
* Role configuration (System vs. Custom Roles)
* Visual permission grid mapping permissions (View, Create, Edit, Delete) per module

### 5.3 User Search / Filters & Profile Inspectors (Core)
* Search users by name/username
* Filter user tables by status/role
* Inspect user details and profile links

---

## 6. Calendar & Holidays

### 6.1 Holiday Master (Core)
* Gazetted, Restricted, and School Holidays setup
* Calendar dates, recurrences, and descriptions

### 6.2 Working Days & Weekend Rules (Core)
* Configurable school week days (e.g., Mon-Fri vs. Mon-Sat)
* Weekly offs and school-specific working hours templates

---

## 7. Global / System Settings

### 7.1 Regional Parameters Configuration (Core)
* Timezone settings
* Date/Time formats
* Local currency mapping (e.g., Indian Rupee `₹`)

### 7.2 Module Configuration Settings (Core)
Centralized parameters editor, including:
* Attendance parameters (edit windows, warning limits)
* Fee parameters (late fee rates, reminder timing)
* Library parameters (max book limits, fine rates per day)
* Timetable parameters (load ceilings)
* Leave parameters (allocation structures)

---

## 8. Master Data

### 8.1 Social Demographics & RTE Quotas Master (Core - India Specific)
* Social Category Lookups (General, OBC, SC, ST)
* Religion Lookups
* RTE Quota applicability, entry class triggers, and target quotas (e.g. 25%)
* **Boundary Note**: Actual RTE admission processing belongs to the Admission module.

---

## 9. Numbering & Sequences

### 9.1 Document Numbering Configurator (Important)
Custom numbering series containing prefix, suffix, start index, and reset scopes:
* **Academic Session Based**: Admission Application numbers, Student Enrollment IDs
* **Financial Year Based**: Invoice numbers, Payment receipt numbers
* **Institution Based**: Staff/Employee IDs
* **Document Based**: Transfer Certificate numbers, Report Card serial numbers

---

## 10. Document Configuration

### 10.1 Required Document Checklist Setup (Important)
* Configurable checklist requirements mapping before student admission or staff onboarding can proceed.

---

## 11. Notification Configuration

### 11.1 Notification Gateway Settings (Core)
* Global SMS/Email gateway settings (e.g., MSG91 api_key, sender_id)
* System-wide notification channel toggles

### 11.2 Global Template Editor & Variable Configuration (Important)
* SMS and Email body editors with support for dynamic placeholders (e.g. `{{student_name}}`)
* Event triggers mapping

---

## 12. System Administration

### 12.1 Database-Level Audit Trail Browser (Important)
* UI search, filter, and trace logs showing database changes, users, timestamp, and query diffs.

### 12.2 Session & Token Control Manager (Core)
* Active session list, token expiry controls, and instant force-logout trigger.

### 12.3 System Backup Trigger (Important)
* Trigger manual database dump exports.

---

## Cross-Module Configuration References

| Administration Configuration | Used By Module |
| ----------------------------- | -------------- |
| **Academic Session** | Admission, SIS, Attendance, Examination, Fees |
| **Board / Academic Framework** | Academics, Examination |
| **Class/Section/Subject Mapping** | Academics, Timetable, SIS |
| **Teacher-Class-Subject Mapping** | Academics, Timetable |
| **Holiday/Working Day Rules** | Attendance, HR & Payroll |
| **Required Documents** | Admission, SIS, HR & Payroll |
| **Module Parameters** | Respective modules |
| **Notification Templates** | Communication |
| **Geography / Demographic Masters** | SIS, HR & Payroll |
| **Numbering Rules** | Admission, SIS, Fees, HR, Certificates |

## Final Priority Summary
* **Core**: 24 features
* **Important**: 7 features
* **Advanced**: 1 feature
* **Total unique features**: 32
* **India-Specific**: 9 items integrated across the features.
