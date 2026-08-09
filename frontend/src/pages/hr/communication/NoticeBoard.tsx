import { useState, useEffect } from "react";
import { api, apiErrorMessage } from "../../../lib/api";

export default function NoticeBoard() {
  const [communications, setCommunications] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [isCreating, setIsCreating] = useState(false);

  const [formData, setFormData] = useState({
    type: "Notice",
    title: "",
    message: "",
    target_audience: "All Staff",
    publish_date: new Date().toISOString().split('T')[0],
    is_pinned: false
  });

  useEffect(() => {
    fetchCommunications();
  }, []);

  const fetchCommunications = async () => {
    try {
      setLoading(true);
      const res = await api.get<{ data: any[] }>("/hr-payroll/communications");
      setCommunications(res.data.data);
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  const handleCreate = async () => {
    try {
      await api.post("/hr-payroll/communications", formData);
      setIsCreating(false);
      setFormData({ ...formData, title: "", message: "", is_pinned: false });
      fetchCommunications();
    } catch (err) {
      setError(apiErrorMessage(err));
    }
  };

  const getTypeBadge = (type: string) => {
    switch (type) {
      case 'Circular': return 'bg-purple-100 text-purple-700';
      case 'Notice': return 'bg-blue-100 text-blue-700';
      case 'HR Notification': return 'bg-amber-100 text-amber-700';
      case 'Alert': return 'bg-red-100 text-red-700';
      default: return 'bg-slate-100 text-slate-700';
    }
  };

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 h-full">
      <div className="flex items-center justify-between mb-6 border-b border-slate-200 pb-4 dark:border-slate-800">
        <h2 className="text-lg font-bold text-slate-900 dark:text-white">Notice Board</h2>
        <button
          onClick={() => setIsCreating(!isCreating)}
          className="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
        >
          {isCreating ? "Cancel" : "+ New Notice"}
        </button>
      </div>

      {error && <div className="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-600">{error}</div>}

      {isCreating && (
        <div className="mb-6 rounded-lg bg-slate-50 p-4 border border-slate-200 dark:bg-slate-800/50 dark:border-slate-700">
          <div className="grid grid-cols-2 gap-4 mb-3">
            <div>
              <label className="block text-xs font-medium text-slate-500 mb-1">Type</label>
              <select className="w-full rounded border p-2 text-sm dark:bg-slate-900 dark:border-slate-700" value={formData.type} onChange={(e) => setFormData({ ...formData, type: e.target.value })}>
                <option value="Notice">Notice</option>
                <option value="Circular">Circular</option>
                <option value="Announcement">Announcement</option>
                <option value="HR Notification">HR Notification</option>
                <option value="Alert">Alert</option>
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-slate-500 mb-1">Target Audience</label>
              <select className="w-full rounded border p-2 text-sm dark:bg-slate-900 dark:border-slate-700" value={formData.target_audience} onChange={(e) => setFormData({ ...formData, target_audience: e.target.value })}>
                <option value="All Staff">All Staff</option>
                <option value="Specific Department">Specific Department</option>
                <option value="Specific Designation">Specific Designation</option>
              </select>
            </div>
          </div>
          <div className="mb-3">
            <label className="block text-xs font-medium text-slate-500 mb-1">Title</label>
            <input type="text" className="w-full rounded border p-2 text-sm dark:bg-slate-900 dark:border-slate-700" value={formData.title} onChange={(e) => setFormData({ ...formData, title: e.target.value })} placeholder="E.g. Public Holiday Announcement" />
          </div>
          <div className="mb-3">
            <label className="block text-xs font-medium text-slate-500 mb-1">Message</label>
            <textarea className="w-full rounded border p-2 text-sm dark:bg-slate-900 dark:border-slate-700" rows={4} value={formData.message} onChange={(e) => setFormData({ ...formData, message: e.target.value })} placeholder="Notice details..."></textarea>
          </div>
          <div className="flex items-center justify-between mt-4">
            <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
              <input type="checkbox" checked={formData.is_pinned} onChange={(e) => setFormData({ ...formData, is_pinned: e.target.checked })} />
              Pin to top
            </label>
            <button onClick={handleCreate} className="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Publish</button>
          </div>
        </div>
      )}

      {loading ? (
        <p className="text-sm text-slate-500">Loading notices...</p>
      ) : communications.length === 0 ? (
        <div className="py-8 text-center text-sm text-slate-500">No notices found.</div>
      ) : (
        <div className="space-y-4 max-h-[60vh] overflow-y-auto pr-2">
          {communications.map((comm) => (
            <div key={comm.communication_id} className={`rounded-lg border p-4 transition ${comm.is_pinned ? 'border-indigo-300 bg-indigo-50/30 dark:border-indigo-900/50 dark:bg-indigo-900/10' : 'border-slate-200 bg-white hover:shadow-sm dark:border-slate-800 dark:bg-slate-900'}`}>
              <div className="flex items-start justify-between mb-2">
                <div className="flex items-center gap-2">
                  {comm.is_pinned && <span className="text-indigo-600">📌</span>}
                  <h3 className="font-bold text-slate-900 dark:text-white">{comm.title}</h3>
                </div>
                <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider ${getTypeBadge(comm.type)}`}>{comm.type}</span>
              </div>
              <p className="text-sm text-slate-600 dark:text-slate-400 whitespace-pre-wrap mb-3">{comm.message}</p>
              <div className="flex items-center justify-between text-xs text-slate-500 border-t border-slate-100 dark:border-slate-800 pt-3">
                <span>Audience: <strong>{comm.target_audience}</strong></span>
                <span>Published: {comm.publish_date}</span>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
