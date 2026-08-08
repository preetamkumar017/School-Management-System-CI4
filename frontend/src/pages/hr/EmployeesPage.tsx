import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";
import { useDepartments, useDesignations } from "./OrgPage";

export interface Employee {
  employee_id: number;
  employee_code: string;
  full_name: string;
  department_id: number;
  designation_id: number;
  joining_date: string;
  exit_date: string | null;
  salary_structure_json: Record<string, number>;
  status: "Active" | "Exited";
}

interface FormState {
  employee_code: string;
  full_name: string;
  department_id: string;
  designation_id: string;
  joining_date: string;
  basic: string;
}

const EMPTY_FORM: FormState = { employee_code: "", full_name: "", department_id: "", designation_id: "", joining_date: "", basic: "" };

export function useEmployees() {
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  function reload() {
    setIsLoading(true);
    api
      .get<{ data: Employee[] }>("/hr-payroll/employees")
      .then((response) => setEmployees(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  useEffect(reload, []);

  return { employees, isLoading, error, reload };
}

export default function EmployeesPage() {
  const { departments } = useDepartments();
  const { designations } = useDesignations();
  const { employees, isLoading, error, reload } = useEmployees();

  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function departmentName(id: number): string {
    return departments.find((d) => d.department_id === id)?.department_name ?? `Dept #${id}`;
  }
  function designationName(id: number): string {
    return designations.find((d) => d.designation_id === id)?.designation_name ?? `Desig #${id}`;
  }

  function openCreate() {
    setForm(EMPTY_FORM);
    setFormError(null);
    setIsCreating(true);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.post("/hr-payroll/employees", {
        employee_code: form.employee_code,
        full_name: form.full_name,
        department_id: Number(form.department_id),
        designation_id: Number(form.designation_id),
        joining_date: form.joining_date,
        salary_structure_json: { basic: Number(form.basic) },
      });
      setIsCreating(false);
      reload();
    } catch (err) {
      setFormError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Employees</h2>
        <button type="button" onClick={openCreate} className={primaryButtonClass}>
          New Employee
        </button>
      </div>

      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {!isLoading && !error && (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th className="px-4 py-2 font-medium">Code</th>
                <th className="px-4 py-2 font-medium">Name</th>
                <th className="px-4 py-2 font-medium">Department</th>
                <th className="px-4 py-2 font-medium">Designation</th>
                <th className="px-4 py-2 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {employees.map((e) => (
                <tr key={e.employee_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{e.employee_code}</td>
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{e.full_name}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{departmentName(e.department_id)}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{designationName(e.designation_id)}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{e.status}</td>
                </tr>
              ))}
              {employees.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-slate-400">
                    No employees yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && (
        <Modal title="New Employee" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className={labelClass}>Employee code</label>
              <input
                required
                value={form.employee_code}
                onChange={(e) => setForm({ ...form, employee_code: e.target.value })}
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>Full name</label>
              <input
                required
                value={form.full_name}
                onChange={(e) => setForm({ ...form, full_name: e.target.value })}
                className={inputClass}
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className={labelClass}>Department</label>
                <select
                  required
                  value={form.department_id}
                  onChange={(e) => setForm({ ...form, department_id: e.target.value })}
                  className={inputClass}
                >
                  <option value="" disabled>
                    Select
                  </option>
                  {departments.map((d) => (
                    <option key={d.department_id} value={d.department_id}>
                      {d.department_name}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className={labelClass}>Designation</label>
                <select
                  required
                  value={form.designation_id}
                  onChange={(e) => setForm({ ...form, designation_id: e.target.value })}
                  className={inputClass}
                >
                  <option value="" disabled>
                    Select
                  </option>
                  {designations.map((d) => (
                    <option key={d.designation_id} value={d.designation_id}>
                      {d.designation_name}
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className={labelClass}>Joining date</label>
                <input
                  required
                  type="date"
                  value={form.joining_date}
                  onChange={(e) => setForm({ ...form, joining_date: e.target.value })}
                  className={inputClass}
                />
              </div>
              <div>
                <label className={labelClass}>Basic salary</label>
                <input
                  required
                  type="number"
                  min={0}
                  value={form.basic}
                  onChange={(e) => setForm({ ...form, basic: e.target.value })}
                  className={inputClass}
                />
              </div>
            </div>

            {formError && (
              <p role="alert" className="text-sm text-red-600 dark:text-red-400">
                {formError}
              </p>
            )}

            <div className="flex justify-end gap-2 pt-2">
              <button type="button" onClick={() => setIsCreating(false)} className={secondaryButtonClass}>
                Cancel
              </button>
              <button type="submit" disabled={isSubmitting} className={primaryButtonClass}>
                {isSubmitting ? "Saving…" : "Save"}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
