import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { useAcademicSessions, useClasses, useGradingSchemes } from "../../lib/academic";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";

export interface Exam {
  exam_id: number;
  exam_name: string;
  class_id: number;
  academic_session_id: number;
  grading_scheme_id: number;
  exam_date: string;
  status: "CONFIGURED" | "ACTIVE" | "LOCKED" | "CLOSED";
}

const STATUS_STYLES: Record<Exam["status"], string> = {
  CONFIGURED: "bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-400",
  ACTIVE: "bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-400",
  LOCKED: "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400",
  CLOSED: "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400",
};

interface FormState {
  exam_name: string;
  class_id: string;
  academic_session_id: string;
  grading_scheme_id: string;
  exam_date: string;
}

const EMPTY_FORM: FormState = { exam_name: "", class_id: "", academic_session_id: "", grading_scheme_id: "", exam_date: "" };

export function useExams(classId: number | null, sessionId: number | null) {
  const [exams, setExams] = useState<Exam[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  function reload() {
    if (classId === null || sessionId === null) {
      setExams([]);
      return;
    }
    setIsLoading(true);
    api
      .get<{ data: Exam[] }>("/examination/exams", { params: { class_id: classId, academic_session_id: sessionId } })
      .then((response) => setExams(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  useEffect(reload, [classId, sessionId]);

  return { exams, isLoading, error, reload };
}

export default function ExamsPage() {
  const { classes } = useClasses();
  const { sessions } = useAcademicSessions();
  const { gradingSchemes } = useGradingSchemes();

  const [classId, setClassId] = useState<number | null>(null);
  const [sessionId, setSessionId] = useState<number | null>(null);
  const { exams, isLoading, error, reload } = useExams(classId, sessionId);

  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [actionMessage, setActionMessage] = useState<string | null>(null);

  function openCreate() {
    setForm({
      exam_name: "",
      class_id: classId ? String(classId) : "",
      academic_session_id: sessionId ? String(sessionId) : "",
      grading_scheme_id: "",
      exam_date: "",
    });
    setFormError(null);
    setIsCreating(true);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.post("/examination/exams", {
        exam_name: form.exam_name,
        class_id: Number(form.class_id),
        academic_session_id: Number(form.academic_session_id),
        grading_scheme_id: Number(form.grading_scheme_id),
        exam_date: form.exam_date,
      });
      setIsCreating(false);
      reload();
    } catch (err) {
      setFormError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleAction(exam: Exam, action: "activate" | "lock") {
    setActionMessage(null);
    try {
      await api.post(`/examination/exams/${exam.exam_id}/${action}`);
      reload();
    } catch (err) {
      setActionMessage(apiErrorMessage(err));
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Exams</h2>
        <button type="button" onClick={openCreate} className={primaryButtonClass}>
          New Exam
        </button>
      </div>

      <div className="mb-4 flex gap-3">
        <select
          value={classId ?? ""}
          onChange={(e) => setClassId(e.target.value ? Number(e.target.value) : null)}
          className={`${inputClass} w-40`}
        >
          <option value="">Select class</option>
          {classes.map((c) => (
            <option key={c.class_id} value={c.class_id}>
              {c.class_name}
            </option>
          ))}
        </select>
        <select
          value={sessionId ?? ""}
          onChange={(e) => setSessionId(e.target.value ? Number(e.target.value) : null)}
          className={`${inputClass} w-52`}
        >
          <option value="">Select session</option>
          {sessions.map((s) => (
            <option key={s.academic_session_id} value={s.academic_session_id}>
              {s.session_name}
            </option>
          ))}
        </select>
      </div>

      {actionMessage && <p className="mb-3 text-sm text-red-600 dark:text-red-400">{actionMessage}</p>}
      {(classId === null || sessionId === null) && <p className="text-sm text-slate-400">Pick a class and session.</p>}
      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {classId !== null && sessionId !== null && !isLoading && !error && (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th className="px-4 py-2 font-medium">Name</th>
                <th className="px-4 py-2 font-medium">Date</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {exams.map((exam) => (
                <tr key={exam.exam_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{exam.exam_name}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{exam.exam_date}</td>
                  <td className="px-4 py-2">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_STYLES[exam.status]}`}>
                      {exam.status}
                    </span>
                  </td>
                  <td className="px-4 py-2 text-right">
                    {exam.status === "CONFIGURED" && (
                      <button
                        type="button"
                        onClick={() => handleAction(exam, "activate")}
                        className="text-xs text-slate-600 hover:underline dark:text-slate-400"
                      >
                        Activate
                      </button>
                    )}
                    {exam.status === "ACTIVE" && (
                      <button
                        type="button"
                        onClick={() => handleAction(exam, "lock")}
                        className="text-xs text-amber-700 hover:underline dark:text-amber-400"
                      >
                        Lock
                      </button>
                    )}
                  </td>
                </tr>
              ))}
              {exams.length === 0 && (
                <tr>
                  <td colSpan={4} className="px-4 py-6 text-center text-slate-400">
                    No exams yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && (
        <Modal title="New Exam" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className={labelClass}>Exam name</label>
              <input
                required
                value={form.exam_name}
                onChange={(e) => setForm({ ...form, exam_name: e.target.value })}
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>Class</label>
              <select
                required
                value={form.class_id}
                onChange={(e) => setForm({ ...form, class_id: e.target.value })}
                className={inputClass}
              >
                <option value="" disabled>
                  Select class
                </option>
                {classes.map((c) => (
                  <option key={c.class_id} value={c.class_id}>
                    {c.class_name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className={labelClass}>Academic session</label>
              <select
                required
                value={form.academic_session_id}
                onChange={(e) => setForm({ ...form, academic_session_id: e.target.value })}
                className={inputClass}
              >
                <option value="" disabled>
                  Select session
                </option>
                {sessions.map((s) => (
                  <option key={s.academic_session_id} value={s.academic_session_id}>
                    {s.session_name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className={labelClass}>Grading scheme</label>
              <select
                required
                value={form.grading_scheme_id}
                onChange={(e) => setForm({ ...form, grading_scheme_id: e.target.value })}
                className={inputClass}
              >
                <option value="" disabled>
                  Select scheme
                </option>
                {gradingSchemes.map((gs) => (
                  <option key={gs.grading_scheme_id} value={gs.grading_scheme_id}>
                    {gs.scheme_name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className={labelClass}>Exam date</label>
              <input
                required
                type="date"
                value={form.exam_date}
                onChange={(e) => setForm({ ...form, exam_date: e.target.value })}
                className={inputClass}
              />
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
