import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";

export interface Vehicle {
  vehicle_id: number;
  registration_no: string;
  capacity: number;
  gps_device_id: string | null;
  license_valid_until: string | null;
}

interface FormState {
  registration_no: string;
  capacity: string;
  license_valid_until: string;
}

const EMPTY_FORM: FormState = { registration_no: "", capacity: "", license_valid_until: "" };

export function useVehicles() {
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  function reload() {
    setIsLoading(true);
    api
      .get<{ data: Vehicle[] }>("/transport/vehicles")
      .then((response) => setVehicles(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  useEffect(reload, []);

  return { vehicles, isLoading, error, reload };
}

export default function VehiclesPage() {
  const { vehicles, isLoading, error, reload } = useVehicles();
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
      await api.post("/transport/vehicles", {
        registration_no: form.registration_no,
        capacity: Number(form.capacity),
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
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Vehicles</h2>
        <button type="button" onClick={openCreate} className={primaryButtonClass}>
          New Vehicle
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
                <th className="px-4 py-2 font-medium">Registration #</th>
                <th className="px-4 py-2 font-medium">Capacity</th>
                <th className="px-4 py-2 font-medium">License valid until</th>
              </tr>
            </thead>
            <tbody>
              {vehicles.map((v) => (
                <tr key={v.vehicle_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{v.registration_no}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{v.capacity}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{v.license_valid_until ?? "—"}</td>
                </tr>
              ))}
              {vehicles.length === 0 && (
                <tr>
                  <td colSpan={3} className="px-4 py-6 text-center text-slate-400">
                    No vehicles yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && (
        <Modal title="New Vehicle" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className={labelClass}>Registration number</label>
              <input
                required
                value={form.registration_no}
                onChange={(e) => setForm({ ...form, registration_no: e.target.value })}
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>Capacity</label>
              <input
                required
                type="number"
                min={1}
                value={form.capacity}
                onChange={(e) => setForm({ ...form, capacity: e.target.value })}
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>License valid until (BR-TRN-006)</label>
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
