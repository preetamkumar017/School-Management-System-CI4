import { useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { useAcademicSessions, useClasses } from "../../lib/academic";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";

interface PromotionRecord {
  promotion_record_id: number;
  student_id: number;
  from_session_id: number;
  to_session_id: number;
  from_class_id: number;
  to_class_id: number;
  academic_closure_confirmed: boolean;
  fee_closure_confirmed: boolean;
}

interface FormState {
  student_id: string;
  from_session_id: string;
  from_class_id: string;
  to_class_id: string;
}

const EMPTY_FORM: FormState = { student_id: "", from_session_id: "", from_class_id: "", to_class_id: "" };

export default function PromotionsPage() {
  const { classes } = useClasses();
  const { sessions } = useAcademicSessions();

  const [toSessionId, setToSessionId] = useState<number | null>(null);
  const [promotions, setPromotions] = useState<PromotionRecord[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function className(id: number): string {
    return classes.find((c) => c.class_id === id)?.class_name ?? `Class #${id}`;
  }

  function reload(forToSessionId: number) {
    setIsLoading(true);
    setError(null);
    api
      .get<{ data: PromotionRecord[] }>("/examination/promotions", { params: { to_session_id: forToSessionId } })
      .then((response) => setPromotions(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  function handleSessionChange(value: string) {
    const id = value ? Number(value) : null;
    setToSessionId(id);
    if (id) reload(id);
    else setPromotions([]);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    if (toSessionId === null) return;
    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.post("/examination/promotions", {
        student_id: Number(form.student_id),
        from_session_id: Number(form.from_session_id),
        to_session_id: toSessionId,
        from_class_id: Number(form.from_class_id),
        to_class_id: Number(form.to_class_id),
      });
      setIsCreating(false);
      reload(toSessionId);
    } catch (err) {
      setFormError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Promotions</h2>
        {toSessionId !== null && (
          <button
            type="button"
            onClick={() => {
              setForm(EMPTY_FORM);
              setFormError(null);
              setIsCreating(true);
            }}
            className={primaryButtonClass}
          >
            New Promotion
          </button>
        )}
      </div>

      <p className="mb-4 text-sm text-slate-400">
        BR-SIS-001: promotion needs both academic and fee closure confirmed — fee closure is computed automatically
        from outstanding invoices (ADR-014), not caller-supplied.
      </p>

      <div className="mb-4">
        <select value={toSessionId ?? ""} onChange={(e) => handleSessionChange(e.target.value)} className={`${inputClass} w-56`}>
          <option value="">To academic session</option>
          {sessions.map((s) => (
            <option key={s.academic_session_id} value={s.academic_session_id}>
              {s.session_name}
            </option>
          ))}
        </select>
      </div>

      {toSessionId === null && <p className="text-sm text-slate-400">Pick a target academic session.</p>}
      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {toSessionId !== null && !isLoading && !error && (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th className="px-4 py-2 font-medium">Student</th>
                <th className="px-4 py-2 font-medium">From class</th>
                <th className="px-4 py-2 font-medium">To class</th>
                <th className="px-4 py-2 font-medium">Academic closed?</th>
                <th className="px-4 py-2 font-medium">Fee closed?</th>
              </tr>
            </thead>
            <tbody>
              {promotions.map((p) => (
                <tr key={p.promotion_record_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">#{p.student_id}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{className(p.from_class_id)}</td>
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{className(p.to_class_id)}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{p.academic_closure_confirmed ? "Yes" : "No"}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{p.fee_closure_confirmed ? "Yes" : "No"}</td>
                </tr>
              ))}
              {promotions.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-slate-400">
                    No promotions for this session.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && toSessionId !== null && (
        <Modal title="New Promotion" onClose={() => setIsCreating(false)}>
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
              <label className={labelClass}>From session</label>
              <select
                required
                value={form.from_session_id}
                onChange={(e) => setForm({ ...form, from_session_id: e.target.value })}
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
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className={labelClass}>From class</label>
                <select
                  required
                  value={form.from_class_id}
                  onChange={(e) => setForm({ ...form, from_class_id: e.target.value })}
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
                <label className={labelClass}>To class</label>
                <select
                  required
                  value={form.to_class_id}
                  onChange={(e) => setForm({ ...form, to_class_id: e.target.value })}
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
