import { useEffect, useState } from "react";
import { api } from "../../lib/api";

interface Holiday {
  holiday_id: number;
  holiday_date: string;
  name: string;
  type: "Gazetted" | "Restricted" | "School";
  description?: string;
  is_recurring: number;
}

const TYPE_COLORS: Record<string, string> = {
  Gazetted:   "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300",
  Restricted: "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300",
  School:     "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300",
};

const MONTHS = [
  "Jan","Feb","Mar","Apr","May","Jun",
  "Jul","Aug","Sep","Oct","Nov","Dec",
];

const EMPTY_FORM: {
  holiday_date: string;
  name: string;
  type: "Gazetted" | "Restricted" | "School";
  description: string;
  is_recurring: boolean;
} = {
  holiday_date: "",
  name: "",
  type: "Gazetted",
  description: "",
  is_recurring: false,
};

export default function HolidaysPage() {
  const [year, setYear] = useState(2026);
  const [holidays, setHolidays] = useState<Holiday[]>([]);
  const [loading, setLoading] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<Holiday | null>(null);
  const [form, setForm] = useState({ ...EMPTY_FORM });
  const [saving, setSaving] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");

  // Nice confirmation state
  const [deleteConfirm, setDeleteConfirm] = useState<{ id: number; name: string } | null>(null);

  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  const load = async () => {
    setLoading(true);
    setErrorMsg(null);
    try {
      const res = await api.get(`/hr-payroll/holidays?year=${year}`);
      setHolidays(res.data?.data ?? []);
    } catch (err: any) {
      setHolidays([]);
      setErrorMsg(err?.response?.data?.error?.message ?? err?.message ?? "Failed to load holidays");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, [year]);

  const openAdd = () => {
    setEditing(null);
    setForm({ ...EMPTY_FORM, holiday_date: `${year}-01-01` });
    setShowForm(true);
  };

  const openEdit = (h: Holiday) => {
    setEditing(h);
    setForm({
      holiday_date: h.holiday_date,
      name: h.name,
      type: h.type,
      description: h.description ?? "",
      is_recurring: !!h.is_recurring,
    });
    setShowForm(true);
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      const payload = {
        ...form,
        is_recurring: form.is_recurring ? 1 : 0,
      };
      if (editing) {
        await api.patch(`/hr-payroll/holidays/${editing.holiday_id}`, payload);
      } else {
        await api.post("/hr-payroll/holidays", payload);
      }
      setShowForm(false);
      load();
    } catch (e: any) {
      alert(e?.message ?? "Save failed");
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = (id: number, name: string) => {
    setDeleteConfirm({ id, name });
  };

  const confirmDelete = async () => {
    if (!deleteConfirm) return;
    try {
      await api.delete(`/hr-payroll/holidays/${deleteConfirm.id}`);
      setDeleteConfirm(null);
      load();
    } catch (err: any) {
      alert(err?.response?.data?.error?.message ?? "Delete failed");
    }
  };

  // Filtered list based on search
  const filteredHolidays = holidays.filter((h) =>
    h.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    (h.description ?? "").toLowerCase().includes(searchQuery.toLowerCase()) ||
    h.type.toLowerCase().includes(searchQuery.toLowerCase())
  );

  // Group by month for calendar-style display
  const byMonth: Record<number, Holiday[]> = {};
  filteredHolidays.forEach((h) => {
    const m = parseInt(h.holiday_date.split("-")[1], 10) - 1;
    if (!byMonth[m]) byMonth[m] = [];
    byMonth[m].push(h);
  });

  const totalGazetted  = holidays.filter((h) => h.type === "Gazetted").length;
  const totalRestricted = holidays.filter((h) => h.type === "Restricted").length;
  const totalSchool    = holidays.filter((h) => h.type === "School").length;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold text-slate-900 dark:text-slate-100">
            🗓️ School Holiday Calendar
          </h2>
          <p className="text-sm text-slate-500 dark:text-slate-400">
            Holidays marked here are automatically excluded from leave day calculations (when Sandwich Rule is OFF)
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          {/* Live Search Input */}
          <div className="relative">
            <input
              type="text"
              placeholder="Search holidays..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-60 rounded-lg border border-slate-200 bg-white px-3 py-2 pl-8 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            />
            <span className="absolute left-2.5 top-2.5 text-slate-400">🔍</span>
          </div>

          <select
            value={year}
            onChange={(e) => setYear(Number(e.target.value))}
            className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
          >
            {[2024, 2025, 2026, 2027].map((y) => (
              <option key={y} value={y}>{y}</option>
            ))}
          </select>
          <button
            id="btn-add-holiday"
            onClick={openAdd}
            className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
          >
            + Add Holiday
          </button>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-3 gap-4">
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
          <div className="text-2xl font-bold text-red-700 dark:text-red-300">{totalGazetted}</div>
          <div className="text-sm text-red-600 dark:text-red-400">Gazetted Holidays</div>
        </div>
        <div className="rounded-xl border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
          <div className="text-2xl font-bold text-yellow-700 dark:text-yellow-300">{totalRestricted}</div>
          <div className="text-sm text-yellow-600 dark:text-yellow-400">Restricted Holidays</div>
        </div>
        <div className="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
          <div className="text-2xl font-bold text-blue-700 dark:text-blue-300">{totalSchool}</div>
          <div className="text-sm text-blue-600 dark:text-blue-400">School Holidays</div>
        </div>
      </div>

      {errorMsg && (
        <div role="alert" className="rounded-xl bg-red-50 p-4 text-sm text-red-600 dark:bg-red-950/20 dark:text-red-400">
          ⚠️ {errorMsg}
        </div>
      )}

      {/* Calendar by Month */}
      {loading ? (
        <div className="py-12 text-center text-slate-400">Loading...</div>
      ) : filteredHolidays.length === 0 ? (
        <div className="rounded-xl border border-dashed border-slate-300 py-16 text-center dark:border-slate-700">
          <div className="text-4xl">📅</div>
          <p className="mt-2 text-slate-500">Koi matching holiday nahi mili {year} mein.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
          {MONTHS.map((mon, idx) => {
            const monthHolidays = byMonth[idx] ?? [];
            if (monthHolidays.length === 0) return null;
            return (
              <div
                key={mon}
                className="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800"
              >
                <h3 className="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                  {mon} {year}
                </h3>
                <div className="space-y-2">
                  {monthHolidays.map((h) => {
                    const d = new Date(h.holiday_date);
                    const dayName = d.toLocaleDateString("en-IN", { weekday: "short" });
                    const dayNum  = d.getDate();
                    return (
                      <div
                        key={h.holiday_id}
                        className="flex items-center gap-3 rounded-lg bg-slate-50 p-2 dark:bg-slate-900/50"
                      >
                        <div className="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-lg bg-slate-900 dark:bg-slate-100">
                          <span className="text-xs font-bold text-slate-100 dark:text-slate-900">{dayNum}</span>
                          <span className="text-[10px] text-slate-400 dark:text-slate-600">{dayName}</span>
                        </div>
                        <div className="min-w-0 flex-1">
                          <div className="truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                            {h.name}
                            {h.is_recurring ? " 🔁" : ""}
                          </div>
                          <span className={`inline-block rounded px-1.5 py-0.5 text-[10px] font-medium ${TYPE_COLORS[h.type]}`}>
                            {h.type}
                          </span>
                        </div>
                        <div className="flex gap-1">
                          <button
                            onClick={() => openEdit(h)}
                            className="rounded p-1 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
                            title="Edit"
                          >✏️</button>
                          <button
                            onClick={() => handleDelete(h.holiday_id, h.name)}
                            className="rounded p-1 text-slate-400 hover:text-red-600"
                            title="Delete"
                          >🗑️</button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Full Table (list view below calendar) */}
      {filteredHolidays.length > 0 && (
        <div className="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-800/60">
              <tr>
                <th className="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300 w-16">S.No.</th>
                <th className="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Date</th>
                <th className="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Holiday</th>
                <th className="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Type</th>
                <th className="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-300">Description</th>
                <th className="px-4 py-3 text-center font-medium text-slate-600 dark:text-slate-300">Recurring</th>
                <th className="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-700/50">
              {filteredHolidays.map((h, index) => (
                <tr key={h.holiday_id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                  <td className="px-4 py-3 text-slate-400 font-medium">{index + 1}</td>
                  <td className="px-4 py-3 font-mono text-slate-700 dark:text-slate-300">
                    {new Date(h.holiday_date).toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" })}
                  </td>
                  <td className="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">{h.name}</td>
                  <td className="px-4 py-3">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${TYPE_COLORS[h.type]}`}>
                      {h.type}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-slate-500 dark:text-slate-400">{h.description ?? "—"}</td>
                  <td className="px-4 py-3 text-center">{h.is_recurring ? "✅" : "—"}</td>
                  <td className="px-4 py-3 text-right">
                    <button onClick={() => openEdit(h)} className="mr-2 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">✏️</button>
                    <button onClick={() => handleDelete(h.holiday_id, h.name)} className="text-slate-400 hover:text-red-600">🗑️</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Add/Edit Modal */}
      {showForm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
          <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900">
            <h3 className="mb-5 text-lg font-bold text-slate-900 dark:text-slate-100">
              {editing ? "✏️ Holiday Edit Karo" : "➕ Naya Holiday Add Karo"}
            </h3>

            <div className="space-y-4">
              <div>
                <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Date</label>
                <input
                  type="date"
                  value={form.holiday_date}
                  onChange={(e) => setForm({ ...form, holiday_date: e.target.value })}
                  className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                />
              </div>

              <div>
                <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Holiday Name</label>
                <input
                  type="text"
                  placeholder="e.g. Diwali"
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                />
              </div>

              <div>
                <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Type</label>
                <select
                  value={form.type}
                  onChange={(e) => setForm({ ...form, type: e.target.value as any })}
                  className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                >
                  <option value="Gazetted">Gazetted (National/Sarkari)</option>
                  <option value="Restricted">Restricted (Optional)</option>
                  <option value="School">School Holiday</option>
                </select>
              </div>

              <div>
                <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Description (optional)</label>
                <input
                  type="text"
                  placeholder="e.g. Festival of Lights"
                  value={form.description}
                  onChange={(e) => setForm({ ...form, description: e.target.value })}
                  className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                />
              </div>

              <label className="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                <input
                  type="checkbox"
                  checked={form.is_recurring}
                  onChange={(e) => setForm({ ...form, is_recurring: e.target.checked })}
                  className="h-4 w-4 rounded"
                />
                <div>
                  <div className="text-sm font-medium text-slate-900 dark:text-slate-100">Recurring Holiday 🔁</div>
                  <div className="text-xs text-slate-500">Har saal repeat hota hai (e.g. Independence Day)</div>
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
                disabled={saving || !form.holiday_date || !form.name}
                className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700 disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900"
              >
                {saving ? "Saving..." : editing ? "Update" : "Add Holiday"}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Custom Delete Confirmation Modal */}
      {deleteConfirm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
          <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900">
            <div className="text-center">
              <span className="inline-block rounded-full bg-red-100 p-3 text-red-600 dark:bg-red-950/30 dark:text-red-400 text-2xl mb-4">
                ⚠️
              </span>
              <h3 className="text-lg font-bold text-slate-900 dark:text-slate-100 mb-2">
                Holiday Delete Karein?
              </h3>
              <p className="text-sm text-slate-500 dark:text-slate-400 mb-6">
                Kya aap sach mein <strong>"{deleteConfirm.name}"</strong> ko list se hatana chahte hain?
              </p>
            </div>

            <div className="flex justify-center gap-3">
              <button
                onClick={() => setDeleteConfirm(null)}
                className="w-1/2 rounded-lg border border-slate-200 py-2.5 text-sm font-semibold dark:border-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
              >
                Nahi, Cancel
              </button>
              <button
                onClick={confirmDelete}
                className="w-1/2 rounded-lg bg-red-600 py-2.5 text-sm font-semibold text-white hover:bg-red-700 transition"
              >
                Haan, Delete
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
