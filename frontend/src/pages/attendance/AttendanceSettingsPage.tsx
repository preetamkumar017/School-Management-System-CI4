import { useState, useEffect } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { inputClass, labelClass, primaryButtonClass } from "../../components/ui/form";

interface Config {
  setting_key: string;
  setting_value: string;
  data_type: "String" | "Number" | "Boolean" | "JSON";
}

export default function AttendanceSettingsPage() {
  const [configs, setConfigs] = useState<Record<string, Config>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  const fetchConfigs = async () => {
    try {
      setLoading(true);
      const res = await api.get<{ data: Config[] }>("/administration/configurations?module=Attendance");
      const map: Record<string, Config> = {};
      res.data.data.forEach((c) => (map[c.setting_key] = c));
      setConfigs(map);
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchConfigs();
  }, []);

  const handleChange = (key: string, value: string) => {
    setConfigs((prev) => ({
      ...prev,
      [key]: { ...prev[key], setting_value: value },
    }));
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setError("");
    setSuccess("");
    try {
      const promises = Object.values(configs).map((c) =>
        api.patch(`/administration/configurations/${encodeURIComponent(c.setting_key)}`, {
          setting_value: c.setting_value,
        })
      );
      await Promise.all(promises);
      setSuccess("Attendance configurations saved successfully!");
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <p className="text-slate-500">Loading settings...</p>;

  return (
    <div className="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <h2 className="mb-6 text-lg font-bold text-slate-900 dark:text-white">Staff Attendance Rules</h2>

      {error && <div className="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/30 dark:text-red-400">{error}</div>}
      {success && <div className="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-600 dark:bg-green-900/30 dark:text-green-400">{success}</div>}

      <form onSubmit={handleSave} className="space-y-6">
        
        {/* Overtime Toggle */}
        <div className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
          <div>
            <h3 className="font-semibold text-slate-800 dark:text-slate-200">Enable Overtime</h3>
            <p className="text-sm text-slate-500 dark:text-slate-400">Calculate extra hours beyond full day threshold as overtime.</p>
          </div>
          <label className="relative inline-flex items-center cursor-pointer">
            <input 
              type="checkbox" 
              className="sr-only peer"
              checked={configs["attendance.overtime_enabled"]?.setting_value === "true"}
              onChange={(e) => handleChange("attendance.overtime_enabled", e.target.checked ? "true" : "false")}
            />
            <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
          </label>
        </div>

        <div className="grid grid-cols-2 gap-4">
          {/* Shift Timings */}
          <div>
            <label className={labelClass}>Standard Shift Start</label>
            <input
              type="time"
              className={inputClass}
              value={configs["attendance.standard_shift_start"]?.setting_value || ""}
              onChange={(e) => handleChange("attendance.standard_shift_start", e.target.value)}
            />
          </div>
          <div>
            <label className={labelClass}>Standard Shift End</label>
            <input
              type="time"
              className={inputClass}
              value={configs["attendance.standard_shift_end"]?.setting_value || ""}
              onChange={(e) => handleChange("attendance.standard_shift_end", e.target.value)}
            />
          </div>

          {/* Grace Periods */}
          <div>
            <label className={labelClass}>Late Coming Grace (Mins)</label>
            <input
              type="number"
              className={inputClass}
              value={configs["attendance.late_coming_grace_minutes"]?.setting_value || ""}
              onChange={(e) => handleChange("attendance.late_coming_grace_minutes", e.target.value)}
            />
          </div>
          <div>
            <label className={labelClass}>Early Leaving Grace (Mins)</label>
            <input
              type="number"
              className={inputClass}
              value={configs["attendance.early_leaving_grace_minutes"]?.setting_value || ""}
              onChange={(e) => handleChange("attendance.early_leaving_grace_minutes", e.target.value)}
            />
          </div>

          {/* Thresholds */}
          <div>
            <label className={labelClass}>Half Day Threshold (Hours)</label>
            <input
              type="number"
              step="0.5"
              className={inputClass}
              value={configs["attendance.half_day_threshold_hours"]?.setting_value || ""}
              onChange={(e) => handleChange("attendance.half_day_threshold_hours", e.target.value)}
            />
          </div>
          <div>
            <label className={labelClass}>Full Day Threshold (Hours)</label>
            <input
              type="number"
              step="0.5"
              className={inputClass}
              value={configs["attendance.full_day_threshold_hours"]?.setting_value || ""}
              onChange={(e) => handleChange("attendance.full_day_threshold_hours", e.target.value)}
            />
          </div>
        </div>

        <div className="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
          <button type="submit" disabled={saving} className={primaryButtonClass}>
            {saving ? "Saving..." : "Save Settings"}
          </button>
        </div>
      </form>
    </div>
  );
}
