import { useEffect, useMemo, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";
import { useDepartments, useDesignations } from "./OrgPage";
import EmployeeEditModal from "./EmployeeEditModal";
import SalaryStructureEditor, { jsonToSalaryComponents, salaryComponentsToJson, type SalaryComponents } from "./SalaryStructureEditor";

export interface Employee {
  employee_id: number;
  employee_code: string;
  full_name: string;
  department_id: number;
  designation_id: number;
  staff_type?: string;
  qualification?: string;
  aadhaar_number?: string;
  pan_number?: string;
  pf_uan?: string;
  esi_number?: string;
  bank_name?: string;
  bank_account_number?: string;
  bank_ifsc_code?: string;
  joining_date: string;
  probation_end_date?: string;
  confirmation_date?: string;
  exit_date: string | null;
  salary_structure_json: Record<string, number>;
  status: "Active" | "Exited";
}

interface FormState {
  employee_code: string;
  full_name: string;
  department_id: string;
  designation_id: string;
  staff_type: string;
  qualification: string;
  aadhaar_number: string;
  pan_number: string;
  pf_uan: string;
  esi_number: string;
  bank_name: string;
  bank_account_number: string;
  bank_ifsc_code: string;
  joining_date: string;
}

const EMPTY_FORM: FormState = {
  employee_code: "",
  full_name: "",
  department_id: "",
  designation_id: "",
  staff_type: "Teaching",
  qualification: "",
  aadhaar_number: "",
  pan_number: "",
  pf_uan: "",
  esi_number: "",
  bank_name: "",
  bank_account_number: "",
  bank_ifsc_code: "",
  joining_date: "",
};

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

  const [statusFilter, setStatusFilter] = useState<"All" | "Active" | "Exited">("Active");
  const [search, setSearch] = useState("");
  const [editing, setEditing] = useState<Employee | null>(null);

  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [components, setComponents] = useState<SalaryComponents>(jsonToSalaryComponents({}));
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function departmentName(id: number): string {
    return departments.find((d) => d.department_id === id)?.department_name ?? `Dept #${id}`;
  }
  function designationName(id: number): string {
    return designations.find((d) => d.designation_id === id)?.designation_name ?? `Desig #${id}`;
  }

  const filtered = useMemo(() => {
    return employees.filter((e) => {
      if (statusFilter !== "All" && e.status !== statusFilter) return false;
      if (search.trim()) {
        const q = search.toLowerCase();
        return e.full_name.toLowerCase().includes(q) || e.employee_code.toLowerCase().includes(q);
      }
      return true;
    });
  }, [employees, statusFilter, search]);

  function openCreate() {
    setForm(EMPTY_FORM);
    setComponents(jsonToSalaryComponents({}));
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
        staff_type: form.staff_type,
        qualification: form.qualification || null,
        aadhaar_number: form.aadhaar_number || null,
        pan_number: form.pan_number || null,
        pf_uan: form.pf_uan || null,
        esi_number: form.esi_number || null,
        bank_name: form.bank_name || null,
        bank_account_number: form.bank_account_number || null,
        bank_ifsc_code: form.bank_ifsc_code || null,
        joining_date: form.joining_date,
        salary_structure_json: salaryComponentsToJson(components),
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

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <input
          placeholder="Search name or code…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className={`${inputClass} w-56`}
        />
        <div className="flex gap-1">
          {(["Active", "Exited", "All"] as const).map((s) => (
            <button
              key={s}
              type="button"
              onClick={() => setStatusFilter(s)}
              className={`rounded-md px-3 py-1.5 text-sm ${
                statusFilter === s
                  ? "bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900"
                  : "border border-slate-300 text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-900"
              }`}
            >
              {s}
            </button>
          ))}
        </div>
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
                <th className="px-4 py-2 font-medium">Type</th>
                <th className="px-4 py-2 font-medium">Department</th>
                <th className="px-4 py-2 font-medium">Designation</th>
                <th className="px-4 py-2 font-medium">Gross</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {filtered.map((e) => {
                const gross = Object.values(e.salary_structure_json).reduce((sum, v) => sum + Number(v), 0);
                return (
                  <tr key={e.employee_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                    <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{e.employee_code}</td>
                    <td className="px-4 py-2 text-slate-900 dark:text-slate-100 font-medium">{e.full_name}</td>
                    <td className="px-4 py-2">
                      <span className="rounded bg-blue-100 px-2 py-0.5 text-xs text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                        {e.staff_type || "Teaching"}
                      </span>
                    </td>
                    <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{departmentName(e.department_id)}</td>
                    <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{designationName(e.designation_id)}</td>
                    <td className="px-4 py-2 text-slate-500 dark:text-slate-400">₹{gross.toLocaleString()}</td>
                    <td className="px-4 py-2">
                      <span
                        className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                          e.status === "Active"
                            ? "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400"
                            : "bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-400"
                        }`}
                      >
                        {e.status}
                      </span>
                    </td>
                    <td className="px-4 py-2 text-right">
                      <button
                        type="button"
                        onClick={() => setEditing(e)}
                        className="text-xs text-slate-600 hover:underline dark:text-slate-400"
                      >
                        Edit
                      </button>
                    </td>
                  </tr>
                );
              })}
              {filtered.length === 0 && (
                <tr>
                  <td colSpan={8} className="px-4 py-6 text-center text-slate-400">
                    No employees match.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && (
        <Modal title="New Employee" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleSubmit} className="max-h-[75vh] space-y-4 overflow-y-auto pr-1">
            <div className="grid grid-cols-2 gap-3">
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
            </div>

            <div className="grid grid-cols-3 gap-3">
              <div>
                <label className={labelClass}>Staff Type</label>
                <select
                  required
                  value={form.staff_type}
                  onChange={(e) => setForm({ ...form, staff_type: e.target.value })}
                  className={inputClass}
                >
                  <option value="Teaching">Teaching</option>
                  <option value="NonTeaching">Non-Teaching</option>
                  <option value="Support">Support</option>
                  <option value="Administrative">Administrative</option>
                </select>
              </div>
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
                <label className={labelClass}>Qualification</label>
                <input
                  placeholder="e.g. M.Sc Physics, B.Ed"
                  value={form.qualification}
                  onChange={(e) => setForm({ ...form, qualification: e.target.value })}
                  className={inputClass}
                />
              </div>
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
                    value={form.aadhaar_number}
                    onChange={(e) => setForm({ ...form, aadhaar_number: e.target.value })}
                    className={inputClass}
                  />
                </div>
                <div>
                  <label className={labelClass}>PAN Number</label>
                  <input
                    maxLength={10}
                    placeholder="10 char PAN"
                    value={form.pan_number}
                    onChange={(e) => setForm({ ...form, pan_number: e.target.value })}
                    className={inputClass}
                  />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className={labelClass}>PF UAN</label>
                  <input
                    placeholder="Universal Account Number"
                    value={form.pf_uan}
                    onChange={(e) => setForm({ ...form, pf_uan: e.target.value })}
                    className={inputClass}
                  />
                </div>
                <div>
                  <label className={labelClass}>ESI Number</label>
                  <input
                    placeholder="ESI Insurance Number"
                    value={form.esi_number}
                    onChange={(e) => setForm({ ...form, esi_number: e.target.value })}
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
                    value={form.bank_name}
                    onChange={(e) => setForm({ ...form, bank_name: e.target.value })}
                    className={inputClass}
                  />
                </div>
                <div>
                  <label className={labelClass}>Account Number</label>
                  <input
                    placeholder="Account Number"
                    value={form.bank_account_number}
                    onChange={(e) => setForm({ ...form, bank_account_number: e.target.value })}
                    className={inputClass}
                  />
                </div>
                <div>
                  <label className={labelClass}>IFSC Code</label>
                  <input
                    placeholder="IFSC Code"
                    value={form.bank_ifsc_code}
                    onChange={(e) => setForm({ ...form, bank_ifsc_code: e.target.value })}
                    className={inputClass}
                  />
                </div>
              </div>
            </div>

            <SalaryStructureEditor components={components} onChange={setComponents} />

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

      {editing && (
        <EmployeeEditModal
          employee={editing}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null);
            reload();
          }}
        />
      )}
    </div>
  );
}
