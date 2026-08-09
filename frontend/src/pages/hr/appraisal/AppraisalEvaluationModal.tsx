import { useState, useEffect } from "react";
import { api, apiErrorMessage } from "../../../lib/api";

export default function AppraisalEvaluationModal({ appraisalId, onClose }: { appraisalId: number; onClose: () => void }) {
  const [data, setData] = useState<any>(null);
  const [scores, setScores] = useState<Record<number, number>>({});
  const [comments, setComments] = useState("");
  const [recommendation, setRecommendation] = useState("None");
  const [error, setError] = useState("");
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    fetchAppraisal();
  }, [appraisalId]);

  const fetchAppraisal = async () => {
    try {
      const res = await api.get<{ data: any }>(`/hr-payroll/appraisals/${appraisalId}`);
      setData(res.data.data);
      const initialScores: any = {};
      res.data.data.kpis.forEach((k: any) => {
        initialScores[k.kpi_id] = k.evaluator_score || k.self_score || 0;
      });
      setScores(initialScores);
      setComments(res.data.data.appraisal.evaluator_comments || "");
      setRecommendation(res.data.data.appraisal.recommendation || "None");
    } catch (err) {
      setError(apiErrorMessage(err));
    }
  };

  const handleSave = async () => {
    try {
      setSaving(true);
      const payload = {
        kpi_scores: Object.keys(scores).reduce((acc: any, kpiId) => {
          acc[kpiId] = { score: scores[Number(kpiId)] };
          return acc;
        }, {}),
        comments,
        recommendation,
      };
      
      const isSelf = data.appraisal.status === 'Self Appraisal Pending';
      const endpoint = isSelf ? `/hr-payroll/appraisals/${appraisalId}/self` : `/hr-payroll/appraisals/${appraisalId}/manager`;
      
      await api.post(endpoint, payload);
      onClose();
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setSaving(false);
    }
  };

  if (!data) return null;

  const isSelf = data.appraisal.status === 'Self Appraisal Pending';

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="fixed inset-0 bg-black/30 backdrop-blur-sm" onClick={onClose} aria-hidden="true" />
      <div className="relative mx-auto w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl dark:bg-slate-900 border dark:border-slate-800">
        <h2 className="text-xl font-bold text-slate-900 dark:text-white mb-1">
          {isSelf ? "Self Appraisal" : "Manager Evaluation"}
        </h2>
        <p className="text-sm text-slate-500 mb-6">Evaluating: {data.employee.first_name} {data.employee.last_name}</p>

        {error && <div className="mb-4 rounded bg-red-50 p-3 text-sm text-red-600">{error}</div>}

        <div className="space-y-6 max-h-[60vh] overflow-y-auto pr-2">
          <div>
            <h3 className="font-semibold mb-3 border-b pb-2">KPIs & KRAs</h3>
            {data.kpis.map((kpi: any) => (
              <div key={kpi.kpi_id} className="mb-4 bg-slate-50 dark:bg-slate-800/50 p-4 rounded-lg">
                <div className="flex justify-between items-center mb-2">
                  <span className="font-medium">{kpi.kpi_name}</span>
                  <span className="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded">Weight: {kpi.weightage}%</span>
                </div>
                <div className="flex gap-4 items-center">
                  <label className="text-sm text-slate-500 w-24">Score (0-5):</label>
                  <input
                    type="number"
                    min="0"
                    max="5"
                    step="0.1"
                    value={scores[kpi.kpi_id] || ''}
                    onChange={(e) => setScores({ ...scores, [kpi.kpi_id]: Number(e.target.value) })}
                    className="border rounded p-1 w-20 text-center dark:bg-slate-900 dark:border-slate-700"
                  />
                </div>
                {!isSelf && (
                  <p className="text-xs text-slate-400 mt-2">Self Score was: {kpi.self_score || 'Not provided'}</p>
                )}
              </div>
            ))}
          </div>

          {!isSelf && (
            <>
              <div>
                <label className="block text-sm font-medium mb-1">Evaluator Comments</label>
                <textarea
                  className="w-full border rounded-md p-2 dark:bg-slate-900 dark:border-slate-700"
                  rows={3}
                  value={comments}
                  onChange={(e) => setComments(e.target.value)}
                ></textarea>
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Recommendation</label>
                <select
                  className="w-full border rounded-md p-2 dark:bg-slate-900 dark:border-slate-700"
                  value={recommendation}
                  onChange={(e) => setRecommendation(e.target.value)}
                >
                  <option value="None">None</option>
                  <option value="Increment">Increment</option>
                  <option value="Promotion">Promotion</option>
                </select>
              </div>
            </>
          )}
        </div>

        <div className="mt-6 flex justify-end gap-3">
          <button onClick={onClose} className="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded">Cancel</button>
          <button
            onClick={handleSave}
            disabled={saving}
            className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded shadow-sm disabled:opacity-50"
          >
            {saving ? 'Saving...' : 'Submit Evaluation'}
          </button>
        </div>
      </div>
    </div>
  );
}
