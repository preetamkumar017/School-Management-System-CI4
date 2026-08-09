import { useEffect, useState } from "react";
import { api } from "../../lib/api";

interface LeaveType {
  leave_type_id: number;
  code: string;
  name: string;
  description?: string;
  max_days_per_year: number;
  is_paid: number;
  balance_check: number;
  sandwich_rule: 0 | 1 | null;
  color_hex: string;
  sort_order: number;
  is_active: number;
}

const SANDWICH_OPTIONS = [
  { value: "null",  label: "🔗 Inherit Global Setting" },
  { value: "0",     label: "📅 Working Days Only (skip Sundays & holidays)" },
  { value: "1",     label: "📆 Calendar Days (Sandwich Rule — count all days)" },
];

const EMPTY_FORM = {
  code: "",
  name: "",
  description: "",
  max_days_per_year: 12,
  is_paid: true,
  balance_check: true,
  sandwich_rule: "null" as "null" | "0" | "1",
  color_hex: "#6366f1",
  sort_order: 0,
  is_active: true,
};

export default function LeaveTypesPage() {
  const [leaveTypes, setLeaveTypes] = useState<LeaveType[]>([]);
  const [loading, setLoading] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<LeaveType | null>(null);
  const [form, setForm] = useState({ ...EMPTY_FORM });
  const [saving, setSaving] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get("/hr-payroll/leave-types");
      setLeaveTypes(res.data?.data ?? []);
    } catch {
      setLeaveTypes([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const openAdd = () => {
    setEditing(null);
    setForm({ ...EMPTY_FORM, sort_order: leaveTypes.length + 1 });
    setShowForm(true);
  };

  const openEdit = (lt: LeaveType) => {
    setEditing(lt);
    setForm({
      code: lt.code,
      name: lt.name,
      description: lt.description ?? "",
      max_days_per_year: lt.max_days_per_year,
      is_paid: !!lt.is_paid,
      balance_check: !!lt.balance_check,
      sandwich_rule: lt.sandwich_rule === null ? "null" : String(lt.sandwich_rule) as "0" | "1",
      color_hex: lt.color_hex,
      sort_order: lt.sort_order,
      is_active: !!lt.is_active,
    });
    setShowForm(true);
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      const payload = {
        ...form,
        max_days_per_year: Number(form.max_days_per_year),
        is_paid: form.is_paid ? 1 : 0,
        balance_check: form.balance_check ? 1 : 0,
        sandwich_rule: form.sandwich_rule === "null" ? null : Number(form.sandwich_rule),
        is_active: form.is_active ? 1 : 0,
        sort_order: Number(form.sort_order),
      };

      if (editing) {
        await api.patch(`/hr-payroll/leave-types/${editing.leave_type_id}`, payload);
      } else {
        await api.post("/hr-payroll/leave-types", payload);
      }
      setShowForm(false);
      load();
    } catch (e: any) {
      const msg = e?.response?.data?.error?.message ?? e?.message ?? "Save failed";
      alert(msg);
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (lt: LeaveType) => {
    if (!window.confirm(`"${lt.name}" leave type delete karna chahte hain?`)) return;
    await api.delete(`/hr-payroll/leave-types/${lt.leave_type_id}`);
    load();
  };

  const sandwichLabel = (rule: 0 | 1 | null) => {
    if (rule === null) return { text: "Inherit Global", cls: "bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300" };
    if (rule === 0)    return { text: "Working Days Only", cls: "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300" };
    return              { text: "Calendar Days (Sandwich)", cls: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300" };
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold text-slate-900 dark:text-slate-100">
            📋 Leave Types Configuration
          </h2>
          <p className="text-sm text-slate-500 dark:text-slate-400">
            Har school apne leave types manage kar sakta hai — codes, max days, paid/unpaid, aur sandwich rule per type
          </p>
        </div>
        <button
          id="btn-add-leave-type"
          onClick={openAdd}
          className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
        >
          + Add Leave Type
        </button>
      </div>

      {/* Summary */}
      <div className="grid grid-cols-3 gap-4">
        <div className="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
          <div className="text-2xl font-bold text-slate-900 dark:text-slate-100">{leaveTypes.length}</div>
          <div className="text-sm text-slate-500">Total Leave Types</div>
        </div>
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-900/20">
          <div className="text-2xl font-bold text-emerald-700 dark:text-emerald-300">
            {leaveTypes.filter((l) => l.is_paid).length}
          </div>
          <div className="text-sm text-emerald-600">Paid Leave Types</div>
        </div>
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
          <div className="text-2xl font-bold text-red-700 dark:text-red-300">
            {leaveTypes.filter((l) => !l.is_paid).length}
          </div>
          <div className="text-sm text-red-600">Unpaid Leave Types</div>
        </div>
      </div>

      {/* Table */}
      {loading ? (
        <div className="py-12 text-center text-slate-400">Loading...</div>
      ) : (
        <div className="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-800/60">
              <tr>
                <th className="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Code</th>
                <th className="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Leave Type</th>
                <th className="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Max Days/Year</th>
                <th className="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Paid</th>
                <th className="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Balance Check</th>
                <th className="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Day Calculation Rule</th>
                <th className="px-4 py-3 text-center font-medium text-slate-600 dark:text-slate-300">Active</th>
                <th className="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-700/50">
              {leaveTypes.map((lt) => {
                const sw = sandwichLabel(lt.sandwich_rule);
                return (
                  <tr key={lt.leave_type_id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-2">
                        <span
                          className="inline-block h-3 w-3 rounded-full"
                          style={{ backgroundColor: lt.color_hex }}
                        />
                        <span className="font-mono font-bold text-slate-900 dark:text-slate-100">{lt.code}</span>
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <div className="font-medium text-slate-900 dark:text-slate-100">{lt.name}</div>
                      {lt.description && (
                        <div className="text-xs text-slate-500 dark:text-slate-400">{lt.description}</div>
                      )}
                    </td>
                    <td className="px-4 py-3 text-slate-700 dark:text-slate-300">
                      {lt.max_days_per_year === 0 ? (
                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs dark:bg-slate-700">Unlimited</span>
                      ) : (
                        <span className="font-medium">{lt.max_days_per_year} days</span>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      {lt.is_paid ? (
                        <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                          ✅ Paid
                        </span>
                      ) : (
                        <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-300">
                          ❌ Unpaid
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-slate-500 dark:text-slate-400">
                      {lt.balance_check ? "Yes" : "No (Unlimited)"}
                    </td>
                    <td className="px-4 py-3">
                      <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${sw.cls}`}>
                        {sw.text}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-center">
                      {lt.is_active ? "✅" : "❌"}
                    </td>
                    <td className="px-4 py-3 text-right">
                      <button onClick={() => openEdit(lt)} className="mr-2 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">✏️</button>
                      <button onClick={() => handleDelete(lt)} className="text-slate-400 hover:text-red-600">🗑️</button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {/* Sandwich Rule Info */}
      <div className="rounded-xl border border-blue-100 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-900/20">
        <h4 className="mb-2 text-sm font-semibold text-blue-900 dark:text-blue-200">ℹ️ Day Calculation Rule — Kaise kaam karta hai?</h4>
        <div className="space-y-1 text-xs text-blue-700 dark:text-blue-300">
          <p><strong>🔗 Inherit Global:</strong> Jo global setting set ki hai HR Settings mein wahi lagegi</p>
          <p><strong>📅 Working Days Only:</strong> CL mein 14-18 Aug liya toh 15 Aug (holiday) skip — sirf 4 working days count</p>
          <p><strong>📆 Calendar Days (Sandwich):</strong> EL mein 14-18 Aug = 5 din gine jaayenge (Sundays + holidays bhi count)</p>
        </div>
      </div>

      {/* Add/Edit Modal */}
      {showForm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
          <div className="w-full max-w-2xl rounded-2xl bg-white shadow-2xl dark:bg-slate-900 overflow-y-auto max-h-[90vh]">
            <div className="p-6">
              <h3 className="mb-5 text-lg font-bold text-slate-900 dark:text-slate-100">
                {editing ? "✏️ Leave Type Edit Karo" : "➕ Naya Leave Type Add Karo"}
              </h3>

              <div className="grid grid-cols-2 gap-4">
                {/* Code */}
                <div>
                  <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                    Code <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    placeholder="e.g. PL, CL, STUDY"
                    value={form.code}
                    onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })}
                    disabled={!!editing}
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono uppercase dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 disabled:opacity-50"
                  />
                  {editing && <p className="mt-1 text-xs text-slate-400">Code change nahi kar sakte (existing requests affected)</p>}
                </div>

                {/* Name */}
                <div>
                  <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                    Name <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    placeholder="e.g. Paternity Leave"
                    value={form.name}
                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                  />
                </div>

                {/* Max Days */}
                <div>
                  <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                    Max Days / Year
                  </label>
                  <input
                    type="number"
                    min={0}
                    placeholder="0 = Unlimited"
                    value={form.max_days_per_year}
                    onChange={(e) => setForm({ ...form, max_days_per_year: Number(e.target.value) })}
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                  />
                  <p className="mt-1 text-xs text-slate-400">0 = Unlimited (e.g. LWP, DL)</p>
                </div>

                {/* Sort Order */}
                <div>
                  <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Sort Order</label>
                  <input
                    type="number"
                    min={0}
                    value={form.sort_order}
                    onChange={(e) => setForm({ ...form, sort_order: Number(e.target.value) })}
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                  />
                </div>

                {/* Color */}
                <div>
                  <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Badge Color</label>
                  <div className="flex items-center gap-2">
                    <input
                      type="color"
                      value={form.color_hex}
                      onChange={(e) => setForm({ ...form, color_hex: e.target.value })}
                      className="h-10 w-16 cursor-pointer rounded-lg border border-slate-200 p-1 dark:border-slate-700"
                    />
                    <span
                      className="rounded-full px-3 py-1 text-xs font-medium text-white"
                      style={{ backgroundColor: form.color_hex }}
                    >
                      {form.code || "PREVIEW"}
                    </span>
                  </div>
                </div>

                {/* Description */}
                <div>
                  <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Description</label>
                  <input
                    type="text"
                    placeholder="Brief description..."
                    value={form.description}
                    onChange={(e) => setForm({ ...form, description: e.target.value })}
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                  />
                </div>
              </div>

              {/* Day Calculation Rule — Full width */}
              <div className="mt-4">
                <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                  📅 Day Calculation Rule (Sandwich Rule)
                </label>
                <select
                  value={form.sandwich_rule}
                  onChange={(e) => setForm({ ...form, sandwich_rule: e.target.value as "null" | "0" | "1" })}
                  className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                >
                  {SANDWICH_OPTIONS.map((o) => (
                    <option key={o.value} value={o.value}>{o.label}</option>
                  ))}
                </select>
              </div>

              {/* Toggle checkboxes */}
              <div className="mt-4 grid grid-cols-3 gap-3">
                <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                  <input
                    type="checkbox"
                    checked={form.is_paid}
                    onChange={(e) => setForm({ ...form, is_paid: e.target.checked })}
                    className="h-4 w-4 rounded"
                  />
                  <div>
                    <div className="text-sm font-medium text-slate-900 dark:text-slate-100">Paid Leave</div>
                    <div className="text-xs text-slate-500">Salary mein koi deduction nahi</div>
                  </div>
                </label>

                <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                  <input
                    type="checkbox"
                    checked={form.balance_check}
                    onChange={(e) => setForm({ ...form, balance_check: e.target.checked })}
                    className="h-4 w-4 rounded"
                  />
                  <div>
                    <div className="text-sm font-medium text-slate-900 dark:text-slate-100">Balance Check</div>
                    <div className="text-xs text-slate-500">Exceed hone par block</div>
                  </div>
                </label>

                <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                  <input
                    type="checkbox"
                    checked={form.is_active}
                    onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                    className="h-4 w-4 rounded"
                  />
                  <div>
                    <div className="text-sm font-medium text-slate-900 dark:text-slate-100">Active</div>
                    <div className="text-xs text-slate-500">Employees apply kar sakte hain</div>
                  </div>
                </label>
              </div>

              <div className="mt-6 flex justify-end gap-3">
                <button
                  onClick={() => setShowForm(false)}
                  className="rounded-lg border border-slate-200 px-4 py-2 text-sm dark:border-slate-700 dark:text-slate-300"
                >
                  Cancel
                </button>
                <button
                  onClick={handleSave}
                  disabled={saving || !form.code || !form.name}
                  className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700 disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900"
                >
                  {saving ? "Saving..." : editing ? "Update" : "Add Leave Type"}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
