import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";
import { useRoutes } from "./RoutesPage";

interface Allocation {
  transport_allocation_id: number;
  student_id: number;
  route_id: number;
  stop_name: string;
  emergency_contact: string;
  status: "Active" | "Inactive";
}

interface Trip {
  trip_id: number;
  route_id: number;
  driver_id: number;
  vehicle_id: number;
  started_at: string;
  status: "Started" | "Completed";
}

export default function AllocationsAndTripsPage() {
  const { routes } = useRoutes();
  const [routeId, setRouteId] = useState<number | null>(null);
  const [allocations, setAllocations] = useState<Allocation[]>([]);
  const [trips, setTrips] = useState<Trip[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const [isCreatingAllocation, setIsCreatingAllocation] = useState(false);
  const [allocForm, setAllocForm] = useState({ student_id: "", stop_name: "", emergency_contact: "" });
  const [allocFormError, setAllocFormError] = useState<string | null>(null);
  const [isSubmittingAlloc, setIsSubmittingAlloc] = useState(false);
  const [isStartingTrip, setIsStartingTrip] = useState(false);

  function reload() {
    if (routeId === null) {
      setAllocations([]);
      setTrips([]);
      return;
    }
    setIsLoading(true);
    setError(null);
    Promise.all([
      api.get<{ data: Allocation[] }>("/transport/allocations", { params: { route_id: routeId } }),
      api.get<{ data: Trip[] }>("/transport/trips", { params: { route_id: routeId } }),
    ])
      .then(([allocResponse, tripResponse]) => {
        setAllocations(allocResponse.data.data);
        setTrips(tripResponse.data.data);
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  useEffect(reload, [routeId]);

  async function handleCreateAllocation(event: FormEvent) {
    event.preventDefault();
    if (routeId === null) return;
    setAllocFormError(null);
    setIsSubmittingAlloc(true);
    try {
      await api.post("/transport/allocations", {
        student_id: Number(allocForm.student_id),
        route_id: routeId,
        stop_name: allocForm.stop_name,
        emergency_contact: allocForm.emergency_contact,
      });
      setIsCreatingAllocation(false);
      reload();
    } catch (err) {
      setAllocFormError(apiErrorMessage(err));
    } finally {
      setIsSubmittingAlloc(false);
    }
  }

  async function handleDeallocate(allocation: Allocation) {
    setMessage(null);
    try {
      await api.post(`/transport/allocations/${allocation.transport_allocation_id}/deallocate`);
      reload();
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  async function handleStartTrip() {
    if (routeId === null) return;
    setMessage(null);
    setIsStartingTrip(true);
    try {
      await api.post("/transport/trips/start", { route_id: routeId });
      reload();
    } catch (err) {
      setMessage(apiErrorMessage(err));
    } finally {
      setIsStartingTrip(false);
    }
  }

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Allocations & Trips</h2>
        {routeId !== null && (
          <div className="flex gap-2">
            <button type="button" onClick={handleStartTrip} disabled={isStartingTrip} className={secondaryButtonClass}>
              {isStartingTrip ? "Starting…" : "Start Trip (BR-TRN-006)"}
            </button>
            <button
              type="button"
              onClick={() => {
                setAllocForm({ student_id: "", stop_name: "", emergency_contact: "" });
                setAllocFormError(null);
                setIsCreatingAllocation(true);
              }}
              className={primaryButtonClass}
            >
              New Allocation
            </button>
          </div>
        )}
      </div>

      <div className="mb-4">
        <select
          value={routeId ?? ""}
          onChange={(e) => setRouteId(e.target.value ? Number(e.target.value) : null)}
          className={`${inputClass} w-56`}
        >
          <option value="">Select route</option>
          {routes.map((r) => (
            <option key={r.route_id} value={r.route_id}>
              {r.route_name}
            </option>
          ))}
        </select>
      </div>

      {message && <p className="mb-3 text-sm text-red-600 dark:text-red-400">{message}</p>}
      {routeId === null && <p className="text-sm text-slate-400">Pick a route.</p>}
      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {routeId !== null && !isLoading && !error && (
        <div className="space-y-6">
          <div>
            <p className={labelClass}>Student Allocations</p>
            <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
              <table className="w-full text-left text-sm">
                <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
                  <tr>
                    <th className="px-4 py-2 font-medium">Student</th>
                    <th className="px-4 py-2 font-medium">Stop</th>
                    <th className="px-4 py-2 font-medium">Emergency contact</th>
                    <th className="px-4 py-2 font-medium">Status</th>
                    <th className="px-4 py-2" />
                  </tr>
                </thead>
                <tbody>
                  {allocations.map((a) => (
                    <tr key={a.transport_allocation_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                      <td className="px-4 py-2 text-slate-900 dark:text-slate-100">#{a.student_id}</td>
                      <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{a.stop_name}</td>
                      <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{a.emergency_contact}</td>
                      <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{a.status}</td>
                      <td className="px-4 py-2 text-right">
                        {a.status === "Active" && (
                          <button
                            type="button"
                            onClick={() => handleDeallocate(a)}
                            className="text-xs text-red-600 hover:underline dark:text-red-400"
                          >
                            Deallocate
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                  {allocations.length === 0 && (
                    <tr>
                      <td colSpan={5} className="px-4 py-6 text-center text-slate-400">
                        No allocations for this route.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>

          <div>
            <p className={labelClass}>Trips</p>
            <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
              <table className="w-full text-left text-sm">
                <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
                  <tr>
                    <th className="px-4 py-2 font-medium">Driver</th>
                    <th className="px-4 py-2 font-medium">Vehicle</th>
                    <th className="px-4 py-2 font-medium">Started at</th>
                    <th className="px-4 py-2 font-medium">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {trips.map((t) => (
                    <tr key={t.trip_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                      <td className="px-4 py-2 text-slate-900 dark:text-slate-100">#{t.driver_id}</td>
                      <td className="px-4 py-2 text-slate-500 dark:text-slate-400">#{t.vehicle_id}</td>
                      <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{t.started_at}</td>
                      <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{t.status}</td>
                    </tr>
                  ))}
                  {trips.length === 0 && (
                    <tr>
                      <td colSpan={4} className="px-4 py-6 text-center text-slate-400">
                        No trips for this route.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {isCreatingAllocation && routeId !== null && (
        <Modal title="New Transport Allocation" onClose={() => setIsCreatingAllocation(false)}>
          <form onSubmit={handleCreateAllocation} className="space-y-4">
            <div>
              <label className={labelClass}>Student ID</label>
              <input
                required
                type="number"
                min={1}
                value={allocForm.student_id}
                onChange={(e) => setAllocForm({ ...allocForm, student_id: e.target.value })}
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>Stop name</label>
              <input
                required
                value={allocForm.stop_name}
                onChange={(e) => setAllocForm({ ...allocForm, stop_name: e.target.value })}
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>Emergency contact (10-digit)</label>
              <input
                required
                value={allocForm.emergency_contact}
                onChange={(e) => setAllocForm({ ...allocForm, emergency_contact: e.target.value })}
                className={inputClass}
              />
            </div>

            {allocFormError && (
              <p role="alert" className="text-sm text-red-600 dark:text-red-400">
                {allocFormError}
              </p>
            )}

            <div className="flex justify-end gap-2 pt-2">
              <button type="button" onClick={() => setIsCreatingAllocation(false)} className={secondaryButtonClass}>
                Cancel
              </button>
              <button type="submit" disabled={isSubmittingAlloc} className={primaryButtonClass}>
                {isSubmittingAlloc ? "Saving…" : "Save"}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
