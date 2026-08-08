import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { useClasses, useSections, useSubjects } from "../../lib/academic";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";

export interface TimetableEntry {
  timetable_entry_id: number;
  section_id: number;
  subject_id: number;
  employee_id: number;
  day_of_week: string;
  period_no: number;
  room_id: string | null;
  version_no: number;
  status: "DRAFT" | "PUBLISHED";
}

const DAYS = ["MONDAY", "TUESDAY", "WEDNESDAY", "THURSDAY", "FRIDAY", "SATURDAY"];

interface FormState {
  subject_id: string;
  employee_id: string;
  day_of_week: string;
  period_no: string;
  room_id: string;
}

const EMPTY_FORM: FormState = { subject_id: "", employee_id: "", day_of_week: "MONDAY", period_no: "", room_id: "" };

export default function EntriesPage() {
  const { classes } = useClasses();
  const { subjects } = useSubjects();
  const [classId, setClassId] = useState<number | null>(null);
  const { sections } = useSections(classId);
  const [sectionId, setSectionId] = useState<number | null>(null);

  const [entries, setEntries] = useState<TimetableEntry[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function subjectName(id: number): string {
    return subjects.find((s) => s.subject_id === id)?.subject_name ?? `Subject #${id}`;
  }

  function reload() {
    if (sectionId === null) {
      setEntries([]);
      return;
    }
    setIsLoading(true);
    api
      .get<{ data: TimetableEntry[] }>("/timetable/entries", { params: { section_id: sectionId } })
      .then((response) => setEntries(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  useEffect(reload, [sectionId]);

  function openCreate() {
    setForm(EMPTY_FORM);
    setFormError(null);
    setIsCreating(true);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    if (sectionId === null) return;
    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.post("/timetable/entries", {
        section_id: sectionId,
        subject_id: Number(form.subject_id),
        employee_id: Number(form.employee_id),
        day_of_week: form.day_of_week,
        period_no: Number(form.period_no),
        room_id: form.room_id || null,
      });
      setIsCreating(false);
      reload();
    } catch (err) {
      setFormError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handlePublish(entry: TimetableEntry) {
    setMessage(null);
    try {
      await api.post(`/timetable/entries/${entry.timetable_entry_id}/publish`);
      reload();
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  async function handleRevise(entry: TimetableEntry) {
    const newPeriod = prompt("New period number:", String(entry.period_no));
    if (!newPeriod) return;
    setMessage(null);
    try {
      await api.post(`/timetable/entries/${entry.timetable_entry_id}/revise`, {
        subject_id: entry.subject_id,
        employee_id: entry.employee_id,
        day_of_week: entry.day_of_week,
        period_no: Number(newPeriod),
        room_id: entry.room_id,
      });
      reload();
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Timetable Entries</h2>
        {sectionId !== null && (
          <button type="button" onClick={openCreate} className={primaryButtonClass}>
            New Entry
          </button>
        )}
      </div>

      <div className="mb-4 flex gap-3">
        <select
          value={classId ?? ""}
          onChange={(e) => {
            setClassId(e.target.value ? Number(e.target.value) : null);
            setSectionId(null);
          }}
          className={`${inputClass} w-40`}
        >
          <option value="">Select class</option>
          {classes.map((c) => (
            <option key={c.class_id} value={c.class_id}>
              {c.class_name}
            </option>
          ))}
        </select>
        <select
          value={sectionId ?? ""}
          onChange={(e) => setSectionId(e.target.value ? Number(e.target.value) : null)}
          disabled={classId === null}
          className={`${inputClass} w-40`}
        >
          <option value="">Select section</option>
          {sections.map((s) => (
            <option key={s.section_id} value={s.section_id}>
              {s.section_name}
            </option>
          ))}
        </select>
      </div>

      {message && <p className="mb-3 text-sm text-red-600 dark:text-red-400">{message}</p>}
      {sectionId === null && <p className="text-sm text-slate-400">Pick a class and section.</p>}
      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {sectionId !== null && !isLoading && !error && (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th className="px-4 py-2 font-medium">Day</th>
                <th className="px-4 py-2 font-medium">Period</th>
                <th className="px-4 py-2 font-medium">Subject</th>
                <th className="px-4 py-2 font-medium">Teacher</th>
                <th className="px-4 py-2 font-medium">Room</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {entries.map((entry) => (
                <tr key={entry.timetable_entry_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{entry.day_of_week}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{entry.period_no}</td>
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{subjectName(entry.subject_id)}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">Employee #{entry.employee_id}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{entry.room_id ?? "—"}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">
                    {entry.status} (v{entry.version_no})
                  </td>
                  <td className="px-4 py-2 text-right">
                    {entry.status === "DRAFT" && (
                      <button
                        type="button"
                        onClick={() => handlePublish(entry)}
                        className="mr-2 text-xs text-slate-600 hover:underline dark:text-slate-400"
                      >
                        Publish
                      </button>
                    )}
                    <button
                      type="button"
                      onClick={() => handleRevise(entry)}
                      className="text-xs text-amber-700 hover:underline dark:text-amber-400"
                    >
                      Revise
                    </button>
                  </td>
                </tr>
              ))}
              {entries.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-4 py-6 text-center text-slate-400">
                    No timetable entries for this section.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && sectionId !== null && (
        <Modal title="New Timetable Entry" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className={labelClass}>Subject</label>
              <select
                required
                value={form.subject_id}
                onChange={(e) => setForm({ ...form, subject_id: e.target.value })}
                className={inputClass}
              >
                <option value="" disabled>
                  Select subject
                </option>
                {subjects.map((s) => (
                  <option key={s.subject_id} value={s.subject_id}>
                    {s.subject_name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className={labelClass}>Teacher (Employee ID)</label>
              <input
                required
                type="number"
                min={1}
                value={form.employee_id}
                onChange={(e) => setForm({ ...form, employee_id: e.target.value })}
                className={inputClass}
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className={labelClass}>Day</label>
                <select
                  value={form.day_of_week}
                  onChange={(e) => setForm({ ...form, day_of_week: e.target.value })}
                  className={inputClass}
                >
                  {DAYS.map((d) => (
                    <option key={d} value={d}>
                      {d}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className={labelClass}>Period #</label>
                <input
                  required
                  type="number"
                  min={1}
                  value={form.period_no}
                  onChange={(e) => setForm({ ...form, period_no: e.target.value })}
                  className={inputClass}
                />
              </div>
            </div>
            <div>
              <label className={labelClass}>Room (optional)</label>
              <input value={form.room_id} onChange={(e) => setForm({ ...form, room_id: e.target.value })} className={inputClass} />
            </div>

            {formError && (
              <p role="alert" className="text-sm text-red-600 dark:text-red-400">
                {formError}
              </p>
            )}

            <div className="flex justify-end gap-2 pt-2">
              <button type="button" onClick={() => setIsCreating(false)} className={secondaryButtonClass}>
                Cancel
              </button>
              <button type="submit" disabled={isSubmitting} className={primaryButtonClass}>
                {isSubmitting ? "Saving…" : "Save"}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
