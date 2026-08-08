import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";

export interface FeeHead {
  fee_head_id: number;
  fee_head_name: string;
  is_taxable: boolean;
  gst_rate: number | null;
}

export function useFeeHeads() {
  const [feeHeads, setFeeHeads] = useState<FeeHead[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  function reload() {
    setIsLoading(true);
    api
      .get<{ data: FeeHead[] }>("/fees/fee-heads")
      .then((response) => setFeeHeads(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  useEffect(reload, []);

  return { feeHeads, isLoading, error, reload };
}

interface FormState {
  fee_head_name: string;
  is_taxable: boolean;
  gst_rate: string;
}

const EMPTY_FORM: FormState = { fee_head_name: "", is_taxable: false, gst_rate: "" };

export default function FeeHeadsPage() {
  const { feeHeads, isLoading, error, reload } = useFeeHeads();
  const [editing, setEditing] = useState<FeeHead | "new" | null>(null);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function openCreate() {
    setForm(EMPTY_FORM);
    setFormError(null);
    setEditing("new");
  }

  function openEdit(feeHead: FeeHead) {
    setForm({
      fee_head_name: feeHead.fee_head_name,
      is_taxable: feeHead.is_taxable,
      gst_rate: feeHead.gst_rate !== null ? String(feeHead.gst_rate) : "",
    });
    setFormError(null);
    setEditing(feeHead);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setFormError(null);
    setIsSubmitting(true);
    const payload = {
      fee_head_name: form.fee_head_name,
      is_taxable: form.is_taxable,
      gst_rate: form.is_taxable && form.gst_rate ? Number(form.gst_rate) : null,
    };
    try {
      if (editing === "new") {
        await api.post("/fees/fee-heads", payload);
      } else if (editing) {
        await api.patch(`/fees/fee-heads/${editing.fee_head_id}`, payload);
      }
      setEditing(null);
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
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Fee Heads</h2>
        <button type="button" onClick={openCreate} className={primaryButtonClass}>
          New Fee Head
        </button>
      </div>

      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {!isLoading && !error && (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th className="px-4 py-2 font-medium">Name</th>
                <th className="px-4 py-2 font-medium">Taxable?</th>
                <th className="px-4 py-2 font-medium">GST rate</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {feeHeads.map((fh) => (
                <tr key={fh.fee_head_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{fh.fee_head_name}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{fh.is_taxable ? "Yes" : "No"}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{fh.gst_rate ?? "—"}</td>
                  <td className="px-4 py-2 text-right">
                    <button
                      type="button"
                      onClick={() => openEdit(fh)}
                      className="text-slate-600 hover:underline dark:text-slate-400"
                    >
                      Edit
                    </button>
                  </td>
                </tr>
              ))}
              {feeHeads.length === 0 && (
                <tr>
                  <td colSpan={4} className="px-4 py-6 text-center text-slate-400">
                    No fee heads yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {editing && (
        <Modal title={editing === "new" ? "New Fee Head" : "Edit Fee Head"} onClose={() => setEditing(null)}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className={labelClass}>Name</label>
              <input
                required
                value={form.fee_head_name}
                onChange={(e) => setForm({ ...form, fee_head_name: e.target.value })}
                className={inputClass}
              />
            </div>
            <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
              <input
                type="checkbox"
                checked={form.is_taxable}
                onChange={(e) => setForm({ ...form, is_taxable: e.target.checked })}
              />
              Taxable (GST applicable, BR-FEE-007)
            </label>
            {form.is_taxable && (
              <div>
                <label className={labelClass}>GST rate (%)</label>
                <input
                  type="number"
                  step="0.01"
                  min={0}
                  value={form.gst_rate}
                  onChange={(e) => setForm({ ...form, gst_rate: e.target.value })}
                  className={inputClass}
                />
              </div>
            )}

            {formError && (
              <p role="alert" className="text-sm text-red-600 dark:text-red-400">
                {formError}
              </p>
            )}

            <div className="flex justify-end gap-2 pt-2">
              <button type="button" onClick={() => setEditing(null)} className={secondaryButtonClass}>
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
