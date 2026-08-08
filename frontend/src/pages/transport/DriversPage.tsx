import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";

export interface Driver {
  driver_id: number;
  full_name: string;
  license_number: string;
  license_valid_until: string | null;
  status: "Active" | "Inactive";
}

interface FormState {
  full_name: string;
  license_number: string;
  license_valid_until: string;
}

const EMPTY_FORM: FormState = { full_name: "", license_number: "", license_valid_until: "" };

export function useDrivers() {
  const [drivers, setDrivers] = useState<Driver[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  function reload() {
    setIsLoading(true);
    api
      .get<{ data: Driver[] }>("/transport/drivers")
      .then((response) => setDrivers(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  useEffect(reload, []);

  return { drivers, isLoading, error, reload };
}

export default function DriversPage() {
  const { drivers, isLoading, error, reload } = useDrivers();
  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function openCreate() {
    setForm(EMPTY_FORM);
    setFormError(null);
    setIsCreating(true);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.post("/transport/drivers", {
        full_name: form.full_name,
        license_number: form.license_number,
        license_valid_until: form.license_valid_until || null,
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
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Drivers</h2>
        <button type="button" onClick={openCreate} className={primaryButtonClass}>
          New Driver
        </button>
      </div>

      <p className="mb-4 text-sm text-slate-400">
        BR-TRN-006: a trip can only start when both the assigned driver's and vehicle's licenses are currently valid.
      </p>

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
                <th className="px-4 py-2 font-medium">License #</th>
                <th className="px-4 py-2 font-medium">Valid until</th>
                <th className="px-4 py-2 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {drivers.map((d) => (
                <tr key={d.driver_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{d.full_name}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{d.license_number}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{d.license_valid_until ?? "—"}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{d.status}</td>
                </tr>
              ))}
              {drivers.length === 0 && (
                <tr>
                  <td colSpan={4} className="px-4 py-6 text-center text-slate-400">
                    No drivers yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && (
        <Modal title="New Driver" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className={labelClass}>Full name</label>
              <input required value={form.full_name} onChange={(e) => setForm({ ...form, full_name: e.target.value })} className={inputClass} />
            </div>
            <div>
              <label className={labelClass}>License number</label>
              <input
                required
                value={form.license_number}
                onChange={(e) => setForm({ ...form, license_number: e.target.value })}
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>License valid until</label>
              <input
                type="date"
                value={form.license_valid_until}
                onChange={(e) => setForm({ ...form, license_valid_until: e.target.value })}
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
