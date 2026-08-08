import { useEffect, useState } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { useClasses, useSections, useSubjects } from "../../lib/academic";
import { inputClass, secondaryButtonClass } from "../../components/ui/form";

interface TimetableEntry {
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

interface Student {
  student_id: number;
  full_name: string;
  admission_number: string;
}

interface AttendanceRecord {
  attendance_record_id: number;
  student_id: number;
  timetable_entry_id: number;
  attendance_date: string;
  state: "PRESENT" | "ABSENT" | "LATE";
  marked_by: number;
  is_locked: boolean;
}

const STATE_STYLES: Record<AttendanceRecord["state"], string> = {
  PRESENT: "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400",
  ABSENT: "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-400",
  LATE: "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400",
};

export default function MarkAttendancePage() {
  const { classes } = useClasses();
  const { subjects } = useSubjects();
  const [classId, setClassId] = useState<number | null>(null);
  const { sections } = useSections(classId);
  const [sectionId, setSectionId] = useState<number | null>(null);

  const [entries, setEntries] = useState<TimetableEntry[]>([]);
  const [entryId, setEntryId] = useState<number | null>(null);
  const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10));

  const [students, setStudents] = useState<Student[]>([]);
  const [records, setRecords] = useState<AttendanceRecord[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busyStudentId, setBusyStudentId] = useState<number | null>(null);

  function subjectName(id: number): string {
    return subjects.find((s) => s.subject_id === id)?.subject_name ?? `Subject #${id}`;
  }

  useEffect(() => {
    if (sectionId === null) {
      setEntries([]);
      return;
    }
    api
      .get<{ data: TimetableEntry[] }>("/timetable/entries", { params: { section_id: sectionId } })
      .then((response) => setEntries(response.data.data.filter((e) => e.status === "PUBLISHED")))
      .catch(() => setEntries([]));
  }, [sectionId]);

  function loadRoster() {
    if (sectionId === null || entryId === null) return;
    setIsLoading(true);
    setError(null);
    Promise.all([
      api.get<{ data: Student[] }>("/sis/students", { params: { section_id: sectionId } }),
      api.get<{ data: AttendanceRecord[] }>("/attendance/records", { params: { timetable_entry_id: entryId, date } }),
    ])
      .then(([studentsResponse, recordsResponse]) => {
        setStudents(studentsResponse.data.data);
        setRecords(recordsResponse.data.data);
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  useEffect(loadRoster, [sectionId, entryId, date]);

  function recordFor(studentId: number): AttendanceRecord | undefined {
    return records.find((r) => r.student_id === studentId);
  }

  async function handleMark(studentId: number, state: AttendanceRecord["state"]) {
    if (entryId === null) return;
    setBusyStudentId(studentId);
    try {
      await api.post("/attendance/records", {
        student_id: studentId,
        timetable_entry_id: entryId,
        attendance_date: date,
        state,
      });
      loadRoster();
    } catch (err) {
      alert(apiErrorMessage(err));
    } finally {
      setBusyStudentId(null);
    }
  }

  async function handleCorrect(record: AttendanceRecord, state: AttendanceRecord["state"]) {
    const reason = prompt("Reason for correction (required once past the same-day edit window):") ?? undefined;
    setBusyStudentId(record.student_id);
    try {
      await api.post(`/attendance/records/${record.attendance_record_id}/correct`, { state, reason: reason || null });
      loadRoster();
    } catch (err) {
      alert(apiErrorMessage(err));
    } finally {
      setBusyStudentId(null);
    }
  }

  async function handleLock(record: AttendanceRecord) {
    setBusyStudentId(record.student_id);
    try {
      await api.post(`/attendance/records/${record.attendance_record_id}/lock`);
      loadRoster();
    } catch (err) {
      alert(apiErrorMessage(err));
    } finally {
      setBusyStudentId(null);
    }
  }

  return (
    <div>
      <h2 className="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Mark Attendance</h2>

      <div className="mb-4 flex flex-wrap gap-3">
        <select
          value={classId ?? ""}
          onChange={(e) => {
            setClassId(e.target.value ? Number(e.target.value) : null);
            setSectionId(null);
            setEntryId(null);
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
          onChange={(e) => {
            setSectionId(e.target.value ? Number(e.target.value) : null);
            setEntryId(null);
          }}
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

        <select
          value={entryId ?? ""}
          onChange={(e) => setEntryId(e.target.value ? Number(e.target.value) : null)}
          disabled={sectionId === null}
          className={`${inputClass} w-64`}
        >
          <option value="">Select period (published only)</option>
          {entries.map((entry) => (
            <option key={entry.timetable_entry_id} value={entry.timetable_entry_id}>
              {entry.day_of_week} · Period {entry.period_no} · {subjectName(entry.subject_id)}
            </option>
          ))}
        </select>

        <input type="date" value={date} onChange={(e) => setDate(e.target.value)} className={`${inputClass} w-40`} />
      </div>

      {entries.length === 0 && sectionId !== null && (
        <p className="mb-4 text-sm text-slate-400">No published timetable entries for this section.</p>
      )}
      {entryId === null && <p className="text-sm text-slate-400">Pick a class, section, period, and date.</p>}
      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {entryId !== null && !isLoading && !error && (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th className="px-4 py-2 font-medium">Admission #</th>
                <th className="px-4 py-2 font-medium">Name</th>
                <th className="px-4 py-2 font-medium">State</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {students.map((student) => {
                const record = recordFor(student.student_id);
                const busy = busyStudentId === student.student_id;
                return (
                  <tr key={student.student_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                    <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{student.admission_number}</td>
                    <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{student.full_name}</td>
                    <td className="px-4 py-2">
                      {record ? (
                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATE_STYLES[record.state]}`}>
                          {record.state}
                          {record.is_locked && " · Locked"}
                        </span>
                      ) : (
                        <span className="text-xs text-slate-400">Not marked</span>
                      )}
                    </td>
                    <td className="px-4 py-2 text-right">
                      {!record &&
                        (["PRESENT", "ABSENT", "LATE"] as const).map((s) => (
                          <button
                            key={s}
                            type="button"
                            disabled={busy}
                            onClick={() => handleMark(student.student_id, s)}
                            className="ml-2 text-xs text-slate-600 hover:underline disabled:opacity-50 dark:text-slate-400"
                          >
                            {s}
                          </button>
                        ))}
                      {record && !record.is_locked && (
                        <>
                          {(["PRESENT", "ABSENT", "LATE"] as const)
                            .filter((s) => s !== record.state)
                            .map((s) => (
                              <button
                                key={s}
                                type="button"
                                disabled={busy}
                                onClick={() => handleCorrect(record, s)}
                                className="ml-2 text-xs text-slate-600 hover:underline disabled:opacity-50 dark:text-slate-400"
                              >
                                → {s}
                              </button>
                            ))}
                          <button
                            type="button"
                            disabled={busy}
                            onClick={() => handleLock(record)}
                            className="ml-2 text-xs text-amber-700 hover:underline disabled:opacity-50 dark:text-amber-400"
                          >
                            Lock
                          </button>
                        </>
                      )}
                    </td>
                  </tr>
                );
              })}
              {students.length === 0 && (
                <tr>
                  <td colSpan={4} className="px-4 py-6 text-center text-slate-400">
                    No students in this section.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      <div className="mt-4">
        <button type="button" onClick={loadRoster} className={secondaryButtonClass}>
          Refresh
        </button>
      </div>
    </div>
  );
}
