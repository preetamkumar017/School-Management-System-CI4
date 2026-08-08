import { useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { useSubjects } from "../../lib/academic";
import { inputClass, labelClass, primaryButtonClass } from "../../components/ui/form";

interface Eligibility {
  subject_teacher_eligibility_id: number;
  employee_id: number;
  subject_id: number;
}

export default function EligibilityPage() {
  const { subjects } = useSubjects();
  const [subjectId, setSubjectId] = useState<number | null>(null);
  const [list, setList] = useState<Eligibility[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [employeeId, setEmployeeId] = useState("");
  const [newSubjectId, setNewSubjectId] = useState("");
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function reload(forSubjectId: number) {
    setIsLoading(true);
    setError(null);
    api
      .get<{ data: Eligibility[] }>("/timetable/subject-teacher-eligibilities", { params: { subject_id: forSubjectId } })
      .then((response) => setList(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  function handleFilterChange(value: string) {
    const id = value ? Number(value) : null;
    setSubjectId(id);
    if (id) reload(id);
    else setList([]);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.post("/timetable/subject-teacher-eligibilities", {
        employee_id: Number(employeeId),
        subject_id: Number(newSubjectId),
      });
      setEmployeeId("");
      if (subjectId === Number(newSubjectId)) reload(subjectId);
    } catch (err) {
      setFormError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="grid gap-8 md:grid-cols-2">
      <div>
        <h2 className="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Subject-Teacher Eligibility</h2>
        <select value={subjectId ?? ""} onChange={(e) => handleFilterChange(e.target.value)} className={`${inputClass} mb-4 w-56`}>
          <option value="">Filter by subject</option>
          {subjects.map((s) => (
            <option key={s.subject_id} value={s.subject_id}>
              {s.subject_name}
            </option>
          ))}
        </select>

        {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}
        {error && (
          <p role="alert" className="text-sm text-red-600 dark:text-red-400">
            {error}
          </p>
        )}
        {subjectId === null && <p className="text-sm text-slate-400">Pick a subject to see eligible teachers.</p>}
        {subjectId !== null && !isLoading && !error && (
          <ul className="space-y-1 text-sm text-slate-600 dark:text-slate-400">
            {list.map((e) => (
              <li key={e.subject_teacher_eligibility_id}>Employee #{e.employee_id}</li>
            ))}
            {list.length === 0 && <li className="text-slate-400">No eligible teachers for this subject yet.</li>}
          </ul>
        )}
      </div>

      <div>
        <h2 className="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Add Eligibility</h2>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className={labelClass}>Employee ID</label>
            <input
              required
              type="number"
              min={1}
              value={employeeId}
              onChange={(e) => setEmployeeId(e.target.value)}
              className={inputClass}
            />
          </div>
          <div>
            <label className={labelClass}>Subject</label>
            <select
              required
              value={newSubjectId}
              onChange={(e) => setNewSubjectId(e.target.value)}
              className={inputClass}
            >
              <option value="" disabled>
                Select subject
              </option>
              {subjects.map((s) => (
                <option key={s.subject_id} value={s.subject_id}>
                  {s.subject_name}
                </option>
              ))}
            </select>
          </div>

          {formError && (
            <p role="alert" className="text-sm text-red-600 dark:text-red-400">
              {formError}
            </p>
          )}

          <button type="submit" disabled={isSubmitting} className={primaryButtonClass}>
            {isSubmitting ? "Saving…" : "Add"}
          </button>
        </form>
      </div>
    </div>
  );
}
