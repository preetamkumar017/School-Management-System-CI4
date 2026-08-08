import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { useAcademicSessions, useClasses } from "../../lib/academic";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";

interface SeatAllocation {
  seat_allocation_id: number;
  class_id: number;
  academic_session_id: number;
  total_capacity: number;
  rte_quota_capacity: number;
  seats_filled: number;
  rte_seats_filled: number;
}

interface FormState {
  class_id: string;
  academic_session_id: string;
  total_capacity: string;
  rte_quota_capacity: string;
}

const EMPTY_FORM: FormState = { class_id: "", academic_session_id: "", total_capacity: "", rte_quota_capacity: "" };

export default function SeatAllocationsPage() {
  const { classes } = useClasses();
  const { sessions } = useAcademicSessions();

  const [classId, setClassId] = useState<number | null>(null);
  const [sessionId, setSessionId] = useState<number | null>(null);
  const [allocations, setAllocations] = useState<SeatAllocation[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function reload() {
    if (classId === null || sessionId === null) {
      setAllocations([]);
      return;
    }
    setIsLoading(true);
    api
      .get<{ data: SeatAllocation[] }>("/admission/seat-allocations", {
        params: { class_id: classId, academic_session_id: sessionId },
      })
      .then((response) => setAllocations(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  useEffect(reload, [classId, sessionId]);

  function openCreate() {
    setForm({
      class_id: classId ? String(classId) : "",
      academic_session_id: sessionId ? String(sessionId) : "",
      total_capacity: "",
      rte_quota_capacity: "",
    });
    setFormError(null);
    setIsCreating(true);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.post("/admission/seat-allocations", {
        class_id: Number(form.class_id),
        academic_session_id: Number(form.academic_session_id),
        total_capacity: Number(form.total_capacity),
        rte_quota_capacity: Number(form.rte_quota_capacity),
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
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Seat Allocations</h2>
        <button type="button" onClick={openCreate} className={primaryButtonClass}>
          New Seat Allocation
        </button>
      </div>

      <div className="mb-4 flex gap-3">
        <select
          value={classId ?? ""}
          onChange={(e) => setClassId(e.target.value ? Number(e.target.value) : null)}
          className={`${inputClass} w-48`}
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
          className={`${inputClass} w-56`}
        >
          <option value="">Select academic session</option>
          {sessions.map((s) => (
            <option key={s.academic_session_id} value={s.academic_session_id}>
              {s.session_name}
            </option>
          ))}
        </select>
      </div>

      {(classId === null || sessionId === null) && (
        <p className="text-sm text-slate-400">Pick a class and academic session to see seat allocations.</p>
      )}
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
                <th className="px-4 py-2 font-medium">Total capacity</th>
                <th className="px-4 py-2 font-medium">Seats filled</th>
                <th className="px-4 py-2 font-medium">RTE quota</th>
                <th className="px-4 py-2 font-medium">RTE seats filled</th>
              </tr>
            </thead>
            <tbody>
              {allocations.map((allocation) => (
                <tr key={allocation.seat_allocation_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{allocation.total_capacity}</td>
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{allocation.seats_filled}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{allocation.rte_quota_capacity}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{allocation.rte_seats_filled}</td>
                </tr>
              ))}
              {allocations.length === 0 && (
                <tr>
                  <td colSpan={4} className="px-4 py-6 text-center text-slate-400">
                    No seat allocation for this class/session yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && (
        <Modal title="New Seat Allocation" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleSubmit} className="space-y-4">
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
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className={labelClass}>Total capacity</label>
                <input
                  required
                  type="number"
                  min={1}
                  value={form.total_capacity}
                  onChange={(e) => setForm({ ...form, total_capacity: e.target.value })}
                  className={inputClass}
                />
              </div>
              <div>
                <label className={labelClass}>RTE quota capacity</label>
                <input
                  required
                  type="number"
                  min={0}
                  value={form.rte_quota_capacity}
                  onChange={(e) => setForm({ ...form, rte_quota_capacity: e.target.value })}
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
