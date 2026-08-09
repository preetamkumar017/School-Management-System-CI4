import { useState, useEffect } from "react";
import { api, apiErrorMessage } from "../../../lib/api";
import AppraisalEvaluationModal from "./AppraisalEvaluationModal";

export default function AppraisalCycleView({ cycleId, onBack }: { cycleId: number; onBack: () => void }) {
  const [appraisals, setAppraisals] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [selectedAppraisalId, setSelectedAppraisalId] = useState<number | null>(null);

  useEffect(() => {
    fetchAppraisals();
  }, [cycleId]);

  const fetchAppraisals = async () => {
    try {
      setLoading(true);
      const res = await api.get<{ data: any[] }>(`/hr-payroll/appraisals/cycles/${cycleId}/appraisals`);
      setAppraisals(res.data.data);
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  const getStatusColor = (status: string) => {
    if (status === 'Completed') return 'text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30';
    if (status === 'Review Pending') return 'text-amber-600 bg-amber-50 dark:bg-amber-900/30';
    return 'text-slate-600 bg-slate-50 dark:bg-slate-800';
  };

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <div className="mb-6 flex items-center gap-4 border-b border-slate-200 pb-4 dark:border-slate-800">
        <button onClick={onBack} className="rounded border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50">← Back</button>
        <h2 className="text-lg font-bold text-slate-900 dark:text-white">Cycle Appraisals</h2>
      </div>

      {error && <div className="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-600">{error}</div>}

      {loading ? (
        <p className="text-slate-500">Loading...</p>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
          <table className="w-full text-left text-sm">
            <thead className="bg-slate-50 text-xs text-slate-500 dark:bg-slate-800/50">
              <tr>
                <th className="px-4 py-3">Employee</th>
                <th className="px-4 py-3">Department</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3">Final Rating</th>
                <th className="px-4 py-3">Recommendation</th>
                <th className="px-4 py-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {appraisals.map((app) => (
                <tr key={app.appraisal_id}>
                  <td className="px-4 py-3 font-medium">{app.employee_name}</td>
                  <td className="px-4 py-3 text-slate-600">{app.department}</td>
                  <td className="px-4 py-3">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${getStatusColor(app.status)}`}>{app.status}</span>
                  </td>
                  <td className="px-4 py-3 font-bold">{app.final_rating ? `${app.final_rating} / 5` : '-'}</td>
                  <td className="px-4 py-3 text-slate-600">{app.recommendation}</td>
                  <td className="px-4 py-3 text-right">
                    <button onClick={() => setSelectedAppraisalId(app.appraisal_id)} className="text-indigo-600 hover:underline">Evaluate</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {selectedAppraisalId && (
        <AppraisalEvaluationModal
          appraisalId={selectedAppraisalId}
          onClose={() => {
            setSelectedAppraisalId(null);
            fetchAppraisals();
          }}
        />
      )}
    </div>
  );
}
