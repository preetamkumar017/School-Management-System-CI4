import { useState, useEffect } from "react";
import { api, apiErrorMessage } from "../../../lib/api";
import AppraisalCycleView from "./AppraisalCycleView";

export default function AppraisalDashboard() {
  const [cycles, setCycles] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [selectedCycleId, setSelectedCycleId] = useState<number | null>(null);

  const [isCreating, setIsCreating] = useState(false);
  const [newCycleName, setNewCycleName] = useState("");
  const [newCycleStart, setNewCycleStart] = useState("");
  const [newCycleEnd, setNewCycleEnd] = useState("");

  useEffect(() => {
    fetchCycles();
  }, []);

  const fetchCycles = async () => {
    try {
      setLoading(true);
      const res = await api.get<{ data: any[] }>("/hr-payroll/appraisals/cycles");
      setCycles(res.data.data);
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  const handleCreateCycle = async () => {
    try {
      await api.post("/hr-payroll/appraisals/cycles", {
        name: newCycleName,
        start_date: newCycleStart,
        end_date: newCycleEnd,
      });
      setIsCreating(false);
      setNewCycleName("");
      setNewCycleStart("");
      setNewCycleEnd("");
      fetchCycles();
    } catch (err) {
      setError(apiErrorMessage(err));
    }
  };

  if (selectedCycleId) {
    return <AppraisalCycleView cycleId={selectedCycleId} onBack={() => setSelectedCycleId(null)} />;
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-lg font-bold text-slate-900 dark:text-white">Performance & Appraisals</h2>
          <p className="text-sm text-slate-500 dark:text-slate-400">Manage performance evaluation cycles.</p>
        </div>
        <button
          onClick={() => setIsCreating(true)}
          className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        >
          + Start New Cycle
        </button>
      </div>

      {error && <div className="rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/30">{error}</div>}

      {isCreating && (
        <div className="rounded-xl border border-slate-200 bg-slate-50 p-6 dark:border-slate-800 dark:bg-slate-900/50">
          <h3 className="mb-4 text-sm font-semibold">Start New Appraisal Cycle</h3>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
              <label className="mb-1 block text-xs font-medium text-slate-500">Cycle Name</label>
              <input type="text" className="w-full rounded-md border border-slate-300 p-2 text-sm dark:border-slate-700 dark:bg-slate-900" value={newCycleName} onChange={(e) => setNewCycleName(e.target.value)} placeholder="e.g. 2026 Annual Review" />
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium text-slate-500">Start Date</label>
              <input type="date" className="w-full rounded-md border border-slate-300 p-2 text-sm dark:border-slate-700 dark:bg-slate-900" value={newCycleStart} onChange={(e) => setNewCycleStart(e.target.value)} />
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium text-slate-500">End Date</label>
              <input type="date" className="w-full rounded-md border border-slate-300 p-2 text-sm dark:border-slate-700 dark:bg-slate-900" value={newCycleEnd} onChange={(e) => setNewCycleEnd(e.target.value)} />
            </div>
          </div>
          <div className="mt-4 flex justify-end gap-2">
            <button onClick={() => setIsCreating(false)} className="rounded px-4 py-2 text-sm text-slate-600 hover:bg-slate-200">Cancel</button>
            <button onClick={handleCreateCycle} className="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Initialize Cycle</button>
          </div>
        </div>
      )}

      {loading ? (
        <p className="text-slate-500">Loading cycles...</p>
      ) : (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
          {cycles.map((cycle) => (
            <div key={cycle.cycle_id} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
              <div className="mb-3 flex items-start justify-between">
                <h3 className="font-bold text-slate-900 dark:text-white">{cycle.name}</h3>
                <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${cycle.status === 'Active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-800'}`}>
                  {cycle.status}
                </span>
              </div>
              <p className="mb-4 text-xs text-slate-500">{cycle.start_date} to {cycle.end_date}</p>
              <button
                onClick={() => setSelectedCycleId(cycle.cycle_id)}
                className="w-full rounded-lg border border-indigo-200 bg-indigo-50 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100 dark:border-indigo-900/30 dark:bg-indigo-900/20 dark:text-indigo-400 dark:hover:bg-indigo-900/40"
              >
                View Appraisals
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
