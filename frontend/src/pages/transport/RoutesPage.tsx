import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";
import { useVehicles } from "./VehiclesPage";
import { useDrivers } from "./DriversPage";

export interface Route {
  route_id: number;
  route_name: string;
  stops_json: string[];
  capacity: number;
  vehicle_id: number | null;
  driver_id: number | null;
}

interface FormState {
  route_name: string;
  stops: string;
  capacity: string;
  vehicle_id: string;
  driver_id: string;
}

const EMPTY_FORM: FormState = { route_name: "", stops: "", capacity: "", vehicle_id: "", driver_id: "" };

export function useRoutes() {
  const [routes, setRoutes] = useState<Route[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  function reload() {
    setIsLoading(true);
    api
      .get<{ data: Route[] }>("/transport/routes")
      .then((response) => setRoutes(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  useEffect(reload, []);

  return { routes, isLoading, error, reload };
}

export default function RoutesPage() {
  const { vehicles } = useVehicles();
  const { drivers } = useDrivers();
  const { routes, isLoading, error, reload } = useRoutes();

  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function vehicleReg(id: number | null): string {
    if (id === null) return "—";
    return vehicles.find((v) => v.vehicle_id === id)?.registration_no ?? `Vehicle #${id}`;
  }
  function driverName(id: number | null): string {
    if (id === null) return "—";
    return drivers.find((d) => d.driver_id === id)?.full_name ?? `Driver #${id}`;
  }

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
      await api.post("/transport/routes", {
        route_name: form.route_name,
        stops_json: form.stops.split(",").map((s) => s.trim()).filter(Boolean),
        capacity: Number(form.capacity),
        vehicle_id: form.vehicle_id ? Number(form.vehicle_id) : null,
        driver_id: form.driver_id ? Number(form.driver_id) : null,
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
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Routes</h2>
        <button type="button" onClick={openCreate} className={primaryButtonClass}>
          New Route
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
                <th className="px-4 py-2 font-medium">Stops</th>
                <th className="px-4 py-2 font-medium">Capacity</th>
                <th className="px-4 py-2 font-medium">Vehicle</th>
                <th className="px-4 py-2 font-medium">Driver</th>
              </tr>
            </thead>
            <tbody>
              {routes.map((r) => (
                <tr key={r.route_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{r.route_name}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{r.stops_json.join(", ") || "—"}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{r.capacity}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{vehicleReg(r.vehicle_id)}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{driverName(r.driver_id)}</td>
                </tr>
              ))}
              {routes.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-slate-400">
                    No routes yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && (
        <Modal title="New Route" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className={labelClass}>Route name</label>
              <input required value={form.route_name} onChange={(e) => setForm({ ...form, route_name: e.target.value })} className={inputClass} />
            </div>
            <div>
              <label className={labelClass}>Stops (comma-separated)</label>
              <input value={form.stops} onChange={(e) => setForm({ ...form, stops: e.target.value })} className={inputClass} />
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
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className={labelClass}>Vehicle</label>
                <select value={form.vehicle_id} onChange={(e) => setForm({ ...form, vehicle_id: e.target.value })} className={inputClass}>
                  <option value="">None</option>
                  {vehicles.map((v) => (
                    <option key={v.vehicle_id} value={v.vehicle_id}>
                      {v.registration_no}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className={labelClass}>Driver</label>
                <select value={form.driver_id} onChange={(e) => setForm({ ...form, driver_id: e.target.value })} className={inputClass}>
                  <option value="">None</option>
                  {drivers.map((d) => (
                    <option key={d.driver_id} value={d.driver_id}>
                      {d.full_name}
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
