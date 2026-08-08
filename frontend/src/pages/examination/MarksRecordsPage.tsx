import { useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { useSubjects } from "../../lib/academic";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";

interface MarksRecord {
  marks_record_id: number;
  exam_id: number;
  student_id: number;
  subject_id: number;
  marks_obtained: number;
  max_marks: number;
  is_flagged: boolean;
  is_locked: boolean;
}

interface FormState {
  student_id: string;
  subject_id: string;
  marks_obtained: string;
  max_marks: string;
}

const EMPTY_FORM: FormState = { student_id: "", subject_id: "", marks_obtained: "", max_marks: "" };

export default function MarksRecordsPage() {
  const { subjects } = useSubjects();
  const [examIdInput, setExamIdInput] = useState("");
  const [examId, setExamId] = useState<number | null>(null);
  const [records, setRecords] = useState<MarksRecord[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function subjectName(id: number): string {
    return subjects.find((s) => s.subject_id === id)?.subject_name ?? `Subject #${id}`;
  }

  function reload(forExamId: number) {
    setIsLoading(true);
    setError(null);
    api
      .get<{ data: MarksRecord[] }>("/examination/marks-records", { params: { exam_id: forExamId } })
      .then((response) => setRecords(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  function handleSearch(event: FormEvent) {
    event.preventDefault();
    const id = Number(examIdInput);
    if (id > 0) {
      setExamId(id);
      reload(id);
    }
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    if (examId === null) return;
    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.post("/examination/marks-records", {
        exam_id: examId,
        student_id: Number(form.student_id),
        subject_id: Number(form.subject_id),
        marks_obtained: Number(form.marks_obtained),
        max_marks: Number(form.max_marks),
      });
      setIsCreating(false);
      reload(examId);
    } catch (err) {
      setFormError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleLock(record: MarksRecord) {
    try {
      await api.post(`/examination/marks-records/${record.marks_record_id}/lock`);
      if (examId) reload(examId);
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  async function handleReevaluate(record: MarksRecord) {
    const newMarks = prompt("New marks obtained:", String(record.marks_obtained));
    if (newMarks === null) return;
    const reason = prompt("Reason for re-evaluation:");
    if (!reason) return;
    try {
      await api.post(`/examination/marks-records/${record.marks_record_id}/reevaluate`, {
        marks_obtained: Number(newMarks),
        reason,
      });
      if (examId) reload(examId);
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Marks Records</h2>
        {examId !== null && (
          <button
            type="button"
            onClick={() => {
              setForm(EMPTY_FORM);
              setFormError(null);
              setIsCreating(true);
            }}
            className={primaryButtonClass}
          >
            New Marks Record
          </button>
        )}
      </div>

      <form onSubmit={handleSearch} className="mb-4 flex gap-2">
        <input
          type="number"
          min={1}
          placeholder="Exam ID"
          value={examIdInput}
          onChange={(e) => setExamIdInput(e.target.value)}
          className={`${inputClass} w-40`}
        />
        <button type="submit" className={secondaryButtonClass}>
          Search
        </button>
      </form>

      {message && <p className="mb-3 text-sm text-red-600 dark:text-red-400">{message}</p>}
      {examId === null && <p className="text-sm text-slate-400">Enter an Exam ID to see marks records.</p>}
      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {examId !== null && !isLoading && !error && (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th className="px-4 py-2 font-medium">Student</th>
                <th className="px-4 py-2 font-medium">Subject</th>
                <th className="px-4 py-2 font-medium">Marks</th>
                <th className="px-4 py-2 font-medium">Flagged?</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {records.map((r) => (
                <tr key={r.marks_record_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">#{r.student_id}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{subjectName(r.subject_id)}</td>
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">
                    {r.marks_obtained} / {r.max_marks}
                  </td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">
                    {r.is_flagged ? "Yes (anomaly)" : "No"}
                  </td>
                  <td className="px-4 py-2 text-right">
                    {r.is_locked ? (
                      <button
                        type="button"
                        onClick={() => handleReevaluate(r)}
                        className="text-xs text-amber-700 hover:underline dark:text-amber-400"
                      >
                        Re-evaluate
                      </button>
                    ) : (
                      <button
                        type="button"
                        onClick={() => handleLock(r)}
                        className="text-xs text-slate-600 hover:underline dark:text-slate-400"
                      >
                        Lock
                      </button>
                    )}
                  </td>
                </tr>
              ))}
              {records.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-slate-400">
                    No marks records for this exam.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && examId !== null && (
        <Modal title="New Marks Record" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className={labelClass}>Student ID</label>
              <input
                required
                type="number"
                min={1}
                value={form.student_id}
                onChange={(e) => setForm({ ...form, student_id: e.target.value })}
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>Subject</label>
              <select
                required
                value={form.subject_id}
                onChange={(e) => setForm({ ...form, subject_id: e.target.value })}
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
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className={labelClass}>Marks obtained</label>
                <input
                  required
                  type="number"
                  step="0.01"
                  min={0}
                  value={form.marks_obtained}
                  onChange={(e) => setForm({ ...form, marks_obtained: e.target.value })}
                  className={inputClass}
                />
              </div>
              <div>
                <label className={labelClass}>Max marks</label>
                <input
                  required
                  type="number"
                  step="0.01"
                  min={1}
                  value={form.max_marks}
                  onChange={(e) => setForm({ ...form, max_marks: e.target.value })}
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
