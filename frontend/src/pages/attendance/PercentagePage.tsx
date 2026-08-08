import { useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { inputClass, labelClass, primaryButtonClass } from "../../components/ui/form";

interface PercentageResult {
  student_id: number;
  from_date: string;
  to_date: string;
  percentage: number;
  is_exam_eligibility_at_risk: boolean;
}

export default function PercentagePage() {
  const [studentId, setStudentId] = useState("");
  const [fromDate, setFromDate] = useState("");
  const [toDate, setToDate] = useState("");
  const [result, setResult] = useState<PercentageResult | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setResult(null);
    setIsLoading(true);
    try {
      const response = await api.get<{ data: PercentageResult }>("/attendance/records/percentage", {
        params: { student_id: Number(studentId), from_date: fromDate, to_date: toDate },
      });
      setResult(response.data.data);
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsLoading(false);
    }
  }

  return (
    <div>
      <h2 className="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Attendance % Lookup</h2>

      <form onSubmit={handleSubmit} className="mb-6 flex flex-wrap items-end gap-3">
        <div>
          <label className={labelClass}>Student ID</label>
          <input
            required
            type="number"
            min={1}
            value={studentId}
            onChange={(e) => setStudentId(e.target.value)}
            className={`${inputClass} w-32`}
          />
        </div>
        <div>
          <label className={labelClass}>From</label>
          <input required type="date" value={fromDate} onChange={(e) => setFromDate(e.target.value)} className={inputClass} />
        </div>
        <div>
          <label className={labelClass}>To</label>
          <input required type="date" value={toDate} onChange={(e) => setToDate(e.target.value)} className={inputClass} />
        </div>
        <button type="submit" disabled={isLoading} className={primaryButtonClass}>
          {isLoading ? "Checking…" : "Check"}
        </button>
      </form>

      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {result && (
        <div className="max-w-sm rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
          <p className="text-xs font-medium text-slate-500 dark:text-slate-400">
            Student #{result.student_id} · {result.from_date} to {result.to_date}
          </p>
          <p className="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{result.percentage}%</p>
          {result.is_exam_eligibility_at_risk && (
            <p className="mt-2 text-sm font-medium text-red-600 dark:text-red-400">
              At risk — below the exam eligibility threshold (BR-ATT-006).
            </p>
          )}
        </div>
      )}
    </div>
  );
}
