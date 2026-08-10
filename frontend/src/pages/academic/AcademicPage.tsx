import { useEffect, useState } from "react";
import { api, apiErrorMessage } from "../../lib/api";

interface Session {
  academic_session_id: number;
  session_name: string;
  start_date: string;
  end_date: string;
  status: "PLANNED" | "ACTIVE" | "CLOSED" | "ARCHIVED";
}

interface AcademicClass {
  class_id: number;
  class_name: string;
  sequence_order: number;
}

interface Section {
  section_id: number;
  class_id: number;
  section_name: string;
  capacity: number;
}

interface SubjectCategory {
  subject_category_id: number;
  category_name: string;
  category_code: string;
  description: string | null;
  is_active: number;
}

interface Subject {
  subject_id: number;
  subject_name: string;
  subject_code: string;
  subject_category_id: number | null;
  is_language_subject: number;
  stream_applicability: "ALL" | "SCIENCE" | "COMMERCE" | "ARTS" | "NONE";
}

interface ClassSubjectMap {
  academic_session_id: number;
  class_id: number;
  subject_id: number;
  is_mandatory: number;
}

interface ClassBoardMap {
  class_board_map_id: number;
  academic_session_id: number;
  class_id: number;
  framework_id: number;
}

interface TeacherMap {
  teacher_class_subject_map_id: number;
  academic_session_id: number;
  class_id: number;
  section_id: number;
  subject_id: number;
  employee_id: number;
}

interface Employee {
  employee_id: number;
  full_name: string;
  designation?: string;
}

interface Framework {
  framework_id: number;
  name: string;
  board_name?: string;
}

type TabType =
  | "sessions"
  | "classes"
  | "sections"
  | "categories"
  | "subjects"
  | "class_subjects"
  | "class_boards"
  | "teacher_assignments";

