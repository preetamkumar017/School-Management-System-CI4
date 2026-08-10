import { useEffect, useState } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { useAcademicSessions, useGradingSchemes } from "../../lib/academic";

interface Board {
  board_id: number;
  name: string;
  short_name: string;
  board_type: string;
  country: string;
  state_applicability: string | null;
  status: string;
  description: string | null;
}

interface BoardAffiliation {
  affiliation_id: number;
  board_id: number;
  academic_session_id: number;
  affiliation_number: string;
  validity_start: string | null;
  validity_end: string | null;
  status: string;
  board_name?: string;
  session_name?: string;
}

interface AcademicFramework {
  framework_id: number;
  name: string;
  board_id: number;
  grading_scheme_id: number | null;
  level_divisions: string[];
  educational_tracks: string[] | null;
  pass_criteria_json: {
    subject_pass_percentage?: number;
    overall_pass_percentage?: number;
  } | null;
  grace_marks_policy: {
    max_grace_marks?: number;
    rounding_policy?: string;
  } | null;
  subject_requirements: {
    min_mandatory_subjects?: number;
  } | null;
  language_requirements: {
    mandatory_languages_count?: number;
  } | null;
  version: number;
  approval_status: string;
  rejection_reason: string | null;
  board_name?: string;
  grading_scheme_name?: string;
  applicable_session_ids: number[];
}

