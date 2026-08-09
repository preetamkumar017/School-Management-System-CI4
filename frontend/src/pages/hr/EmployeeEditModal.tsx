import { useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";
import { useDepartments, useDesignations } from "./OrgPage";
import SalaryStructureEditor, { jsonToSalaryComponents, salaryComponentsToJson, type SalaryComponents } from "./SalaryStructureEditor";
import type { Employee } from "./EmployeesPage";

export default function EmployeeEditModal({
  employee,
  onClose,
  onSaved,
}: {
  employee: Employee;
  onClose: () => void;
  onSaved: () => void;
}) {
  const { departments } = useDepartments();
  const { designations } = useDesignations();

  const [fullName, setFullName] = useState(employee.full_name);
  const [departmentId, setDepartmentId] = useState(String(employee.department_id));
  const [designationId, setDesignationId] = useState(String(employee.designation_id));
  const [staffType, setStaffType] = useState(employee.staff_type || "Teaching");
  const [cbseClassification, setCbseClassification] = useState(employee.cbse_classification || "None");
  const [cbseTeacherCode, setCbseTeacherCode] = useState(employee.cbse_teacher_code || "");
  const [qualification, setQualification] = useState(employee.qualification || "");
  const [experienceYears, setExperienceYears] = useState(String(employee.experience_years ?? ""));
  const [emergencyContactName, setEmergencyContactName] = useState(employee.emergency_contact_name || "");
  const [emergencyContactPhone, setEmergencyContactPhone] = useState(employee.emergency_contact_phone || "");
  // documents stored as array of {name, url}
  const [documents, setDocuments] = useState<Array<{ name: string; url: string }>>(
    (employee.documents_json || []).map((d: any) => ({ name: d.name || "", url: d.url || "" }))
  );
  const [aadhaarNumber, setAadhaarNumber] = useState(employee.aadhaar_number || "");
  const [panNumber, setPanNumber] = useState(employee.pan_number || "");
  const [pfUan, setPfUan] = useState(employee.pf_uan || "");
  const [esiNumber, setEsiNumber] = useState(employee.esi_number || "");
  const [bankName, setBankName] = useState(employee.bank_name || "");
  const [bankAccountNumber, setBankAccountNumber] = useState(employee.bank_account_number || "");
  const [bankIfscCode, setBankIfscCode] = useState(employee.bank_ifsc_code || "");
  const [components, setComponents] = useState<SalaryComponents>(jsonToSalaryComponents(employee.salary_structure_json));
  const [exitDate, setExitDate] = useState(employee.exit_date ?? "");
  const [confirmExit, setConfirmExit] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  const isExiting = employee.exit_date === null && exitDate !== "";

  async function handleSave(event: FormEvent) {
    event.preventDefault();
    if (isExiting && !confirmExit) {
      setError("Confirm the exit checkbox below — this deactivates the employee's login immediately (BR-HR-002).");
      return;
    }
    setError(null);
    setIsSaving(true);
    try {
      await api.patch(`/hr-payroll/employees/${employee.employee_id}`, {
        full_name: fullName,
        department_id: Number(departmentId),
        designation_id: Number(designationId),
        staff_type: staffType,
        cbse_classification: cbseClassification,
        cbse_teacher_code: cbseTeacherCode || null,
        qualification: qualification || null,
        experience_years: experienceYears !== "" ? parseFloat(experienceYears) : null,
        emergency_contact_name: emergencyContactName || null,
        emergency_contact_phone: emergencyContactPhone || null,
        documents_json: documents.filter(d => d.name.trim() !== ""),
        aadhaar_number: aadhaarNumber || null,
        pan_number: panNumber || null,
        pf_uan: pfUan || null,
        esi_number: esiNumber || null,
        bank_name: bankName || null,
        bank_account_number: bankAccountNumber || null,
        bank_ifsc_code: bankIfscCode || null,
        salary_structure_json: salaryComponentsToJson(components),
        exit_date: exitDate || null,
      });
      onSaved();
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsSaving(false);
    }
  }

  return (
    <Modal title={`Edit Employee — ${employee.employee_code}`} onClose={onClose} maxWidth="3xl">
      <form onSubmit={handleSave} className="max-h-[75vh] space-y-4 overflow-y-auto pr-1">
        <div>
          <label className={labelClass}>Full name</label>
          <input required value={fullName} onChange={(e) => setFullName(e.target.value)} className={inputClass} />
        </div>

        <div className="grid grid-cols-5 gap-3">
          <div>
            <label className={labelClass}>Staff Type</label>
            <select required value={staffType} onChange={(e) => setStaffType(e.target.value)} className={inputClass}>
              <option value="Teaching">Teaching</option>
              <option value="NonTeaching">Non-Teaching</option>
              <option value="Support">Support</option>
              <option value="Administrative">Administrative</option>
            </select>
          </div>
          <div>
            <label className={labelClass}>CBSE Category</label>
            <select required value={cbseClassification} onChange={(e) => setCbseClassification(e.target.value as any)} className={inputClass}>
              <option value="None">None</option>
              <option value="PRT">PRT (Primary)</option>
              <option value="TGT">TGT (Trained Grad)</option>
              <option value="PGT">PGT (Post Grad)</option>
            </select>
          </div>
          <div>
            <label className={labelClass}>CBSE Code</label>
            <input
              placeholder="e.g. T-9921"
              value={cbseTeacherCode}
              onChange={(e) => setCbseTeacherCode(e.target.value)}
              className={inputClass}
            />
          </div>
          <div>
            <label className={labelClass}>Department</label>
            <select required value={departmentId} onChange={(e) => setDepartmentId(e.target.value)} className={inputClass}>
              {departments.map((d) => (
                <option key={d.department_id} value={d.department_id}>
                  {d.department_name}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className={labelClass}>Designation</label>
            <select required value={designationId} onChange={(e) => setDesignationId(e.target.value)} className={inputClass}>
              {designations.map((d) => (
                <option key={d.designation_id} value={d.designation_id}>
                  {d.designation_name}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div>
          <label className={labelClass}>Qualification</label>
          <input
            placeholder="e.g. M.Sc Physics, B.Ed"
            value={qualification}
            onChange={(e) => setQualification(e.target.value)}
            className={inputClass}
          />
        </div>

        {/* Experience & Emergency Contact */}
        <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-3 dark:border-slate-800 dark:bg-slate-900/50">
          <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-500">Experience &amp; Emergency Contact</h4>
          <div className="grid grid-cols-3 gap-3">
            <div>
              <label className={labelClass}>Experience (Years)</label>
              <input
                type="number"
                min="0"
                max="60"
                step="0.5"
                placeholder="e.g. 5.5"
                value={experienceYears}
                onChange={(e) => setExperienceYears(e.target.value)}
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>Emergency Contact Name</label>
              <input
                placeholder="Contact person name"
                value={emergencyContactName}
                onChange={(e) => setEmergencyContactName(e.target.value)}
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>Emergency Contact Phone</label>
              <input
                placeholder="+91 9XXXXXXXXX"
                value={emergencyContactPhone}
                onChange={(e) => setEmergencyContactPhone(e.target.value)}
                className={inputClass}
              />
            </div>
          </div>
        </div>

        {/* Documents */}
        <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-2 dark:border-slate-800 dark:bg-slate-900/50">
          <div className="flex items-center justify-between">
            <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-500">Documents / References</h4>
            <button
              type="button"
              onClick={() => setDocuments([...documents, { name: "", url: "" }])}
              className="text-xs text-indigo-600 hover:underline dark:text-indigo-400 font-semibold"
            >
              + Add Document
            </button>
          </div>
          {documents.length === 0 && (
            <p className="text-xs text-slate-400 italic">No documents added yet.</p>
          )}
          {documents.map((doc, idx) => (
            <div key={idx} className="grid grid-cols-5 gap-2 items-center">
              <div className="col-span-2">
                <input
                  placeholder="Document name (e.g. Degree Certificate)"
                  value={doc.name}
                  onChange={(e) => {
                    const updated = [...documents];
                    updated[idx] = { ...updated[idx], name: e.target.value };
                    setDocuments(updated);
                  }}
                  className={inputClass}
                />
              </div>
              <div className="col-span-2">
                <input
                  placeholder="Link or reference (optional)"
                  value={doc.url}
                  onChange={(e) => {
                    const updated = [...documents];
                    updated[idx] = { ...updated[idx], url: e.target.value };
                    setDocuments(updated);
                  }}
                  className={inputClass}
                />
              </div>
              <button
                type="button"
                onClick={() => setDocuments(documents.filter((_, i) => i !== idx))}
                className="text-xs text-red-500 hover:text-red-700 font-semibold"
              >
                Remove
              </button>
            </div>
          ))}
        </div>

        {/* Indian School KYC & Statutory */}
        <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-3 dark:border-slate-800 dark:bg-slate-900/50">
          <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-500">Statutory & Identity KYC</h4>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className={labelClass}>Aadhaar Number</label>
              <input
                maxLength={12}
                placeholder="12 digit Aadhaar"
                value={aadhaarNumber}
                onChange={(e) => setAadhaarNumber(e.target.value)}
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>PAN Number</label>
              <input
                maxLength={10}
                placeholder="10 char PAN"
                value={panNumber}
                onChange={(e) => setPanNumber(e.target.value)}
                className={inputClass}
              />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className={labelClass}>PF UAN</label>
              <input
                placeholder="Universal Account Number"
                value={pfUan}
                onChange={(e) => setPfUan(e.target.value)}
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>ESI Number</label>
              <input
                placeholder="ESI Insurance Number"
                value={esiNumber}
                onChange={(e) => setEsiNumber(e.target.value)}
                className={inputClass}
              />
            </div>
          </div>
        </div>

        {/* Bank Details */}
        <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-3 dark:border-slate-800 dark:bg-slate-900/50">
          <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-500">Bank Account Details</h4>
          <div className="grid grid-cols-3 gap-3">
            <div>
              <label className={labelClass}>Bank Name</label>
              <input
                placeholder="e.g. State Bank of India"
                value={bankName}
                onChange={(e) => setBankName(e.target.value)}
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>Account Number</label>
              <input
                placeholder="Account Number"
                value={bankAccountNumber}
                onChange={(e) => setBankAccountNumber(e.target.value)}
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>IFSC Code</label>
              <input
                placeholder="IFSC Code"
                value={bankIfscCode}
                onChange={(e) => setBankIfscCode(e.target.value)}
                className={inputClass}
              />
            </div>
          </div>
        </div>

        <SalaryStructureEditor components={components} onChange={setComponents} />

        <div className="border-t border-slate-200 pt-4 dark:border-slate-800">
          <label className={labelClass}>Exit date</label>
          {employee.exit_date !== null ? (
            <p className="text-sm text-slate-500 dark:text-slate-400">
              Exited on {employee.exit_date} — this is permanent, no un-exit path exists.
            </p>
          ) : (
            <>
              <input type="date" value={exitDate} onChange={(e) => setExitDate(e.target.value)} className={inputClass} />
              {isExiting && (
                <label className="mt-2 flex items-start gap-2 text-sm text-amber-700 dark:text-amber-400">
                  <input
                    type="checkbox"
                    checked={confirmExit}
                    onChange={(e) => setConfirmExit(e.target.checked)}
                    className="mt-0.5"
                  />
                  <span>
                    I understand this immediately deactivates {employee.full_name}'s login (BR-HR-002) and cannot be
                    undone from this screen.
                  </span>
                </label>
              )}
            </>
          )}
        </div>

        {error && (
          <p role="alert" className="text-sm text-red-600 dark:text-red-400">
            {error}
          </p>
        )}

        <div className="flex justify-end gap-2 pt-2">
          <button type="button" onClick={onClose} className={secondaryButtonClass}>
            Cancel
          </button>
          <button type="submit" disabled={isSaving} className={primaryButtonClass}>
            {isSaving ? "Saving…" : "Save"}
          </button>
        </div>
      </form>
    </Modal>
  );
}
