import { useState } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { useClasses, useSections } from "../../lib/academic";
import { inputClass } from "../../components/ui/form";
import StudentDetailModal from "./StudentDetailModal";

export interface Student {
  student_id: number;
  admission_number: string;
  full_name: string;
  dob: string;
  aadhaar_number: string | null;
  section_id: number;
  application_id: number;
  category: "GENERAL" | "RTE";
  status: "DRAFT" | "ACTIVE" | "PROMOTED" | "EXITED" | "ARCHIVED";
  medical_info: string | null;
  photo_document_id: number | null;
}

const STATUS_STYLES: Record<Student["status"], string> = {
  DRAFT: "bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-400",
  ACTIVE: "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400",
  PROMOTED: "bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-400",
  EXITED: "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400",
  ARCHIVED: "bg-slate-100 text-slate-500 dark:bg-slate-900 dark:text-slate-500",
};

export default function StudentsPage() {
  const { classes } = useClasses();
  const [classId, setClassId] = useState<number | null>(null);
  const { sections } = useSections(classId);
  const [sectionId, setSectionId] = useState<number | null>(null);

  const [students, setStudents] = useState<Student[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [selected, setSelected] = useState<Student | null>(null);

  function loadStudents(forSectionId: number) {
    setIsLoading(true);
    setError(null);
    api
      .get<{ data: Student[] }>("/sis/students", { params: { section_id: forSectionId } })
      .then((response) => setStudents(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  function handleClassChange(value: string) {
    const id = value ? Number(value) : null;
    setClassId(id);
    setSectionId(null);
    setStudents([]);
  }

  function handleSectionChange(value: string) {
    const id = value ? Number(value) : null;
    setSectionId(id);
    if (id) loadStudents(id);
    else setStudents([]);
  }

  return (
    <div>
      <h2 className="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Students</h2>

      <div className="mb-4 flex gap-3">
        <select value={classId ?? ""} onChange={(e) => handleClassChange(e.target.value)} className={`${inputClass} w-48`}>
          <option value="">Select class</option>
          {classes.map((c) => (
            <option key={c.class_id} value={c.class_id}>
              {c.class_name}
            </option>
          ))}
        </select>

        <select
          value={sectionId ?? ""}
          onChange={(e) => handleSectionChange(e.target.value)}
          disabled={classId === null}
          className={`${inputClass} w-48`}
        >
          <option value="">Select section</option>
          {sections.map((s) => (
            <option key={s.section_id} value={s.section_id}>
              {s.section_name}
            </option>
          ))}
        </select>
      </div>

      {sectionId === null && <p className="text-sm text-slate-400">Pick a class and section to see students.</p>}
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
                <th className="px-4 py-2 font-medium">Admission #</th>
                <th className="px-4 py-2 font-medium">Name</th>
                <th className="px-4 py-2 font-medium">DOB</th>
                <th className="px-4 py-2 font-medium">Category</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {students.map((student) => (
                <tr key={student.student_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{student.admission_number}</td>
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{student.full_name}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{student.dob}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{student.category}</td>
                  <td className="px-4 py-2">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_STYLES[student.status]}`}>
                      {student.status}
                    </span>
                  </td>
                  <td className="px-4 py-2 text-right">
                    <button
                      type="button"
                      onClick={() => setSelected(student)}
                      className="text-slate-600 hover:underline dark:text-slate-400"
                    >
                      View
                    </button>
                  </td>
                </tr>
              ))}
              {students.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-4 py-6 text-center text-slate-400">
                    No students in this section.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {selected && classId !== null && (
        <StudentDetailModal
          student={selected}
          classId={classId}
          onClose={() => setSelected(null)}
          onSaved={() => {
            setSelected(null);
            if (sectionId) loadStudents(sectionId);
          }}
        />
      )}
    </div>
  );
}
