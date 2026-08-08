import { useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";

interface Substitution {
  substitution_id: number;
  timetable_entry_id: number;
  absent_employee_id: number;
  substitute_employee_id: number | null;
  substitution_date: string;
  status: "ASSIGNED" | "UNSUPERVISED";
}

export default function SubstitutionsPage() {
  const [entryId, setEntryId] = useState("");
  const [date, setDate] = useState("");
  const [substituteEmployeeId, setSubstituteEmployeeId] = useState("");
  const [result, setResult] = useState<Substitution | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const [eligibleEntryId, setEligibleEntryId] = useState("");
  const [eligibleList, setEligibleList] = useState<number[] | null>(null);
  const [eligibleError, setEligibleError] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);
    try {
      const response = await api.post<{ data: Substitution }>("/timetable/substitutions", {
        timetable_entry_id: Number(entryId),
        substitution_date: date,
        substitute_employee_id: substituteEmployeeId ? Number(substituteEmployeeId) : null,
      });
      setResult(response.data.data);
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleCheckEligible(event: FormEvent) {
    event.preventDefault();
    setEligibleError(null);
    setEligibleList(null);
    try {
      const response = await api.get<{ data: number[] }>(
        `/timetable/entries/${eligibleEntryId}/eligible-substitutes`,
      );
      setEligibleList(response.data.data);
    } catch (err) {
      setEligibleError(apiErrorMessage(err));
    }
  }

  return (
    <div className="grid gap-8 md:grid-cols-2">
      <div>
        <h2 className="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">New Substitution</h2>
        <p className="mb-4 text-sm text-slate-400">
          BR-TT-004/FR-16: if no eligible substitute is supplied and the absent teacher isn't recorded as absent that
          date, this is rejected. With no eligible substitute at all, the period is marked Unsupervised, not
          rejected.
        </p>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className={labelClass}>Timetable Entry ID</label>
            <input
              required
              type="number"
              min={1}
              value={entryId}
              onChange={(e) => setEntryId(e.target.value)}
              className={inputClass}
            />
          </div>
          <div>
            <label className={labelClass}>Substitution date</label>
            <input required type="date" value={date} onChange={(e) => setDate(e.target.value)} className={inputClass} />
          </div>
          <div>
            <label className={labelClass}>Substitute Employee ID (optional — auto-assigned if eligible)</label>
            <input
              type="number"
              min={1}
              value={substituteEmployeeId}
              onChange={(e) => setSubstituteEmployeeId(e.target.value)}
              className={inputClass}
            />
          </div>

          {error && (
            <p role="alert" className="text-sm text-red-600 dark:text-red-400">
              {error}
            </p>
          )}

          <button type="submit" disabled={isSubmitting} className={primaryButtonClass}>
            {isSubmitting ? "Creating…" : "Create Substitution"}
          </button>
        </form>

        {result && (
          <div className="mt-4 rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-950">
            <p className="text-slate-900 dark:text-slate-100">
              Status: <span className="font-medium">{result.status}</span>
            </p>
            <p className="text-slate-500 dark:text-slate-400">
              {result.substitute_employee_id
                ? `Substitute: Employee #${result.substitute_employee_id}`
                : "No substitute assigned — period is Unsupervised."}
            </p>
          </div>
        )}
      </div>

      <div>
        <h2 className="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Check Eligible Substitutes</h2>
        <form onSubmit={handleCheckEligible} className="mb-4 flex gap-2">
          <input
            type="number"
            min={1}
            placeholder="Timetable Entry ID"
            value={eligibleEntryId}
            onChange={(e) => setEligibleEntryId(e.target.value)}
            className={inputClass}
          />
          <button type="submit" className={secondaryButtonClass}>
            Check
          </button>
        </form>

        {eligibleError && (
          <p role="alert" className="text-sm text-red-600 dark:text-red-400">
            {eligibleError}
          </p>
        )}

        {eligibleList && (
          <ul className="space-y-1 text-sm text-slate-600 dark:text-slate-400">
            {eligibleList.map((employeeId) => (
              <li key={employeeId}>Employee #{employeeId}</li>
            ))}
            {eligibleList.length === 0 && <li className="text-slate-400">No eligible substitutes found.</li>}
          </ul>
        )}
      </div>
    </div>
  );
}