export default function BoardFrameworkPage() {
  const [subTab, setSubTab] = useState<"boards" | "affiliations" | "frameworks">("boards");
  
  // Data lists
  const [boards, setBoards] = useState<Board[]>([]);
  const [affiliations, setAffiliations] = useState<BoardAffiliation[]>([]);
  const [frameworks, setFrameworks] = useState<AcademicFramework[]>([]);
  
  // Auxiliary hooks
  const { sessions } = useAcademicSessions();
  const { gradingSchemes } = useGradingSchemes();

  // Popups & Loading
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  // Form states
  const [editingBoard, setEditingBoard] = useState<Partial<Board> | null>(null);
  const [editingAff, setEditingAff] = useState<Partial<BoardAffiliation> | null>(null);
  const [editingFw, setEditingFw] = useState<Partial<AcademicFramework> | null>(null);
  const [rejectionFwId, setRejectionFwId] = useState<number | null>(null);
  const [rejectionReason, setRejectionReason] = useState("");

  const refreshData = async () => {
    setLoading(true);
    setError(null);
    try {
      if (subTab === "boards") {
        const res = await api.get("/administration/boards");
        setBoards(res.data.data);
      } else if (subTab === "affiliations") {
        const res = await api.get("/administration/board-affiliations");
        setAffiliations(res.data.data);
      } else if (subTab === "frameworks") {
        const res = await api.get("/administration/academic-frameworks");
        setFrameworks(res.data.data);
      }
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    refreshData();
  }, [subTab]);

  const handleSaveBoard = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingBoard) return;
    setError(null);
    setSuccess(null);
    try {
      if (editingBoard.board_id) {
        await api.patch(`/administration/boards/${editingBoard.board_id}`, editingBoard);
        setSuccess("Board configuration updated successfully.");
      } else {
        await api.post("/administration/boards", editingBoard);
        setSuccess("Board configuration created successfully.");
      }
      setEditingBoard(null);
      refreshData();
    } catch (err) {
      setError(apiErrorMessage(err));
    }
  };

  const handleDeleteBoard = async (id: number) => {
    if (!confirm("Are you sure you want to delete this board configuration?")) return;
    setError(null);
    setSuccess(null);
    try {
      await api.delete(`/administration/boards/${id}`);
      setSuccess("Board deleted successfully.");
      refreshData();
    } catch (err) {
      setError(apiErrorMessage(err));
    }
  };

  const handleSaveAffiliation = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingAff) return;
    setError(null);
    setSuccess(null);
    try {
      if (editingAff.affiliation_id) {
        await api.patch(`/administration/board-affiliations/${editingAff.affiliation_id}`, editingAff);
        setSuccess("Board affiliation updated successfully.");
      } else {
        await api.post("/administration/board-affiliations", editingAff);
        setSuccess("Board affiliation created successfully.");
      }
      setEditingAff(null);
      refreshData();
    } catch (err) {
      setError(apiErrorMessage(err));
    }
  };

  const handleDeleteAffiliation = async (id: number) => {
    if (!confirm("Are you sure you want to delete this affiliation?")) return;
    setError(null);
    setSuccess(null);
    try {
      await api.delete(`/administration/board-affiliations/${id}`);
      setSuccess("Affiliation deleted successfully.");
      refreshData();
    } catch (err) {
      setError(apiErrorMessage(err));
    }
  };

  const handleSaveFramework = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingFw) return;
    setError(null);
    setSuccess(null);
    try {
      const payload = {
        ...editingFw,
        level_divisions: typeof editingFw.level_divisions === "string" 
          ? (editingFw.level_divisions as string).split(",").map(s => s.trim()) 
          : editingFw.level_divisions || [],
        educational_tracks: typeof editingFw.educational_tracks === "string" 
          ? (editingFw.educational_tracks as string).split(",").map(s => s.trim()) 
          : editingFw.educational_tracks || null
      };

      if (editingFw.framework_id) {
        await api.patch(`/administration/academic-frameworks/${editingFw.framework_id}`, payload);
        setSuccess("Framework updated successfully.");
      } else {
        await api.post("/administration/academic-frameworks", payload);
        setSuccess("Framework created successfully.");
      }
      setEditingFw(null);
      refreshData();
    } catch (err) {
      setError(apiErrorMessage(err));
    }
  };

  const handleWorkflowAction = async (id: number, action: "submit" | "approve" | "reject") => {
    setError(null);
    setSuccess(null);
    try {
      if (action === "reject") {
        await api.post(`/administration/academic-frameworks/${id}/reject`, { reason: rejectionReason });
        setSuccess("Framework rejected successfully.");
        setRejectionFwId(null);
        setRejectionReason("");
      } else {
        await api.post(`/administration/academic-frameworks/${id}/${action}`);
        setSuccess(`Framework ${action}d successfully.`);
      }
      refreshData();
    } catch (err) {
      setError(apiErrorMessage(err));
    }
  };

  const handleDeleteFramework = async (id: number) => {
    if (!confirm("Are you sure you want to delete this framework draft?")) return;
    setError(null);
    setSuccess(null);
    try {
      await api.delete(`/administration/academic-frameworks/${id}`);
      setSuccess("Framework deleted successfully.");
      refreshData();
    } catch (err) {
      setError(apiErrorMessage(err));
    }
  };

  return (
    <div className="bg-white p-6 rounded-lg shadow-sm border border-slate-100 dark:bg-slate-900 dark:border-slate-800">
      <div className="flex gap-4 border-b border-slate-100 pb-4 mb-6 dark:border-slate-800">
        <button
          onClick={() => setSubTab("boards")}
          className={`px-4 py-2 font-medium text-sm rounded ${
            subTab === "boards" ? "bg-slate-100 dark:bg-slate-800" : "text-slate-600 dark:text-slate-400"
          }`}
        >
          Boards Master
        </button>
        <button
          onClick={() => setSubTab("affiliations")}
          className={`px-4 py-2 font-medium text-sm rounded ${
            subTab === "affiliations" ? "bg-slate-100 dark:bg-slate-800" : "text-slate-600 dark:text-slate-400"
          }`}
        >
          Board Affiliations
        </button>
        <button
          onClick={() => setSubTab("frameworks")}
          className={`px-4 py-2 font-medium text-sm rounded ${
            subTab === "frameworks" ? "bg-slate-100 dark:bg-slate-800" : "text-slate-600 dark:text-slate-400"
          }`}
        >
          Academic Frameworks
        </button>
      </div>

      {loading && <div className="mb-4 text-xs text-slate-500">Loading data...</div>}
      {error && <div className="mb-4 p-3 bg-red-50 text-red-700 rounded border border-red-200 text-sm">{error}</div>}
      {success && <div className="mb-4 p-3 bg-green-50 text-green-700 rounded border border-green-200 text-sm">{success}</div>}

      {/* BOARDS TAB */}
      {subTab === "boards" && (
        <div>
          <div className="flex justify-between items-center mb-4">
            <h3 className="text-lg font-semibold text-slate-800 dark:text-slate-200">Boards List</h3>
            <button
              onClick={() => setEditingBoard({ name: "", short_name: "", board_type: "CENTRAL", country: "India", status: "ACTIVE" })}
              className="bg-slate-900 text-white px-4 py-2 rounded text-sm hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-950 dark:hover:bg-slate-200 transition"
            >
              Add Board
            </button>
          </div>

          <table className="w-full text-left text-sm border-collapse">
            <thead>
              <tr className="border-b border-slate-100 dark:border-slate-800 text-slate-500">
                <th className="py-2">Board Name</th>
                <th className="py-2">Abbreviation</th>
                <th className="py-2">Type</th>
                <th className="py-2">State Applicability</th>
                <th className="py-2">Status</th>
                <th className="py-2 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {boards.map((b) => (
                <tr key={b.board_id} className="border-b border-slate-50 dark:border-slate-800 text-slate-700 dark:text-slate-300">
                  <td className="py-3 font-medium">{b.name}</td>
                  <td className="py-3">{b.short_name}</td>
                  <td className="py-3">{b.board_type}</td>
                  <td className="py-3">{b.state_applicability || "National"}</td>
                  <td className="py-3">
                    <span className={`px-2 py-1 text-xs rounded font-medium ${b.status === "ACTIVE" ? "bg-green-50 text-green-700" : "bg-slate-100 text-slate-600"}`}>
                      {b.status}
                    </span>
                  </td>
                  <td className="py-3 text-right flex justify-end gap-2">
                    <button onClick={() => setEditingBoard(b)} className="text-slate-600 hover:text-slate-900 text-xs">Edit</button>
                    <button onClick={() => handleDeleteBoard(b.board_id)} className="text-red-600 hover:text-red-900 text-xs">Delete</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* BOARD AFFILIATIONS TAB */}
      {subTab === "affiliations" && (
        <div>
          <div className="flex justify-between items-center mb-4">
            <h3 className="text-lg font-semibold text-slate-800 dark:text-slate-200">Board Affiliations</h3>
            <button
              onClick={() => setEditingAff({ board_id: boards[0]?.board_id || 0, academic_session_id: sessions[0]?.academic_session_id || 0, affiliation_number: "", status: "ACTIVE" })}
              className="bg-slate-900 text-white px-4 py-2 rounded text-sm hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-950 dark:hover:bg-slate-200 transition"
            >
              Link Affiliation
            </button>
          </div>

          <table className="w-full text-left text-sm border-collapse">
            <thead>
              <tr className="border-b border-slate-100 dark:border-slate-800 text-slate-500">
                <th className="py-2">Board</th>
                <th className="py-2">Academic Session</th>
                <th className="py-2">Affiliation Number</th>
                <th className="py-2">Validity</th>
                <th className="py-2">Status</th>
                <th className="py-2 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {affiliations.map((a) => (
                <tr key={a.affiliation_id} className="border-b border-slate-50 dark:border-slate-800 text-slate-700 dark:text-slate-300">
                  <td className="py-3 font-medium">{a.board_name}</td>
                  <td className="py-3">{a.session_name}</td>
                  <td className="py-3 font-mono">{a.affiliation_number}</td>
                  <td className="py-3 text-xs">{a.validity_start} to {a.validity_end}</td>
                  <td className="py-3">
                    <span className={`px-2 py-1 text-xs rounded font-medium ${a.status === "ACTIVE" ? "bg-green-50 text-green-700" : "bg-slate-100 text-slate-600"}`}>
                      {a.status}
                    </span>
                  </td>
                  <td className="py-3 text-right flex justify-end gap-2">
                    <button onClick={() => setEditingAff(a)} className="text-slate-600 hover:text-slate-900 text-xs">Edit</button>
                    <button onClick={() => handleDeleteAffiliation(a.affiliation_id)} className="text-red-600 hover:text-red-900 text-xs">Delete</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* ACADEMIC FRAMEWORKS TAB */}
      {subTab === "frameworks" && (
        <div>
          <div className="flex justify-between items-center mb-4">
            <h3 className="text-lg font-semibold text-slate-800 dark:text-slate-200">Academic Framework Versions</h3>
            <button
              onClick={() => setEditingFw({
                name: "",
                board_id: boards[0]?.board_id || 0,
                grading_scheme_id: gradingSchemes[0]?.grading_scheme_id || null,
                level_divisions: ["Primary"],
                educational_tracks: [],
                pass_criteria_json: { subject_pass_percentage: 33, overall_pass_percentage: 35 },
                grace_marks_policy: { max_grace_marks: 5, rounding_policy: "No rounding" },
                subject_requirements: { min_mandatory_subjects: 5 },
                language_requirements: { mandatory_languages_count: 1 },
                applicable_session_ids: []
              })}
              className="bg-slate-900 text-white px-4 py-2 rounded text-sm hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-950 dark:hover:bg-slate-200 transition"
            >
              Create Framework Version
            </button>
          </div>

          <table className="w-full text-left text-sm border-collapse">
            <thead>
              <tr className="border-b border-slate-100 dark:border-slate-800 text-slate-500">
                <th className="py-2">Framework Name</th>
                <th className="py-2">Board</th>
                <th className="py-2">Version</th>
                <th className="py-2">Level Divisions</th>
                <th className="py-2">Status</th>
                <th className="py-2 text-right">Workflow Actions</th>
              </tr>
            </thead>
            <tbody>
              {frameworks.map((f) => (
                <tr key={f.framework_id} className="border-b border-slate-50 dark:border-slate-800 text-slate-700 dark:text-slate-300">
                  <td className="py-3 font-medium">{f.name}</td>
                  <td className="py-3">{f.board_name}</td>
                  <td className="py-3 font-mono text-xs">v{f.version}</td>
                  <td className="py-3 text-xs">{f.level_divisions?.join(", ")}</td>
                  <td className="py-3">
                    <span className={`px-2 py-1 text-xs rounded font-medium ${
                      f.approval_status === "PUBLISHED" ? "bg-green-50 text-green-700" :
                      f.approval_status === "SUBMITTED" ? "bg-amber-50 text-amber-700" :
                      f.approval_status === "REJECTED" ? "bg-red-50 text-red-700" : "bg-slate-100 text-slate-600"
                    }`}>
                      {f.approval_status}
                    </span>
                  </td>
                  <td className="py-3 text-right flex justify-end gap-2">
                    {f.approval_status === "DRAFT" && (
                      <>
                        <button onClick={() => setEditingFw(f)} className="text-slate-600 hover:text-slate-900 text-xs">Edit</button>
                        <button onClick={() => handleWorkflowAction(f.framework_id, "submit")} className="text-indigo-600 hover:text-indigo-900 text-xs font-semibold">Submit</button>
                        <button onClick={() => handleDeleteFramework(f.framework_id)} className="text-red-600 hover:text-red-900 text-xs">Delete</button>
                      </>
                    )}
                    {f.approval_status === "SUBMITTED" && (
                      <>
                        <button onClick={() => handleWorkflowAction(f.framework_id, "approve")} className="text-green-600 hover:text-green-950 text-xs font-semibold">Approve</button>
                        <button onClick={() => setRejectionFwId(f.framework_id)} className="text-red-600 hover:text-red-950 text-xs font-semibold">Reject</button>
                      </>
                    )}
                    {f.approval_status === "PUBLISHED" && (
                      <span className="text-slate-400 text-xs italic">Immutable</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* BOARD DIALOG POPUP */}
      {editingBoard && (
        <div className="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center p-4 z-50">
          <div className="bg-white dark:bg-slate-900 rounded-lg p-6 max-w-md w-full border dark:border-slate-800 shadow-xl">
            <h4 className="text-lg font-bold mb-4 text-slate-800 dark:text-slate-200">
              {editingBoard.board_id ? "Edit Board Master" : "Add Board Master"}
            </h4>
            <form onSubmit={handleSaveBoard} className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Board Name</label>
                <input
                  type="text"
                  required
                  value={editingBoard.name || ""}
                  onChange={(e) => setEditingBoard({ ...editingBoard, name: e.target.value })}
                  className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700"
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Abbreviation</label>
                  <input
                    type="text"
                    required
                    value={editingBoard.short_name || ""}
                    onChange={(e) => setEditingBoard({ ...editingBoard, short_name: e.target.value })}
                    className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Board Type</label>
                  <select
                    value={editingBoard.board_type || "CENTRAL"}
                    onChange={(e) => setEditingBoard({ ...editingBoard, board_type: e.target.value })}
                    className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700"
                  >
                    <option value="CENTRAL">CENTRAL</option>
                    <option value="STATE">STATE</option>
                    <option value="OTHER">OTHER</option>
                  </select>
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">State Applicability</label>
                <input
                  type="text"
                  placeholder="e.g. Chhattisgarh"
                  value={editingBoard.state_applicability || ""}
                  onChange={(e) => setEditingBoard({ ...editingBoard, state_applicability: e.target.value || null })}
                  className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700"
                />
              </div>
              <div className="flex justify-end gap-2 pt-4">
                <button type="button" onClick={() => setEditingBoard(null)} className="px-4 py-2 border rounded text-sm hover:bg-slate-50 dark:hover:bg-slate-800">Cancel</button>
                <button type="submit" className="bg-slate-900 text-white px-4 py-2 rounded text-sm hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-950 dark:hover:bg-slate-200">Save</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* AFFILIATION DIALOG POPUP */}
      {editingAff && (
        <div className="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center p-4 z-50">
          <div className="bg-white dark:bg-slate-900 rounded-lg p-6 max-w-md w-full border dark:border-slate-800 shadow-xl">
            <h4 className="text-lg font-bold mb-4 text-slate-800 dark:text-slate-200">
              {editingAff.affiliation_id ? "Edit Board Affiliation" : "Link Board Affiliation"}
            </h4>
            <form onSubmit={handleSaveAffiliation} className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Select Board</label>
                <select
                  value={editingAff.board_id || ""}
                  onChange={(e) => setEditingAff({ ...editingAff, board_id: Number(e.target.value) })}
                  className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700"
                >
                  {boards.map(b => <option key={b.board_id} value={b.board_id}>{b.name}</option>)}
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Academic Session</label>
                <select
                  value={editingAff.academic_session_id || ""}
                  onChange={(e) => setEditingAff({ ...editingAff, academic_session_id: Number(e.target.value) })}
                  className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700"
                >
                  {sessions.map(s => <option key={s.academic_session_id} value={s.academic_session_id}>{s.session_name}</option>)}
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Affiliation Number</label>
                <input
                  type="text"
                  required
                  value={editingAff.affiliation_number || ""}
                  onChange={(e) => setEditingAff({ ...editingAff, affiliation_number: e.target.value })}
                  className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700"
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Start Date</label>
                  <input
                    type="date"
                    value={editingAff.validity_start || ""}
                    onChange={(e) => setEditingAff({ ...editingAff, validity_start: e.target.value })}
                    className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">End Date</label>
                  <input
                    type="date"
                    value={editingAff.validity_end || ""}
                    onChange={(e) => setEditingAff({ ...editingAff, validity_end: e.target.value })}
                    className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700"
                  />
                </div>
              </div>
              <div className="flex justify-end gap-2 pt-4">
                <button type="button" onClick={() => setEditingAff(null)} className="px-4 py-2 border rounded text-sm hover:bg-slate-50 dark:hover:bg-slate-800">Cancel</button>
                <button type="submit" className="bg-slate-900 text-white px-4 py-2 rounded text-sm hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-950 dark:hover:bg-slate-200">Save</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* FRAMEWORK DIALOG POPUP */}
      {editingFw && (
        <div className="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center p-4 z-50 overflow-y-auto">
          <div className="bg-white dark:bg-slate-900 rounded-lg p-6 max-w-xl w-full border dark:border-slate-800 shadow-xl my-8">
            <h4 className="text-lg font-bold mb-4 text-slate-800 dark:text-slate-200">
              {editingFw.framework_id ? "Edit Framework Version" : "Create Framework Version"}
            </h4>
            <form onSubmit={handleSaveFramework} className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Framework Name</label>
                  <input
                    type="text"
                    required
                    value={editingFw.name || ""}
                    onChange={(e) => setEditingFw({ ...editingFw, name: e.target.value })}
                    className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Select Board</label>
                  <select
                    value={editingFw.board_id || ""}
                    onChange={(e) => setEditingFw({ ...editingFw, board_id: Number(e.target.value) })}
                    className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700"
                  >
                    {boards.map(b => <option key={b.board_id} value={b.board_id}>{b.name}</option>)}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Grading Scheme Link</label>
                  <select
                    value={editingFw.grading_scheme_id || ""}
                    onChange={(e) => setEditingFw({ ...editingFw, grading_scheme_id: Number(e.target.value) || null })}
                    className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700"
                  >
                    <option value="">None (Select Scheme)</option>
                    {gradingSchemes.map(gs => <option key={gs.grading_scheme_id} value={gs.grading_scheme_id}>{gs.scheme_name}</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Level Divisions (comma-separated)</label>
                  <input
                    type="text"
                    placeholder="e.g. Primary, Secondary"
                    value={Array.isArray(editingFw.level_divisions) ? editingFw.level_divisions.join(", ") : editingFw.level_divisions || ""}
                    onChange={(e) => setEditingFw({ ...editingFw, level_divisions: e.target.value as any })}
                    className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4 border-t pt-4 dark:border-slate-800">
                <div>
                  <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Min Subject Pass %</label>
                  <input
                    type="number"
                    value={editingFw.pass_criteria_json?.subject_pass_percentage || 0}
                    onChange={(e) => setEditingFw({
                      ...editingFw,
                      pass_criteria_json: { ...editingFw.pass_criteria_json, subject_pass_percentage: Number(e.target.value) }
                    })}
                    className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Overall Pass %</label>
                  <input
                    type="number"
                    value={editingFw.pass_criteria_json?.overall_pass_percentage || 0}
                    onChange={(e) => setEditingFw({
                      ...editingFw,
                      pass_criteria_json: { ...editingFw.pass_criteria_json, overall_pass_percentage: Number(e.target.value) }
                    })}
                    className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Max Grace Marks</label>
                  <input
                    type="number"
                    value={editingFw.grace_marks_policy?.max_grace_marks || 0}
                    onChange={(e) => setEditingFw({
                      ...editingFw,
                      grace_marks_policy: { ...editingFw.grace_marks_policy, max_grace_marks: Number(e.target.value) }
                    })}
                    className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Grace Rounding Policy</label>
                  <select
                    value={editingFw.grace_marks_policy?.rounding_policy || "No rounding"}
                    onChange={(e) => setEditingFw({
                      ...editingFw,
                      grace_marks_policy: { ...editingFw.grace_marks_policy, rounding_policy: e.target.value }
                    })}
                    className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700"
                  >
                    <option value="No rounding">No rounding</option>
                    <option value="Round before grace calculation">Round before grace calculation</option>
                    <option value="Round after grace calculation">Round after grace calculation</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Min Mandatory Subjects</label>
                  <input
                    type="number"
                    value={editingFw.subject_requirements?.min_mandatory_subjects || 0}
                    onChange={(e) => setEditingFw({
                      ...editingFw,
                      subject_requirements: { min_mandatory_subjects: Number(e.target.value) }
                    })}
                    className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Mandatory Languages Count</label>
                  <input
                    type="number"
                    value={editingFw.language_requirements?.mandatory_languages_count || 0}
                    onChange={(e) => setEditingFw({
                      ...editingFw,
                      language_requirements: { mandatory_languages_count: Number(e.target.value) }
                    })}
                    className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Applicable Sessions</label>
                <div className="grid grid-cols-3 gap-2 border p-3 rounded dark:bg-slate-800 dark:border-slate-700">
                  {sessions.map(s => (
                    <label key={s.academic_session_id} className="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                      <input
                        type="checkbox"
                        checked={editingFw.applicable_session_ids?.includes(s.academic_session_id) || false}
                        onChange={(e) => {
                          const ids = editingFw.applicable_session_ids || [];
                          if (e.target.checked) {
                            setEditingFw({ ...editingFw, applicable_session_ids: [...ids, s.academic_session_id] });
                          } else {
                            setEditingFw({ ...editingFw, applicable_session_ids: ids.filter(id => id !== s.academic_session_id) });
                          }
                        }}
                      />
                      {s.session_name}
                    </label>
                  ))}
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t dark:border-slate-800">
                <button type="button" onClick={() => setEditingFw(null)} className="px-4 py-2 border rounded text-sm hover:bg-slate-50 dark:hover:bg-slate-800">Cancel</button>
                <button type="submit" className="bg-slate-900 text-white px-4 py-2 rounded text-sm hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-950 dark:hover:bg-slate-200">Save</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* REJECTION REASON DIALOG POPUP */}
      {rejectionFwId !== null && (
        <div className="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center p-4 z-50">
          <div className="bg-white dark:bg-slate-900 rounded-lg p-6 max-w-md w-full border dark:border-slate-800 shadow-xl">
            <h4 className="text-lg font-bold mb-2 text-slate-800 dark:text-slate-200">Reject Framework Version</h4>
            <p className="text-xs text-slate-500 mb-4">Please provide a valid rejection reason (minimum 10 characters).</p>
            <textarea
              required
              rows={3}
              value={rejectionReason}
              onChange={(e) => setRejectionReason(e.target.value)}
              className="w-full border rounded px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-700 mb-4"
              placeholder="Enter rejection reason here..."
            />
            <div className="flex justify-end gap-2">
              <button type="button" onClick={() => { setRejectionFwId(null); setRejectionReason(""); }} className="px-4 py-2 border rounded text-sm hover:bg-slate-50 dark:hover:bg-slate-800">Cancel</button>
              <button
                type="button"
                onClick={() => handleWorkflowAction(rejectionFwId, "reject")}
                className="bg-red-600 text-white px-4 py-2 rounded text-sm hover:bg-red-700"
              >
                Confirm Reject
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
