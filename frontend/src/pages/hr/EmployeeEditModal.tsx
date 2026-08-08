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
    <Modal title={`Edit — ${employee.employee_code}`} onClose={onClose}>
      <form onSubmit={handleSave} className="max-h-[70vh] space-y-4 overflow-y-auto pr-1">
        <div>
          <label className={labelClass}>Full name</label>
          <input required value={fullName} onChange={(e) => setFullName(e.target.value)} className={inputClass} />
        </div>
        <div className="grid grid-cols-2 gap-3">
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

        <SalaryStructureEditor components={components} onChange={setComponents} />

        <div className="border-t border-slate-200 pt-4 dark:border-slate-800">
          <label className={labelClass}>Exit date</label>
          {employee.exit_date !== null ? (
            <p className="text-sm text-slate-500 dark:text-slate-400">
              Exited on {employee.exit_date} — this is permanent, no un-exit path exists (ADR-008 §9: no
              settlement/reversal workflow modeled).
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
