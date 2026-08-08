import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";

interface Department {
  department_id: number;
  department_name: string;
}
interface Designation {
  designation_id: number;
  designation_name: string;
}

export function useDepartments() {
  const [departments, setDepartments] = useState<Department[]>([]);
  useEffect(() => {
    api
      .get<{ data: Department[] }>("/hr-payroll/departments")
      .then((r) => setDepartments(r.data.data))
      .catch(() => setDepartments([]));
  }, []);
  return { departments };
}

export function useDesignations() {
  const [designations, setDesignations] = useState<Designation[]>([]);
  useEffect(() => {
    api
      .get<{ data: Designation[] }>("/hr-payroll/designations")
      .then((r) => setDesignations(r.data.data))
      .catch(() => setDesignations([]));
  }, []);
  return { designations };
}

export default function OrgPage() {
  const { departments } = useDepartments();
  const { designations } = useDesignations();

  const [isCreatingDept, setIsCreatingDept] = useState(false);
  const [deptName, setDeptName] = useState("");
  const [isCreatingDesig, setIsCreatingDesig] = useState(false);
  const [desigName, setDesigName] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleCreateDept(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);
    try {
      await api.post("/hr-payroll/departments", { department_name: deptName });
      setDeptName("");
      setIsCreatingDept(false);
      window.location.reload();
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleCreateDesig(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);
    try {
      await api.post("/hr-payroll/designations", { designation_name: desigName });
      setDesigName("");
      setIsCreatingDesig(false);
      window.location.reload();
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="grid gap-8 md:grid-cols-2">
      <div>
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Departments</h2>
          <button type="button" onClick={() => setIsCreatingDept(true)} className={primaryButtonClass}>
            New
          </button>
        </div>
        <ul className="space-y-1 text-sm text-slate-600 dark:text-slate-400">
          {departments.map((d) => (
            <li key={d.department_id}>{d.department_name}</li>
          ))}
          {departments.length === 0 && <li className="text-slate-400">No departments yet.</li>}
        </ul>
      </div>

      <div>
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Designations</h2>
          <button type="button" onClick={() => setIsCreatingDesig(true)} className={primaryButtonClass}>
            New
          </button>
        </div>
        <ul className="space-y-1 text-sm text-slate-600 dark:text-slate-400">
          {designations.map((d) => (
            <li key={d.designation_id}>{d.designation_name}</li>
          ))}
          {designations.length === 0 && <li className="text-slate-400">No designations yet.</li>}
        </ul>
      </div>

      {isCreatingDept && (
        <Modal title="New Department" onClose={() => setIsCreatingDept(false)}>
          <form onSubmit={handleCreateDept} className="space-y-4">
            <div>
              <label className={labelClass}>Name</label>
              <input required value={deptName} onChange={(e) => setDeptName(e.target.value)} className={inputClass} />
            </div>
            {error && <p className="text-sm text-red-600 dark:text-red-400">{error}</p>}
            <div className="flex justify-end gap-2">
              <button type="button" onClick={() => setIsCreatingDept(false)} className={secondaryButtonClass}>
                Cancel
              </button>
              <button type="submit" disabled={isSubmitting} className={primaryButtonClass}>
                {isSubmitting ? "Saving…" : "Save"}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {isCreatingDesig && (
        <Modal title="New Designation" onClose={() => setIsCreatingDesig(false)}>
          <form onSubmit={handleCreateDesig} className="space-y-4">
            <div>
              <label className={labelClass}>Name</label>
              <input required value={desigName} onChange={(e) => setDesigName(e.target.value)} className={inputClass} />
            </div>
            {error && <p className="text-sm text-red-600 dark:text-red-400">{error}</p>}
            <div className="flex justify-end gap-2">
              <button type="button" onClick={() => setIsCreatingDesig(false)} className={secondaryButtonClass}>
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