export default function AcademicPage() {
  const [activeTab, setActiveTab] = useState<TabType>("sessions");

  // Auxiliary context / lookup lists
  const [sessions, setSessions] = useState<Session[]>([]);
  const [classes, setClasses] = useState<AcademicClass[]>([]);
  const [sections, setSections] = useState<Section[]>([]);
  const [categories, setCategories] = useState<SubjectCategory[]>([]);
  const [subjects, setSubjects] = useState<Subject[]>([]);
  const [classSubjectMaps, setClassSubjectMaps] = useState<ClassSubjectMap[]>([]);
  const [classBoardMaps, setClassBoardMaps] = useState<ClassBoardMap[]>([]);
  const [teacherMaps, setTeacherMaps] = useState<TeacherMap[]>([]);

  // Remote data lists for selections
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [frameworks, setFrameworks] = useState<Framework[]>([]);

  // Selected filters for mapping tabs
  const [selectedSessionId, setSelectedSessionId] = useState<number>(0);
  const [selectedClassId, setSelectedClassId] = useState<number>(0);

  // States
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  // Edit / Form Modal states
  const [showModal, setShowModal] = useState(false);
  const [modalType, setModalType] = useState<"create" | "edit">("create");
  const [editingItem, setEditingItem] = useState<any>(null);

  useEffect(() => {
    fetchRequiredLookupData();
  }, []);

  useEffect(() => {
    refreshData();
  }, [activeTab, selectedSessionId, selectedClassId]);

  const fetchRequiredLookupData = async () => {
    try {
      // Fetch Employees for Teacher Mapping
      const empRes = await api.get("/hr-payroll/employees");
      setEmployees(empRes.data.data || []);
      
      // Fetch Academic Frameworks
      const fwRes = await api.get("/administration/academic-frameworks");
      setFrameworks(fwRes.data.data || []);
    } catch (err) {
      console.error("Failed to load dependency data", err);
    }
  };

  const refreshData = async () => {
    setLoading(true);
    setError(null);
    try {
      if (activeTab === "sessions") {
        const res = await api.get("/academic/sessions");
        setSessions(res.data.data || []);
      } else if (activeTab === "classes") {
        const res = await api.get("/academic/classes");
        setClasses(res.data.data || []);
      } else if (activeTab === "sections") {
        const classRes = await api.get("/academic/classes");
        setClasses(classRes.data.data || []);
        if (selectedClassId > 0) {
          const res = await api.get(`/academic/sections?class_id=${selectedClassId}`);
          setSections(res.data.data || []);
        } else {
          setSections([]);
        }
      } else if (activeTab === "categories") {
        const res = await api.get("/academic/subject-categories");
        setCategories(res.data.data || []);
      } else if (activeTab === "subjects") {
        const res = await api.get("/academic/subjects");
        setSubjects(res.data.data || []);
        const catRes = await api.get("/academic/subject-categories");
        setCategories(catRes.data.data || []);
      } else if (activeTab === "class_subjects") {
        const sessRes = await api.get("/academic/sessions");
        const classRes = await api.get("/academic/classes");
        const subRes = await api.get("/academic/subjects");
        setSessions(sessRes.data.data || []);
        setClasses(classRes.data.data || []);
        setSubjects(subRes.data.data || []);

        // Initial default selections
        if (selectedSessionId === 0 && sessRes.data.data?.length > 0) {
          const activeSess = sessRes.data.data.find((s: any) => s.status === "ACTIVE") || sessRes.data.data[0];
          setSelectedSessionId(activeSess.academic_session_id);
        }
        if (selectedClassId === 0 && classRes.data.data?.length > 0) {
          setSelectedClassId(classRes.data.data[0].class_id);
        }

        if (selectedSessionId > 0 && selectedClassId > 0) {
          const res = await api.get(`/academic/class-subject-map/list/${selectedSessionId}/${selectedClassId}`);
          setClassSubjectMaps(res.data.data || []);
        } else {
          setClassSubjectMaps([]);
        }
      } else if (activeTab === "class_boards") {
        const sessRes = await api.get("/academic/sessions");
        const classRes = await api.get("/academic/classes");
        setSessions(sessRes.data.data || []);
        setClasses(classRes.data.data || []);

        if (selectedSessionId === 0 && sessRes.data.data?.length > 0) {
          const activeSess = sessRes.data.data.find((s: any) => s.status === "ACTIVE") || sessRes.data.data[0];
          setSelectedSessionId(activeSess.academic_session_id);
        }

        const res = await api.get(`/academic/class-board-maps?academic_session_id=${selectedSessionId || 0}`);
        setClassBoardMaps(res.data.data || []);
      } else if (activeTab === "teacher_assignments") {
        const sessRes = await api.get("/academic/sessions");
        const classRes = await api.get("/academic/classes");
        setSessions(sessRes.data.data || []);
        setClasses(classRes.data.data || []);

        if (selectedSessionId === 0 && sessRes.data.data?.length > 0) {
          const activeSess = sessRes.data.data.find((s: any) => s.status === "ACTIVE") || sessRes.data.data[0];
          setSelectedSessionId(activeSess.academic_session_id);
        }

        if (selectedClassId > 0) {
          const secRes = await api.get(`/academic/sections?class_id=${selectedClassId}`);
          setSections(secRes.data.data || []);
        }

        const res = await api.get(`/academic/teacher-maps?academic_session_id=${selectedSessionId || 0}`);
        setTeacherMaps(res.data.data || []);
      }
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  const handleOpenCreateModal = () => {
    setModalType("create");
    setEditingItem({});
    setShowModal(true);
  };

  const handleOpenEditModal = (item: any) => {
    setModalType("edit");
    setEditingItem({ ...item });
    setShowModal(true);
  };

  const handleFormSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSuccess(null);
    setLoading(true);

    try {
      if (activeTab === "sessions") {
        if (modalType === "create") {
          await api.post("/academic/sessions", {
            session_name: editingItem.session_name,
            start_date: editingItem.start_date,
            end_date: editingItem.end_date,
          });
          setSuccess("Academic Session created successfully.");
        } else {
          await api.patch(`/academic/sessions/${editingItem.academic_session_id}`, {
            session_name: editingItem.session_name,
            start_date: editingItem.start_date,
            end_date: editingItem.end_date,
          });
          setSuccess("Academic Session updated successfully.");
        }
      } else if (activeTab === "classes") {
        if (modalType === "create") {
          await api.post("/academic/classes", {
            class_name: editingItem.class_name,
            sequence_order: Number(editingItem.sequence_order),
          });
          setSuccess("Class created successfully.");
        } else {
          await api.patch(`/academic/classes/${editingItem.class_id}`, {
            class_name: editingItem.class_name,
            sequence_order: Number(editingItem.sequence_order),
          });
          setSuccess("Class updated successfully.");
        }
      } else if (activeTab === "sections") {
        if (modalType === "create") {
          await api.post("/academic/sections", {
            class_id: selectedClassId,
            section_name: editingItem.section_name,
            capacity: Number(editingItem.capacity),
          });
          setSuccess("Section created successfully.");
        } else {
          await api.patch(`/academic/sections/${editingItem.section_id}`, {
            section_name: editingItem.section_name,
            capacity: Number(editingItem.capacity),
          });
          setSuccess("Section updated successfully.");
        }
      } else if (activeTab === "categories") {
        if (modalType === "create") {
          await api.post("/academic/subject-categories", {
            category_name: editingItem.category_name,
            category_code: editingItem.category_code,
            description: editingItem.description,
          });
          setSuccess("Subject Category created successfully.");
        } else {
          await api.patch(`/academic/subject-categories/${editingItem.subject_category_id}`, {
            category_name: editingItem.category_name,
            category_code: editingItem.category_code,
            description: editingItem.description,
            is_active: Number(editingItem.is_active ?? 1),
          });
          setSuccess("Subject Category updated successfully.");
        }
      } else if (activeTab === "subjects") {
        if (modalType === "create") {
          await api.post("/academic/subjects", {
            subject_name: editingItem.subject_name,
            subject_code: editingItem.subject_code,
            subject_category_id: editingItem.subject_category_id ? Number(editingItem.subject_category_id) : null,
            is_language_subject: Number(editingItem.is_language_subject ?? 0),
            stream_applicability: editingItem.stream_applicability || "ALL",
          });
          setSuccess("Subject created successfully.");
        } else {
          await api.patch(`/academic/subjects/${editingItem.subject_id}`, {
            subject_name: editingItem.subject_name,
            subject_code: editingItem.subject_code,
            subject_category_id: editingItem.subject_category_id ? Number(editingItem.subject_category_id) : null,
            is_language_subject: Number(editingItem.is_language_subject ?? 0),
            stream_applicability: editingItem.stream_applicability || "ALL",
          });
          setSuccess("Subject updated successfully.");
        }
      } else if (activeTab === "class_subjects") {
        await api.post("/academic/class-subject-map", {
          academic_session_id: Number(selectedSessionId),
          class_id: Number(selectedClassId),
          subject_id: Number(editingItem.subject_id),
          is_mandatory: Number(editingItem.is_mandatory ?? 1),
        });
        setSuccess("Subject mapped to Class successfully.");
      } else if (activeTab === "class_boards") {
        if (modalType === "create") {
          await api.post("/academic/class-board-maps", {
            academic_session_id: Number(selectedSessionId),
            class_id: Number(editingItem.class_id),
            framework_id: Number(editingItem.framework_id),
          });
          setSuccess("Class mapped to Board successfully.");
        } else {
          await api.patch(`/academic/class-board-maps/${editingItem.class_board_map_id}`, {
            academic_session_id: Number(selectedSessionId),
            class_id: Number(editingItem.class_id),
            framework_id: Number(editingItem.framework_id),
          });
          setSuccess("Class-board mapping updated successfully.");
        }
      } else if (activeTab === "teacher_assignments") {
        if (modalType === "create") {
          await api.post("/academic/teacher-maps", {
            academic_session_id: Number(selectedSessionId),
            class_id: Number(selectedClassId),
            section_id: Number(editingItem.section_id),
            subject_id: Number(editingItem.subject_id),
            employee_id: Number(editingItem.employee_id),
          });
          setSuccess("Teacher assigned successfully.");
        } else {
          await api.patch(`/academic/teacher-maps/${editingItem.teacher_class_subject_map_id}`, {
            academic_session_id: Number(selectedSessionId),
            class_id: Number(selectedClassId),
            section_id: Number(editingItem.section_id),
            subject_id: Number(editingItem.subject_id),
            employee_id: Number(editingItem.employee_id),
          });
          setSuccess("Teacher assignment updated successfully.");
        }
      }

      setShowModal(false);
      refreshData();
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  const handleDeleteItem = async (item: any) => {
    if (!window.confirm("Are you sure you want to delete this mapping / master record? This action is irreversible.")) return;

    setError(null);
    setSuccess(null);
    setLoading(true);
    try {
      if (activeTab === "classes") {
        await api.delete(`/academic/classes/${item.class_id}`);
        setSuccess("Class deleted successfully.");
      } else if (activeTab === "sections") {
        await api.delete(`/academic/sections/${item.section_id}`);
        setSuccess("Section deleted successfully.");
      } else if (activeTab === "categories") {
        await api.delete(`/academic/subject-categories/${item.subject_category_id}`);
        setSuccess("Subject Category deleted successfully.");
      } else if (activeTab === "subjects") {
        await api.delete(`/academic/subjects/${item.subject_id}`);
        setSuccess("Subject deleted successfully.");
      } else if (activeTab === "class_subjects") {
        await api.delete(`/academic/class-subject-map/${selectedSessionId}/${selectedClassId}/${item.subject_id}`);
        setSuccess("Class-Subject mapping deleted successfully.");
      } else if (activeTab === "class_boards") {
        await api.delete(`/academic/class-board-maps/${item.class_board_map_id}`);
        setSuccess("Class-board mapping deleted successfully.");
      } else if (activeTab === "teacher_assignments") {
        await api.delete(`/academic/teacher-maps/${item.teacher_class_subject_map_id}`);
        setSuccess("Teacher assignment deleted successfully.");
      }
      refreshData();
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  const handleUpdateSessionStatus = async (sessionId: number, newStatus: string) => {
    setLoading(true);
    setError(null);
    try {
      await api.post(`/academic/sessions/${sessionId}/status`, { status: newStatus });
      setSuccess(`Session status changed to ${newStatus}.`);
      refreshData();
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Academic Master Setup</h1>
          <p className="text-slate-500 text-sm">Configure sessions, classes, subjects, teacher mappings, and board alignments.</p>
        </div>
        {activeTab !== "class_subjects" || (selectedSessionId > 0 && selectedClassId > 0) ? (
          <button
            onClick={handleOpenCreateModal}
            className="px-4 py-2 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition"
          >
            + Add New
          </button>
        ) : null}
      </div>

      {/* Tabs */}
      <div className="flex flex-wrap gap-2 border-b border-slate-200 pb-px">
        {(
          [
            { id: "sessions", label: "Sessions" },
            { id: "classes", label: "Classes" },
            { id: "sections", label: "Sections" },
            { id: "categories", label: "Subject Categories" },
            { id: "subjects", label: "Subjects" },
            { id: "class_subjects", label: "Class-Subject Mapping" },
            { id: "class_boards", label: "Class-Board Map" },
            { id: "teacher_assignments", label: "Teacher Assignments" },
          ] as const
        ).map((tab) => (
          <button
            key={tab.id}
            onClick={() => {
              setActiveTab(tab.id);
              setError(null);
              setSuccess(null);
            }}
            className={`px-4 py-2 border-b-2 text-sm font-semibold transition ${
              activeTab === tab.id
                ? "border-slate-900 text-slate-900 font-bold"
                : "border-transparent text-slate-500 hover:text-slate-800"
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Alerts */}
      {error && <div className="p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{error}</div>}
      {success && <div className="p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{success}</div>}

      {/* Dependency filters for session / class */}
      {(activeTab === "sections" || activeTab === "class_subjects" || activeTab === "class_boards" || activeTab === "teacher_assignments") && (
        <div className="p-4 bg-slate-50 border border-slate-200 rounded-lg flex flex-wrap gap-4 items-center">
          {(activeTab === "class_subjects" || activeTab === "class_boards" || activeTab === "teacher_assignments") && (
            <div className="flex flex-col gap-1">
              <label className="text-xs font-bold text-slate-600">Select Academic Session</label>
              <select
                className="px-3 py-1.5 border border-slate-300 rounded bg-white text-sm"
                value={selectedSessionId}
                onChange={(e) => setSelectedSessionId(Number(e.target.value))}
              >
                <option value={0}>-- Select Session --</option>
                {sessions.map((s) => (
                  <option key={s.academic_session_id} value={s.academic_session_id}>
                    {s.session_name} ({s.status})
                  </option>
                ))}
              </select>
            </div>
          )}

          {(activeTab === "sections" || activeTab === "class_subjects" || activeTab === "teacher_assignments") && (
            <div className="flex flex-col gap-1">
              <label className="text-xs font-bold text-slate-600">Select Class</label>
              <select
                className="px-3 py-1.5 border border-slate-300 rounded bg-white text-sm"
                value={selectedClassId}
                onChange={(e) => setSelectedClassId(Number(e.target.value))}
              >
                <option value={0}>-- Select Class --</option>
                {classes.map((c) => (
                  <option key={c.class_id} value={c.class_id}>
                    {c.class_name}
                  </option>
                ))}
              </select>
            </div>
          )}
        </div>
      )}

      {/* Lists & Data Views */}
      <div className="bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm">
        {loading ? (
          <div className="p-8 text-center text-slate-500">Loading data...</div>
        ) : (
          <>
            {activeTab === "sessions" && (
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                  <tr>
                    <th className="p-4">Session Name</th>
                    <th className="p-4">Start Date</th>
                    <th className="p-4">End Date</th>
                    <th className="p-4">Status</th>
                    <th className="p-4 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {sessions.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="p-4 text-center text-slate-400">No academic sessions found.</td>
                    </tr>
                  ) : (
                    sessions.map((sess) => (
                      <tr key={sess.academic_session_id}>
                        <td className="p-4 font-semibold text-slate-800">{sess.session_name}</td>
                        <td className="p-4 text-slate-600">{sess.start_date}</td>
                        <td className="p-4 text-slate-600">{sess.end_date}</td>
                        <td className="p-4">
                          <span className={`px-2 py-0.5 rounded text-xs font-bold ${
                            sess.status === "ACTIVE" ? "bg-green-100 text-green-700" :
                            sess.status === "PLANNED" ? "bg-amber-100 text-amber-700" :
                            "bg-slate-100 text-slate-700"
                          }`}>{sess.status}</span>
                        </td>
                        <td className="p-4 text-right space-x-2">
                          {sess.status === "PLANNED" && (
                            <button
                              onClick={() => handleUpdateSessionStatus(sess.academic_session_id, "ACTIVE")}
                              className="text-xs text-green-600 font-bold hover:underline"
                            >
                              Activate
                            </button>
                          )}
                          {sess.status === "ACTIVE" && (
                            <button
                              onClick={() => handleUpdateSessionStatus(sess.academic_session_id, "CLOSED")}
                              className="text-xs text-amber-600 font-bold hover:underline"
                            >
                              Close
                            </button>
                          )}
                          {sess.status === "CLOSED" && (
                            <button
                              onClick={() => handleUpdateSessionStatus(sess.academic_session_id, "ARCHIVED")}
                              className="text-xs text-slate-600 font-bold hover:underline"
                            >
                              Archive
                            </button>
                          )}
                          {sess.status !== "CLOSED" && sess.status !== "ARCHIVED" && (
                            <button
                              onClick={() => handleOpenEditModal(sess)}
                              className="text-xs text-slate-600 font-bold hover:underline"
                            >
                              Edit
                            </button>
                          )}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            )}

            {activeTab === "classes" && (
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                  <tr>
                    <th className="p-4">Class / Grade Name</th>
                    <th className="p-4">Sequence Order (Junior ➔ Senior)</th>
                    <th className="p-4 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {classes.length === 0 ? (
                    <tr>
                      <td colSpan={3} className="p-4 text-center text-slate-400">No classes configured.</td>
                    </tr>
                  ) : (
                    classes.map((cls) => (
                      <tr key={cls.class_id}>
                        <td className="p-4 font-semibold text-slate-800">{cls.class_name}</td>
                        <td className="p-4 text-slate-600">{cls.sequence_order}</td>
                        <td className="p-4 text-right space-x-4">
                          <button
                            onClick={() => handleOpenEditModal(cls)}
                            className="text-xs text-slate-600 font-bold hover:underline"
                          >
                            Edit
                          </button>
                          <button
                            onClick={() => handleDeleteItem(cls)}
                            className="text-xs text-red-600 font-bold hover:underline"
                          >
                            Delete
                          </button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            )}

            {activeTab === "sections" && (
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                  <tr>
                    <th className="p-4">Section Name</th>
                    <th className="p-4">Max Capacity Limit</th>
                    <th className="p-4 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {selectedClassId === 0 ? (
                    <tr>
                      <td colSpan={3} className="p-4 text-center text-slate-400">Please select a class to view sections.</td>
                    </tr>
                  ) : sections.length === 0 ? (
                    <tr>
                      <td colSpan={3} className="p-4 text-center text-slate-400">No sections added to this class.</td>
                    </tr>
                  ) : (
                    sections.map((sec) => (
                      <tr key={sec.section_id}>
                        <td className="p-4 font-semibold text-slate-800">Section {sec.section_name}</td>
                        <td className="p-4 text-slate-600">{sec.capacity} Students</td>
                        <td className="p-4 text-right space-x-4">
                          <button
                            onClick={() => handleOpenEditModal(sec)}
                            className="text-xs text-slate-600 font-bold hover:underline"
                          >
                            Edit
                          </button>
                          <button
                            onClick={() => handleDeleteItem(sec)}
                            className="text-xs text-red-600 font-bold hover:underline"
                          >
                            Delete
                          </button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            )}

            {activeTab === "categories" && (
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                  <tr>
                    <th className="p-4">Category Name</th>
                    <th className="p-4">Category Code</th>
                    <th className="p-4">Description</th>
                    <th className="p-4">Status</th>
                    <th className="p-4 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {categories.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="p-4 text-center text-slate-400">No subject categories configured.</td>
                    </tr>
                  ) : (
                    categories.map((cat) => (
                      <tr key={cat.subject_category_id}>
                        <td className="p-4 font-semibold text-slate-800">{cat.category_name}</td>
                        <td className="p-4 font-mono text-slate-600 text-xs">{cat.category_code}</td>
                        <td className="p-4 text-slate-600 text-xs">{cat.description || "--"}</td>
                        <td className="p-4">
                          <span className={`px-2 py-0.5 rounded text-xs font-bold ${
                            cat.is_active ? "bg-green-100 text-green-700" : "bg-red-100 text-red-700"
                          }`}>{cat.is_active ? "Active" : "Inactive"}</span>
                        </td>
                        <td className="p-4 text-right space-x-4">
                          <button
                            onClick={() => handleOpenEditModal(cat)}
                            className="text-xs text-slate-600 font-bold hover:underline"
                          >
                            Edit
                          </button>
                          <button
                            onClick={() => handleDeleteItem(cat)}
                            className="text-xs text-red-600 font-bold hover:underline"
                          >
                            Delete
                          </button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            )}

            {activeTab === "subjects" && (
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                  <tr>
                    <th className="p-4">Subject Name</th>
                    <th className="p-4">Subject Code</th>
                    <th className="p-4">Category</th>
                    <th className="p-4">Language?</th>
                    <th className="p-4">Stream Applicability</th>
                    <th className="p-4 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {subjects.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="p-4 text-center text-slate-400">No subjects found.</td>
                    </tr>
                  ) : (
                    subjects.map((sub) => {
                      const category = categories.find((c) => c.subject_category_id === sub.subject_category_id);
                      return (
                        <tr key={sub.subject_id}>
                          <td className="p-4 font-semibold text-slate-800">{sub.subject_name}</td>
                          <td className="p-4 text-slate-600 font-mono text-xs">{sub.subject_code}</td>
                          <td className="p-4 text-slate-600">{category?.category_name || "--"}</td>
                          <td className="p-4">
                            {sub.is_language_subject ? (
                              <span className="px-2 py-0.5 rounded text-xs bg-violet-100 text-violet-700 font-bold">Yes</span>
                            ) : "No"}
                          </td>
                          <td className="p-4 text-slate-600">
                            <span className="px-2 py-0.5 rounded text-xs bg-slate-100 text-slate-700 font-semibold">
                              {sub.stream_applicability}
                            </span>
                          </td>
                          <td className="p-4 text-right space-x-4">
                            <button
                              onClick={() => handleOpenEditModal(sub)}
                              className="text-xs text-slate-600 font-bold hover:underline"
                            >
                              Edit
                            </button>
                            <button
                              onClick={() => handleDeleteItem(sub)}
                              className="text-xs text-red-600 font-bold hover:underline"
                            >
                              Delete
                            </button>
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            )}

            {activeTab === "class_subjects" && (
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                  <tr>
                    <th className="p-4">Subject Name</th>
                    <th className="p-4">Subject Code</th>
                    <th className="p-4">Type</th>
                    <th className="p-4 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {selectedSessionId === 0 || selectedClassId === 0 ? (
                    <tr>
                      <td colSpan={4} className="p-4 text-center text-slate-400">Please select academic session and class.</td>
                    </tr>
                  ) : classSubjectMaps.length === 0 ? (
                    <tr>
                      <td colSpan={4} className="p-4 text-center text-slate-400">No subjects mapped to this class for the session.</td>
                    </tr>
                  ) : (
                    classSubjectMaps.map((map) => {
                      const sub = subjects.find((s) => s.subject_id === map.subject_id);
                      return (
                        <tr key={map.subject_id}>
                          <td className="p-4 font-semibold text-slate-800">{sub?.subject_name || "Unknown Subject"}</td>
                          <td className="p-4 text-slate-600 font-mono text-xs">{sub?.subject_code || "--"}</td>
                          <td className="p-4">
                            <span className={`px-2 py-0.5 rounded text-xs font-bold ${
                              map.is_mandatory ? "bg-indigo-100 text-indigo-700" : "bg-amber-100 text-amber-700"
                            }`}>{map.is_mandatory ? "Mandatory" : "Elective"}</span>
                          </td>
                          <td className="p-4 text-right">
                            <button
                              onClick={() => handleDeleteItem(map)}
                              className="text-xs text-red-600 font-bold hover:underline"
                            >
                              Unmap
                            </button>
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            )}

            {activeTab === "class_boards" && (
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                  <tr>
                    <th className="p-4">Class</th>
                    <th className="p-4">Aligned Board Framework</th>
                    <th className="p-4 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {selectedSessionId === 0 ? (
                    <tr>
                      <td colSpan={3} className="p-4 text-center text-slate-400">Please select an academic session.</td>
                    </tr>
                  ) : classBoardMaps.length === 0 ? (
                    <tr>
                      <td colSpan={3} className="p-4 text-center text-slate-400">No class board framework alignments configured.</td>
                    </tr>
                  ) : (
                    classBoardMaps.map((map) => {
                      const cls = classes.find((c) => c.class_id === map.class_id);
                      const fw = frameworks.find((f) => f.framework_id === map.framework_id);
                      return (
                        <tr key={map.class_board_map_id}>
                          <td className="p-4 font-semibold text-slate-800">{cls?.class_name || "Unknown Class"}</td>
                          <td className="p-4 text-slate-600 font-semibold">{fw?.name || `Framework ID: ${map.framework_id}`}</td>
                          <td className="p-4 text-right space-x-4">
                            <button
                              onClick={() => handleOpenEditModal(map)}
                              className="text-xs text-slate-600 font-bold hover:underline"
                            >
                              Edit
                            </button>
                            <button
                              onClick={() => handleDeleteItem(map)}
                              className="text-xs text-red-600 font-bold hover:underline"
                            >
                              Delete
                            </button>
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            )}

            {activeTab === "teacher_assignments" && (
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                  <tr>
                    <th className="p-4">Class & Section</th>
                    <th className="p-4">Subject</th>
                    <th className="p-4">Assigned Teacher</th>
                    <th className="p-4 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {selectedSessionId === 0 ? (
                    <tr>
                      <td colSpan={4} className="p-4 text-center text-slate-400">Please select academic session.</td>
                    </tr>
                  ) : teacherMaps.length === 0 ? (
                    <tr>
                      <td colSpan={4} className="p-4 text-center text-slate-400">No teacher assignments mapped for this session.</td>
                    </tr>
                  ) : (
                    teacherMaps.map((map) => {
                      const cls = classes.find((c) => c.class_id === map.class_id);
                      const sec = sections.find((s) => s.section_id === map.section_id);
                      const sub = subjects.find((s) => s.subject_id === map.subject_id);
                      const emp = employees.find((e) => e.employee_id === map.employee_id);
                      return (
                        <tr key={map.teacher_class_subject_map_id}>
                          <td className="p-4 text-slate-800">
                            {cls?.class_name || "Unknown"} - Section {sec?.section_name || "Unknown"}
                          </td>
                          <td className="p-4 font-semibold text-slate-600">{sub?.subject_name || "Unknown"}</td>
                          <td className="p-4 font-semibold text-slate-800">
                            {emp ? emp.full_name : `ID: ${map.employee_id}`}
                          </td>
                          <td className="p-4 text-right space-x-4">
                            <button
                              onClick={() => handleOpenEditModal(map)}
                              className="text-xs text-slate-600 font-bold hover:underline"
                            >
                              Edit
                            </button>
                            <button
                              onClick={() => handleDeleteItem(map)}
                              className="text-xs text-red-600 font-bold hover:underline"
                            >
                              Delete
                            </button>
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            )}
          </>
        )}
      </div>

      {/* Create / Edit Form Modal */}
      {showModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex justify-center items-center p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full border border-slate-200 overflow-hidden">
            <div className="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
              <h3 className="text-lg font-bold text-slate-800">
                {modalType === "create" ? "Add New Record" : "Edit Configuration"}
              </h3>
              <button onClick={() => setShowModal(false)} className="text-slate-400 hover:text-slate-600 text-lg">✕</button>
            </div>

            <form onSubmit={handleFormSubmit} className="p-6 space-y-4">
              {activeTab === "sessions" && (
                <>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">Session Name (YYYY-YY)</label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. 2026-27"
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full"
                      value={editingItem.session_name || ""}
                      onChange={(e) => setEditingItem({ ...editingItem, session_name: e.target.value })}
                    />
                  </div>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">Start Date</label>
                    <input
                      type="date"
                      required
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full"
                      value={editingItem.start_date || ""}
                      onChange={(e) => setEditingItem({ ...editingItem, start_date: e.target.value })}
                    />
                  </div>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">End Date</label>
                    <input
                      type="date"
                      required
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full"
                      value={editingItem.end_date || ""}
                      onChange={(e) => setEditingItem({ ...editingItem, end_date: e.target.value })}
                    />
                  </div>
                </>
              )}

              {activeTab === "classes" && (
                <>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">Class Name</label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. Class 10"
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full"
                      value={editingItem.class_name || ""}
                      onChange={(e) => setEditingItem({ ...editingItem, class_name: e.target.value })}
                    />
                  </div>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">Sequence order</label>
                    <input
                      type="number"
                      required
                      placeholder="e.g. 10"
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full"
                      value={editingItem.sequence_order ?? ""}
                      onChange={(e) => setEditingItem({ ...editingItem, sequence_order: e.target.value })}
                    />
                  </div>
                </>
              )}

              {activeTab === "sections" && (
                <>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">Section Name</label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. A"
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full"
                      value={editingItem.section_name || ""}
                      onChange={(e) => setEditingItem({ ...editingItem, section_name: e.target.value })}
                    />
                  </div>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">Max Capacity Boundaries</label>
                    <input
                      type="number"
                      required
                      placeholder="e.g. 40"
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full"
                      value={editingItem.capacity ?? ""}
                      onChange={(e) => setEditingItem({ ...editingItem, capacity: e.target.value })}
                    />
                  </div>
                </>
              )}

              {activeTab === "categories" && (
                <>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">Category Name</label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. Core"
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full"
                      value={editingItem.category_name || ""}
                      onChange={(e) => setEditingItem({ ...editingItem, category_name: e.target.value })}
                    />
                  </div>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">Category Code</label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. CORE"
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full"
                      value={editingItem.category_code || ""}
                      onChange={(e) => setEditingItem({ ...editingItem, category_code: e.target.value })}
                    />
                  </div>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">Description</label>
                    <textarea
                      placeholder="Enter description..."
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full"
                      value={editingItem.description || ""}
                      onChange={(e) => setEditingItem({ ...editingItem, description: e.target.value })}
                    />
                  </div>
                  {modalType === "edit" && (
                    <div className="flex items-center gap-2">
                      <input
                        type="checkbox"
                        id="is_active_cat"
                        checked={Number(editingItem.is_active ?? 1) === 1}
                        onChange={(e) => setEditingItem({ ...editingItem, is_active: e.target.checked ? 1 : 0 })}
                      />
                      <label htmlFor="is_active_cat" className="text-xs font-bold text-slate-600">Is Active</label>
                    </div>
                  )}
                </>
              )}

              {activeTab === "subjects" && (
                <>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">Subject Name</label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. Mathematics"
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full"
                      value={editingItem.subject_name || ""}
                      onChange={(e) => setEditingItem({ ...editingItem, subject_name: e.target.value })}
                    />
                  </div>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">Subject Code</label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. MATH10"
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full"
                      value={editingItem.subject_code || ""}
                      onChange={(e) => setEditingItem({ ...editingItem, subject_code: e.target.value })}
                    />
                  </div>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">Subject Category</label>
                    <select
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full bg-white"
                      value={editingItem.subject_category_id || ""}
                      onChange={(e) => setEditingItem({ ...editingItem, subject_category_id: e.target.value })}
                    >
                      <option value="">-- Select Category --</option>
                      {categories.map((c) => (
                        <option key={c.subject_category_id} value={c.subject_category_id}>
                          {c.category_name}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="flex items-center gap-2 py-1">
                    <input
                      type="checkbox"
                      id="is_lang_subj"
                      checked={Number(editingItem.is_language_subject ?? 0) === 1}
                      onChange={(e) => setEditingItem({ ...editingItem, is_language_subject: e.target.checked ? 1 : 0 })}
                    />
                    <label htmlFor="is_lang_subj" className="text-xs font-bold text-slate-600">Is Language Subject</label>
                  </div>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">Stream Applicability</label>
                    <select
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full bg-white"
                      value={editingItem.stream_applicability || "ALL"}
                      onChange={(e) => setEditingItem({ ...editingItem, stream_applicability: e.target.value })}
                    >
                      <option value="ALL">ALL</option>
                      <option value="SCIENCE">SCIENCE</option>
                      <option value="COMMERCE">COMMERCE</option>
                      <option value="ARTS">ARTS</option>
                      <option value="NONE">NONE</option>
                    </select>
                  </div>
                </>
              )}

              {activeTab === "class_subjects" && (
                <>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">Select Subject</label>
                    <select
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full bg-white"
                      value={editingItem.subject_id || ""}
                      onChange={(e) => setEditingItem({ ...editingItem, subject_id: e.target.value })}
                      required
                    >
                      <option value="">-- Select Subject --</option>
                      {subjects.map((s) => (
                        <option key={s.subject_id} value={s.subject_id}>
                          {s.subject_name} ({s.subject_code})
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="flex items-center gap-2 py-1">
                    <input
                      type="checkbox"
                      id="is_mandatory_chk"
                      checked={Number(editingItem.is_mandatory ?? 1) === 1}
                      onChange={(e) => setEditingItem({ ...editingItem, is_mandatory: e.target.checked ? 1 : 0 })}
                    />
                    <label htmlFor="is_mandatory_chk" className="text-xs font-bold text-slate-600">Is Mandatory (Compulsory Subject)</label>
                  </div>
                </>
              )}

              {activeTab === "class_boards" && (
                <>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">Select Class</label>
                    <select
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full bg-white"
                      value={editingItem.class_id || ""}
                      onChange={(e) => setEditingItem({ ...editingItem, class_id: e.target.value })}
                      required
                      disabled={modalType === "edit"}
                    >
                      <option value="">-- Select Class --</option>
                      {classes.map((c) => (
                        <option key={c.class_id} value={c.class_id}>
                          {c.class_name}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">Select Board Framework</label>
                    <select
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full bg-white"
                      value={editingItem.framework_id || ""}
                      onChange={(e) => setEditingItem({ ...editingItem, framework_id: e.target.value })}
                      required
                    >
                      <option value="">-- Select Framework --</option>
                      {frameworks.map((f) => (
                        <option key={f.framework_id} value={f.framework_id}>
                          {f.name} ({f.board_name})
                        </option>
                      ))}
                    </select>
                  </div>
                </>
              )}

              {activeTab === "teacher_assignments" && (
                <>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">Select Section</label>
                    <select
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full bg-white"
                      value={editingItem.section_id || ""}
                      onChange={(e) => setEditingItem({ ...editingItem, section_id: e.target.value })}
                      required
                    >
                      <option value="">-- Select Section --</option>
                      {sections.map((s) => (
                        <option key={s.section_id} value={s.section_id}>
                          Section {s.section_name}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">Select Subject</label>
                    <select
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full bg-white"
                      value={editingItem.subject_id || ""}
                      onChange={(e) => setEditingItem({ ...editingItem, subject_id: e.target.value })}
                      required
                    >
                      <option value="">-- Select Subject --</option>
                      {subjects.map((s) => (
                        <option key={s.subject_id} value={s.subject_id}>
                          {s.subject_name} ({s.subject_code})
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="flex flex-col gap-1">
                    <label className="text-xs font-bold text-slate-600">Select Teacher</label>
                    <select
                      className="px-3 py-2 border border-slate-300 rounded text-sm w-full bg-white"
                      value={editingItem.employee_id || ""}
                      onChange={(e) => setEditingItem({ ...editingItem, employee_id: e.target.value })}
                      required
                    >
                      <option value="">-- Select Teacher --</option>
                      {employees.map((emp) => (
                        <option key={emp.employee_id} value={emp.employee_id}>
                          {emp.full_name} ({emp.designation || "Staff"})
                        </option>
                      ))}
                    </select>
                  </div>
                </>
              )}

              <div className="pt-4 flex justify-end gap-2 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="px-4 py-2 border border-slate-300 text-slate-600 rounded text-sm font-semibold hover:bg-slate-50"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 bg-slate-900 text-white rounded text-sm font-semibold hover:bg-slate-800"
                >
                  Save Changes
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}


