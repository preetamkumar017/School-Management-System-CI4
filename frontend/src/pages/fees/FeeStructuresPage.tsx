import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { useAcademicSessions, useClasses } from "../../lib/academic";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";
import { useFeeHeads } from "./FeeHeadsPage";

interface FeeStructure {
  fee_structure_id: number;
  class_id: number;
  fee_head_id: number;
  academic_session_id: number;
  route_id: number | null;
  category: "GENERAL" | "RTE";
  amount: number;
}

interface FormState {
  class_id: string;
  fee_head_id: string;
  academic_session_id: string;
  category: "GENERAL" | "RTE";
  amount: string;
}

const EMPTY_FORM: FormState = { class_id: "", fee_head_id: "", academic_session_id: "", category: "GENERAL", amount: "" };

export default function FeeStructuresPage() {
  const { classes } = useClasses();
  const { sessions } = useAcademicSessions();
  const { feeHeads } = useFeeHeads();

  const [classId, setClassId] = useState<number | null>(null);
  const [sessionId, setSessionId] = useState<number | null>(null);
  const [category, setCategory] = useState<"GENERAL" | "RTE">("GENERAL");

  const [structures, setStructures] = useState<FeeStructure[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function feeHeadName(id: number): string {
    return feeHeads.find((fh) => fh.fee_head_id === id)?.fee_head_name ?? `Fee head #${id}`;
  }

  function reload() {
    if (classId === null || sessionId === null) {
      setStructures([]);
      return;
    }
    setIsLoading(true);
    api
      .get<{ data: FeeStructure[] }>("/fees/fee-structures", {
        params: { class_id: classId, academic_session_id: sessionId, category },
      })
      .then((response) => setStructures(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  useEffect(reload, [classId, sessionId, category]);

  function openCreate() {
    setForm({
      class_id: classId ? String(classId) : "",
      fee_head_id: "",
      academic_session_id: sessionId ? String(sessionId) : "",
      category,
      amount: "",
    });
    setFormError(null);
    setIsCreating(true);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.post("/fees/fee-structures", {
        class_id: Number(form.class_id),
        fee_head_id: Number(form.fee_head_id),
        academic_session_id: Number(form.academic_session_id),
        category: form.category,
        amount: Number(form.amount),
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
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Fee Structures</h2>
        <button type="button" onClick={openCreate} className={primaryButtonClass}>
          New Fee Structure
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
        <select value={category} onChange={(e) => setCategory(e.target.value as "GENERAL" | "RTE")} className={`${inputClass} w-32`}>
          <option value="GENERAL">General</option>
          <option value="RTE">RTE</option>
        </select>
      </div>

      {(classId === null || sessionId === null) && (
        <p className="text-sm text-slate-400">Pick a class and academic session to see fee structures.</p>
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
                <th className="px-4 py-2 font-medium">Fee head</th>
                <th className="px-4 py-2 font-medium">Amount</th>
                <th className="px-4 py-2 font-medium">Route-tier?</th>
              </tr>
            </thead>
            <tbody>
              {structures.map((s) => (
                <tr key={s.fee_structure_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{feeHeadName(s.fee_head_id)}</td>
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">₹{s.amount}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{s.route_id ? `Route #${s.route_id}` : "—"}</td>
                </tr>
              ))}
              {structures.length === 0 && (
                <tr>
                  <td colSpan={3} className="px-4 py-6 text-center text-slate-400">
                    No fee structure for this class/session/category yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && (
        <Modal title="New Fee Structure" onClose={() => setIsCreating(false)}>
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
              <label className={labelClass}>Fee head</label>
              <select
                required
                value={form.fee_head_id}
                onChange={(e) => setForm({ ...form, fee_head_id: e.target.value })}
                className={inputClass}
              >
                <option value="" disabled>
                  Select fee head
                </option>
                {feeHeads.map((fh) => (
                  <option key={fh.fee_head_id} value={fh.fee_head_id}>
                    {fh.fee_head_name}
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
                <label className={labelClass}>Category</label>
                <select
                  value={form.category}
                  onChange={(e) => setForm({ ...form, category: e.target.value as FormState["category"] })}
                  className={inputClass}
                >
                  <option value="GENERAL">General</option>
                  <option value="RTE">RTE</option>
                </select>
              </div>
              <div>
                <label className={labelClass}>Amount</label>
                <input
                  required
                  type="number"
                  step="0.01"
                  min={0}
                  value={form.amount}
                  onChange={(e) => setForm({ ...form, amount: e.target.value })}
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
