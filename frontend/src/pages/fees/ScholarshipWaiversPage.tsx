import { useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";
import { useFeeHeads } from "./FeeHeadsPage";

interface Waiver {
  scholarship_waiver_id: number;
  student_id: number;
  fee_head_id: number;
  waiver_type: "RTE" | "MERIT" | "SIBLING" | "STAFF_WARD";
  waiver_amount: number;
}

interface FormState {
  fee_head_id: string;
  waiver_type: Waiver["waiver_type"];
  waiver_amount: string;
}

const EMPTY_FORM: FormState = { fee_head_id: "", waiver_type: "MERIT", waiver_amount: "" };

export default function ScholarshipWaiversPage() {
  const { feeHeads } = useFeeHeads();
  const [studentIdInput, setStudentIdInput] = useState("");
  const [studentId, setStudentId] = useState<number | null>(null);
  const [waivers, setWaivers] = useState<Waiver[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function feeHeadName(id: number): string {
    return feeHeads.find((fh) => fh.fee_head_id === id)?.fee_head_name ?? `Fee head #${id}`;
  }

  function loadWaivers(id: number) {
    setIsLoading(true);
    setError(null);
    api
      .get<{ data: Waiver[] }>("/fees/scholarship-waivers", { params: { student_id: id } })
      .then((response) => setWaivers(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  function handleSearch(event: FormEvent) {
    event.preventDefault();
    const id = Number(studentIdInput);
    if (id > 0) {
      setStudentId(id);
      loadWaivers(id);
    }
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    if (studentId === null) return;
    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.post("/fees/scholarship-waivers", {
        student_id: studentId,
        fee_head_id: Number(form.fee_head_id),
        waiver_type: form.waiver_type,
        waiver_amount: Number(form.waiver_amount),
      });
      setIsCreating(false);
      loadWaivers(studentId);
    } catch (err) {
      setFormError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Scholarship Waivers</h2>
        {studentId !== null && (
          <button
            type="button"
            onClick={() => {
              setForm(EMPTY_FORM);
              setFormError(null);
              setIsCreating(true);
            }}
            className={primaryButtonClass}
          >
            New Waiver
          </button>
        )}
      </div>

      <form onSubmit={handleSearch} className="mb-4 flex gap-2">
        <input
          type="number"
          min={1}
          placeholder="Student ID"
          value={studentIdInput}
          onChange={(e) => setStudentIdInput(e.target.value)}
          className={`${inputClass} w-40`}
        />
        <button type="submit" className={secondaryButtonClass}>
          Search
        </button>
      </form>

      {studentId === null && <p className="text-sm text-slate-400">Enter a Student ID to see their waivers.</p>}
      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {studentId !== null && !isLoading && !error && (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th className="px-4 py-2 font-medium">Fee head</th>
                <th className="px-4 py-2 font-medium">Type</th>
                <th className="px-4 py-2 font-medium">Amount</th>
              </tr>
            </thead>
            <tbody>
              {waivers.map((w) => (
                <tr key={w.scholarship_waiver_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{feeHeadName(w.fee_head_id)}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{w.waiver_type}</td>
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">₹{w.waiver_amount}</td>
                </tr>
              ))}
              {waivers.length === 0 && (
                <tr>
                  <td colSpan={3} className="px-4 py-6 text-center text-slate-400">
                    No waivers for this student.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && studentId !== null && (
        <Modal title="New Waiver" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleSubmit} className="space-y-4">
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
              <label className={labelClass}>Waiver type</label>
              <select
                value={form.waiver_type}
                onChange={(e) => setForm({ ...form, waiver_type: e.target.value as FormState["waiver_type"] })}
                className={inputClass}
              >
                <option value="RTE">RTE</option>
                <option value="MERIT">Merit</option>
                <option value="SIBLING">Sibling</option>
                <option value="STAFF_WARD">Staff Ward</option>
              </select>
            </div>
            <div>
              <label className={labelClass}>Waiver amount</label>
              <input
                required
                type="number"
                step="0.01"
                min={0.01}
                value={form.waiver_amount}
                onChange={(e) => setForm({ ...form, waiver_amount: e.target.value })}
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
