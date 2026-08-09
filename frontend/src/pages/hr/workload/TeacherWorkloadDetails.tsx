import { useState, useEffect } from "react";
import { api, apiErrorMessage } from "../../../lib/api";

interface DetailsProps {
  employeeId: number;
  onBack: () => void;
}

interface TimetableEntry {
  timetable_entry_id: number;
  subject_id: number;
  section_id: number;
  day_of_week: string;
  period_no: number;
  is_extra_class: boolean;
}

interface WorkloadDetails {
  entries: TimetableEntry[];
  substitutions: any[];
  total_periods: number;
  extra_classes: number;
}

export default function TeacherWorkloadDetails({ employeeId, onBack }: DetailsProps) {
  const [details, setDetails] = useState<WorkloadDetails | null>(null);
  const [freePeriods, setFreePeriods] = useState<number[]>([]);
  const [dayFilter, setDayFilter] = useState("MONDAY");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const DAYS = ["MONDAY", "TUESDAY", "WEDNESDAY", "THURSDAY", "FRIDAY", "SATURDAY"];

  useEffect(() => {
    fetchDetails();
    fetchFreePeriods(dayFilter);
  }, [employeeId, dayFilter]);

  const fetchDetails = async () => {
    try {
      setLoading(true);
      const res = await api.get<{ data: WorkloadDetails }>(`/timetable/workload/teachers/${employeeId}`);
      setDetails(res.data.data);
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  const fetchFreePeriods = async (day: string) => {
    try {
      const res = await api.get<{ data: number[] }>(`/timetable/workload/teachers/${employeeId}/free-periods/${day}`);
      setFreePeriods(res.data.data);
    } catch (err) {
      console.error(err);
    }
  };

  const handleToggleExtraClass = async (entryId: number) => {
    try {
      await api.patch(`/timetable/workload/entries/${entryId}/toggle-extra`, {});
      fetchDetails(); // Reload data
    } catch (err) {
      setError(apiErrorMessage(err));
    }
  };

  if (loading) return <p className="text-slate-500">Loading details...</p>;
  if (!details) return <p className="text-slate-500">No details found.</p>;

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <div className="mb-6 flex items-center gap-4 border-b border-slate-200 pb-4 dark:border-slate-800">
        <button
          onClick={onBack}
          className="rounded border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
        >
          ← Back
        </button>
        <div>
          <h2 className="text-lg font-bold text-slate-900 dark:text-white">Teacher Detailed Workload</h2>
          <p className="text-sm text-slate-500 dark:text-slate-400">Total Periods: {details.total_periods} | Extra Classes: {details.extra_classes}</p>
        </div>
      </div>

      {error && <div className="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/30 dark:text-red-400">{error}</div>}

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        {/* Timetable Entries List */}
        <div className="md:col-span-2 space-y-4">
          <h3 className="font-semibold text-slate-800 dark:text-slate-200">Scheduled Classes</h3>
          <div className="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
            <table className="w-full text-left text-sm">
              <thead className="bg-slate-50 text-xs text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                <tr>
                  <th className="px-4 py-3">Day</th>
                  <th className="px-4 py-3">Period</th>
                  <th className="px-4 py-3">Class/Subject</th>
                  <th className="px-4 py-3">Type</th>
                  <th className="px-4 py-3">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {details.entries.length === 0 ? (
                  <tr><td colSpan={5} className="p-4 text-center text-slate-500">No classes assigned.</td></tr>
                ) : (
                  details.entries.map((entry) => (
                    <tr key={entry.timetable_entry_id}>
                      <td className="px-4 py-3 font-medium text-slate-700 dark:text-slate-300">{entry.day_of_week}</td>
                      <td className="px-4 py-3 text-slate-600 dark:text-slate-400">Period {entry.period_no}</td>
                      <td className="px-4 py-3 text-slate-600 dark:text-slate-400">Sub {entry.subject_id} / Sec {entry.section_id}</td>
                      <td className="px-4 py-3">
                        {entry.is_extra_class ? (
                          <span className="rounded bg-indigo-100 px-2 py-0.5 text-xs text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">Extra Class</span>
                        ) : (
                          <span className="text-slate-400 text-xs">Regular</span>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <button
                          onClick={() => handleToggleExtraClass(entry.timetable_entry_id)}
                          className="text-xs text-indigo-600 hover:underline dark:text-indigo-400"
                        >
                          {entry.is_extra_class ? "Mark Regular" : "Mark as Extra"}
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Free Periods Widget */}
        <div className="space-y-4">
          <h3 className="font-semibold text-slate-800 dark:text-slate-200">Free Periods Analyzer</h3>
          <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/30">
            <label className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2 block">Select Day</label>
            <select
              className="w-full mb-4 rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
              value={dayFilter}
              onChange={(e) => setDayFilter(e.target.value)}
            >
              {DAYS.map((d) => <option key={d} value={d}>{d}</option>)}
            </select>

            <div className="space-y-2">
              <label className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Available Periods</label>
              <div className="flex flex-wrap gap-2">
                {freePeriods.length === 0 ? (
                  <span className="text-sm text-slate-500">Fully Booked</span>
                ) : (
                  freePeriods.map((p) => (
                    <span key={p} className="rounded-md bg-emerald-100 px-3 py-1 text-sm font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                      P{p}
                    </span>
                  ))
                )}
              </div>
            </div>
            <p className="mt-4 text-xs text-slate-500">
              Use these periods to assign Substitute classes.
            </p>
          </div>
        </div>

      </div>
    </div>
  );
}
