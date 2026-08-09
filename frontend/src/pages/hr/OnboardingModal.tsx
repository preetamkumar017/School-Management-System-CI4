import { useState, useEffect } from "react";
import Modal from "../../components/ui/Modal";
import { api, apiErrorMessage } from "../../lib/api";
import type { Employee } from "./EmployeesPage";

interface ChecklistItem {
  checklist_id: number;
  item_name: string;
  is_done: boolean;
  done_at: string | null;
  done_by: string | null;
  remarks: string | null;
  sort_order: number;
}

interface ChecklistData {
  items: ChecklistItem[];
  done: number;
  total: number;
  percent: number;
}

interface Document {
  name: string;
  url?: string;
  status?: "Pending" | "Verified" | "Rejected";
  remark?: string;
  verified_at?: string;
}

const STATUS_COLORS: Record<string, string> = {
  Verified: "bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400",
  Rejected: "bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400",
  Pending: "bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400",
};

export default function OnboardingModal({
  employee,
  onClose,
}: {
  employee: Employee;
  onClose: () => void;
}) {
  const [tab, setTab] = useState<"checklist" | "documents">("checklist");
  const [checklist, setChecklist] = useState<ChecklistData | null>(null);
  const [docs, setDocs] = useState<Document[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [togglingId, setTogglingId] = useState<number | null>(null);
  const [remarksInput, setRemarksInput] = useState<Record<number, string>>({});
  const [verifyingIdx, setVerifyingIdx] = useState<number | null>(null);
  const [verifyRemark, setVerifyRemark] = useState<Record<number, string>>({});

  async function loadChecklist() {
    try {
      const res = await api.get<{ success: boolean; data: ChecklistData }>(
        `/hr-payroll/employees/${employee.employee_id}/checklist`
      );
      setChecklist(res.data.data);
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    setDocs(
      (employee.documents_json || []).map((d: any) => ({
        name: d.name || "",
        url: d.url || "",
        status: d.status || "Pending",
        remark: d.remark || "",
        verified_at: d.verified_at || null,
      }))
    );
    loadChecklist();
  }, []);

  async function toggleItem(item: ChecklistItem) {
    setTogglingId(item.checklist_id);
    try {
      const res = await api.patch<{ success: boolean; data: ChecklistItem }>(
        `/hr-payroll/employees/${employee.employee_id}/checklist/${item.checklist_id}`,
        {
          is_done: !item.is_done,
          remarks: remarksInput[item.checklist_id] ?? item.remarks ?? "",
        }
      );
      setChecklist((prev) => {
        if (!prev) return prev;
        const updated = prev.items.map((i) =>
          i.checklist_id === item.checklist_id ? { ...i, ...res.data.data } : i
        );
        const done = updated.filter((i) => i.is_done).length;
        return {
          ...prev,
          items: updated,
          done,
          percent: Math.round((done / prev.total) * 100),
        };
      });
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setTogglingId(null);
    }
  }

  async function verifyDocument(idx: number, status: "Verified" | "Rejected") {
    setVerifyingIdx(idx);
    try {
      const res = await api.patch<{ success: boolean; data: { documents_json: Document[] } }>(
        `/hr-payroll/employees/${employee.employee_id}/documents`,
        {
          updates: [{ index: idx, status, remark: verifyRemark[idx] ?? "" }],
        }
      );
      setDocs(res.data.data.documents_json || docs);
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setVerifyingIdx(null);
    }
  }

  const [downloading, setDownloading] = useState<string | null>(null);

  async function downloadPdf(type: "appointment-letter" | "id-card") {
    setDownloading(type);
    try {
      const res = await api.get(
        `/hr-payroll/employees/${employee.employee_id}/${type}`,
        { responseType: "blob" }
      );
      const blob = new Blob([res.data as BlobPart], { type: "application/pdf" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `${type}-${employee.employee_code}.pdf`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setDownloading(null);
    }
  }

  const progressColor =
    checklist?.percent === 100
      ? "bg-green-500"
      : checklist && checklist.percent >= 60
      ? "bg-blue-500"
      : "bg-amber-500";

  return (
    <Modal title={`Onboarding — ${employee.full_name} (${employee.employee_code})`} onClose={onClose} maxWidth="2xl">
      {/* Download buttons */}
      <div className="flex gap-2 mb-4 pb-3 border-b border-slate-100 dark:border-slate-800">
        <button
          onClick={() => downloadPdf("appointment-letter")}
          disabled={downloading !== null}
          className="flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 transition disabled:opacity-60 disabled:cursor-not-allowed"
        >
          {downloading === "appointment-letter" ? "⏳ Generating…" : "📄 Download Appointment Letter"}
        </button>
        <button
          onClick={() => downloadPdf("id-card")}
          disabled={downloading !== null}
          className="flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 transition disabled:opacity-60 disabled:cursor-not-allowed"
        >
          {downloading === "id-card" ? "⏳ Generating…" : "🪪 Download ID Card"}
        </button>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 mb-4 border-b border-slate-200 dark:border-slate-700">
        {(["checklist", "documents"] as const).map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`px-4 py-1.5 text-xs font-semibold capitalize transition border-b-2 -mb-px ${
              tab === t
                ? "border-indigo-500 text-indigo-600 dark:text-indigo-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            {t === "checklist" ? `Joining Checklist` : `Document Verification`}
            {t === "checklist" && checklist && (
              <span className="ml-1.5 rounded-full bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-[10px]">
                {checklist.done}/{checklist.total}
              </span>
            )}
            {t === "documents" && (
              <span className="ml-1.5 rounded-full bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-[10px]">
                {docs.length}
              </span>
            )}
          </button>
        ))}
      </div>

      {error && (
        <p className="mb-3 text-xs text-red-600 dark:text-red-400 font-semibold">{error}</p>
      )}

      {/* ── CHECKLIST TAB ── */}
      {tab === "checklist" && (
        <div>
          {isLoading ? (
            <p className="text-xs text-slate-400 text-center py-6">Loading checklist…</p>
          ) : checklist ? (
            <>
              {/* Progress bar */}
              <div className="mb-4">
                <div className="flex justify-between text-xs mb-1">
                  <span className="font-semibold text-slate-700 dark:text-slate-300">
                    Onboarding Progress
                  </span>
                  <span className="font-bold text-slate-900 dark:text-slate-100">
                    {checklist.percent}%
                  </span>
                </div>
                <div className="h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                  <div
                    className={`h-full rounded-full transition-all duration-500 ${progressColor}`}
                    style={{ width: `${checklist.percent}%` }}
                  />
                </div>
                <p className="text-[10px] text-slate-400 mt-1">
                  {checklist.done} of {checklist.total} items completed
                </p>
              </div>

              <div className="space-y-2 max-h-72 overflow-y-auto pr-1">
                {checklist.items.map((item) => (
                  <div
                    key={item.checklist_id}
                    className={`rounded-lg border p-3 transition ${
                      item.is_done
                        ? "border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950/20"
                        : "border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
                    }`}
                  >
                    <div className="flex items-start gap-3">
                      <button
                        disabled={togglingId === item.checklist_id}
                        onClick={() => toggleItem(item)}
                        className={`mt-0.5 h-5 w-5 flex-shrink-0 rounded border-2 flex items-center justify-center transition ${
                          item.is_done
                            ? "border-green-500 bg-green-500 text-white"
                            : "border-slate-300 dark:border-slate-600"
                        }`}
                      >
                        {item.is_done && <span className="text-[10px] font-bold">✓</span>}
                      </button>
                      <div className="flex-1 min-w-0">
                        <p className={`text-xs font-semibold ${item.is_done ? "line-through text-slate-400" : "text-slate-800 dark:text-slate-200"}`}>
                          {item.item_name}
                        </p>
                        {item.done_at && (
                          <p className="text-[10px] text-green-600 dark:text-green-400 mt-0.5">
                            Done on {item.done_at}
                          </p>
                        )}
                        {item.remarks && (
                          <p className="text-[10px] text-slate-500 mt-0.5 italic">{item.remarks}</p>
                        )}
                        {!item.is_done && (
                          <input
                            type="text"
                            placeholder="Add remark (optional)"
                            value={remarksInput[item.checklist_id] ?? ""}
                            onChange={(e) =>
                              setRemarksInput((prev) => ({
                                ...prev,
                                [item.checklist_id]: e.target.value,
                              }))
                            }
                            className="mt-1.5 w-full rounded border border-slate-200 dark:border-slate-700 bg-transparent px-2 py-1 text-[10px] outline-none focus:border-indigo-400"
                          />
                        )}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </>
          ) : (
            <p className="text-xs text-slate-400 text-center py-4">No checklist found.</p>
          )}
        </div>
      )}

      {/* ── DOCUMENTS TAB ── */}
      {tab === "documents" && (
        <div className="space-y-3 max-h-80 overflow-y-auto pr-1">
          {docs.length === 0 && (
            <p className="text-xs text-slate-400 text-center py-6 italic">
              No documents uploaded for this employee yet. Add documents in the Edit modal.
            </p>
          )}
          {docs.map((doc, idx) => (
            <div
              key={idx}
              className="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3"
            >
              <div className="flex items-start justify-between gap-3">
                <div className="flex-1 min-w-0">
                  <p className="text-xs font-semibold text-slate-800 dark:text-slate-200">{doc.name}</p>
                  {doc.url && (
                    <a
                      href={doc.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-[10px] text-indigo-600 dark:text-indigo-400 hover:underline"
                    >
                      View document ↗
                    </a>
                  )}
                  {doc.verified_at && (
                    <p className="text-[10px] text-slate-400 mt-0.5">Last updated: {doc.verified_at}</p>
                  )}
                  {doc.remark && (
                    <p className="text-[10px] text-slate-500 mt-0.5 italic">Remark: {doc.remark}</p>
                  )}
                </div>
                <span
                  className={`rounded-full px-2 py-0.5 text-[10px] font-bold flex-shrink-0 ${
                    STATUS_COLORS[doc.status ?? "Pending"] ?? STATUS_COLORS["Pending"]
                  }`}
                >
                  {doc.status ?? "Pending"}
                </span>
              </div>

              {/* Verify/Reject actions */}
              <div className="mt-2 flex items-center gap-2">
                <input
                  type="text"
                  placeholder="Remark (optional)"
                  value={verifyRemark[idx] ?? ""}
                  onChange={(e) =>
                    setVerifyRemark((prev) => ({ ...prev, [idx]: e.target.value }))
                  }
                  className="flex-1 rounded border border-slate-200 dark:border-slate-700 bg-transparent px-2 py-1 text-[10px] outline-none focus:border-indigo-400"
                />
                <button
                  disabled={verifyingIdx === idx}
                  onClick={() => verifyDocument(idx, "Verified")}
                  className="rounded px-2 py-1 text-[10px] font-semibold bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-950 dark:text-green-400 transition"
                >
                  ✓ Verify
                </button>
                <button
                  disabled={verifyingIdx === idx}
                  onClick={() => verifyDocument(idx, "Rejected")}
                  className="rounded px-2 py-1 text-[10px] font-semibold bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-950 dark:text-red-400 transition"
                >
                  ✕ Reject
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </Modal>
  );
}
