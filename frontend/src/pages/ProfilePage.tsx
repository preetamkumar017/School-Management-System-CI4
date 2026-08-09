import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../lib/api";
import { useAuth } from "../lib/auth";
import { useCurrentEmployee } from "../lib/currentEmployee";
import { inputClass, labelClass, primaryButtonClass } from "../components/ui/form";

interface UserInfo {
  user_id: number;
  username: string;
  role_id: number;
  status: string;
}

export default function ProfilePage() {
  const { user, logout } = useAuth();
  const { employee, isLoading: isEmpLoading, error: empError } = useCurrentEmployee();

  const [userInfo, setUserInfo] = useState<UserInfo | null>(null);
  const [isUserLoading, setIsUserLoading] = useState(true);
  const [userError, setUserError] = useState<string | null>(null);

  // Change password states
  const [currPassword, setCurrPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [pwdError, setPwdError] = useState<string | null>(null);
  const [pwdSuccess, setPwdSuccess] = useState<string | null>(null);
  const [isChangingPwd, setIsChangingPwd] = useState(false);

  async function handleChangePassword(e: FormEvent) {
    e.preventDefault();
    if (newPassword !== confirmPassword) {
      setPwdError("New passwords do not match.");
      return;
    }
    setIsChangingPwd(true);
    setPwdError(null);
    setPwdSuccess(null);
    try {
      await api.post("/auth/change-password", {
        current_password: currPassword,
        new_password: newPassword,
      });
      setPwdSuccess("Password changed successfully! Logging out...");
      setCurrPassword("");
      setNewPassword("");
      setConfirmPassword("");
      setTimeout(async () => {
        await logout();
        window.location.reload();
      }, 2000);
    } catch (err) {
      setPwdError(apiErrorMessage(err));
    } finally {
      setIsChangingPwd(false);
    }
  }

  const [depts, setDepts] = useState<any[]>([]);
  const [desigs, setDesigs] = useState<any[]>([]);

  useEffect(() => {
    Promise.all([
      api.get("/hr-payroll/departments"),
      api.get("/hr-payroll/designations"),
    ])
      .then(([deptRes, desigRes]) => {
        setDepts(deptRes.data.data || []);
        setDesigs(desigRes.data.data || []);
      })
      .catch(() => {});
  }, []);

  useEffect(() => {
    if (!user) return;
    setIsUserLoading(true);
    api.get(`/administration/users/${user.userId}`)
      .then((res) => {
        setUserInfo(res.data.data);
      })
      .catch((err) => {
        setUserError(apiErrorMessage(err));
      })
      .finally(() => {
        setIsUserLoading(false);
      });
  }, [user]);

  const deptName = depts.find((d) => d.department_id === employee?.department_id)?.department_name ?? `Dept #${employee?.department_id}`;
  const desigName = desigs.find((d) => d.designation_id === employee?.designation_id)?.designation_name ?? `Desig #${employee?.designation_id}`;

  if (isEmpLoading || isUserLoading) {
    return (
      <div className="flex h-[60vh] items-center justify-center">
        <div className="text-slate-500 dark:text-slate-400 animate-pulse">Loading profile details...</div>
      </div>
    );
  }

  if (empError || userError) {
    return (
      <div className="mx-auto max-w-2xl rounded-2xl border border-red-200 bg-red-50 p-6 text-red-800 dark:bg-red-950/20 dark:text-red-400">
        <h4 className="font-bold">Error Loading Profile</h4>
        <p className="mt-1 text-sm">{empError || userError}</p>
      </div>
    );
  }

  const salaryStructure = employee?.salary_structure_json || {};

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      {/* Header Profile summary card */}
      <div className="relative overflow-hidden rounded-3xl bg-slate-900 p-8 text-white shadow-xl dark:bg-slate-950">
        <div className="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-indigo-500/20 blur-3xl" />
        <div className="absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-emerald-500/20 blur-3xl" />

        <div className="relative flex flex-col items-center gap-6 sm:flex-row">
          <div className="flex h-24 w-24 items-center justify-center rounded-2xl bg-indigo-600 text-3xl font-extrabold text-white shadow-inner sm:h-28 sm:w-28">
            {employee?.full_name?.split(" ").map(n => n[0]).join("") || "U"}
          </div>

          <div className="flex-1 text-center sm:text-left">
            <div className="flex flex-wrap items-center justify-center gap-2 sm:justify-start">
              <h1 className="text-2xl font-bold tracking-tight text-white">{employee?.full_name}</h1>
              <span className="rounded-full bg-emerald-500/20 px-2.5 py-0.5 text-xs font-semibold text-emerald-400 border border-emerald-500/30">
                {userInfo?.status || "ACTIVE"}
              </span>
            </div>
            <p className="mt-1 text-sm text-slate-300">
              {desigName} • {deptName}
            </p>
            <p className="mt-2 text-xs text-slate-400">
              Employee Code: <span className="font-mono text-slate-200">{employee?.employee_code}</span>
            </p>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
        {/* Left Side: Account credentials */}
        <div className="space-y-6 md:col-span-1">
          <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
            <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">Account Credentials</h3>
            <div className="mt-4 space-y-3 text-xs">
              <div>
                <span className="text-slate-400 block">Username</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">{userInfo?.username}</span>
              </div>
              <div>
                <span className="text-slate-400 block">System Role</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">Role #{userInfo?.role_id}</span>
              </div>
              <div>
                <span className="text-slate-400 block mb-1">Assigned Permissions</span>
                <div className="flex flex-wrap gap-1">
                  {user?.permissionSet.map((perm) => (
                    <span key={perm} className="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-900 dark:text-slate-400">
                      {perm}
                    </span>
                  ))}
                  {user?.permissionSet.length === 0 && <span className="text-slate-400 italic">No permissions assigned</span>}
                </div>
              </div>
            </div>
          </div>

          {/* Salary Breakdown overview */}
          <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
            <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">Salary structure</h3>
            <div className="mt-4 space-y-2 text-xs">
              {Object.entries(salaryStructure).map(([key, value]) => (
                <div key={key} className="flex justify-between border-b border-slate-50 pb-1.5 last:border-0 dark:border-slate-900">
                  <span className="text-slate-400 uppercase">{key.replace(/_/g, " ")}</span>
                  <span className="font-bold text-slate-800 dark:text-slate-200">₹{Number(value).toLocaleString()}</span>
                </div>
              ))}
              {Object.keys(salaryStructure).length === 0 && (
                <div className="text-slate-400 italic text-center py-2">No salary structure defined.</div>
              )}
            </div>
          </div>

          {/* Change Password Security Card */}
          <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
            <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">Change Password</h3>
            <form onSubmit={handleChangePassword} className="mt-4 space-y-3">
              <div>
                <label className={labelClass}>Current Password</label>
                <input
                  required
                  type="password"
                  value={currPassword}
                  onChange={(e) => setCurrPassword(e.target.value)}
                  className={inputClass}
                />
              </div>
              <div>
                <label className={labelClass}>New Password</label>
                <input
                  required
                  type="password"
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  className={inputClass}
                />
              </div>
              <div>
                <label className={labelClass}>Confirm New Password</label>
                <input
                  required
                  type="password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  className={inputClass}
                />
              </div>

              {pwdError && (
                <p role="alert" className="text-xs text-red-600 dark:text-red-400 font-semibold">
                  {pwdError}
                </p>
              )}
              {pwdSuccess && (
                <p className="text-xs text-green-600 dark:text-green-400 font-semibold">
                  {pwdSuccess}
                </p>
              )}

              <button
                type="submit"
                disabled={isChangingPwd || !currPassword || !newPassword}
                className={`${primaryButtonClass} w-full mt-2`}
              >
                {isChangingPwd ? "Updating..." : "Update Password"}
              </button>
            </form>
          </div>
        </div>

        {/* Right Side: details */}
        <div className="md:col-span-2 space-y-6">
          {/* Personal & statutory details */}
          <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
            <h3 className="border-b border-slate-100 pb-3 text-sm font-bold text-slate-900 dark:text-slate-100 dark:border-slate-800">Statutory Identification</h3>
            <div className="mt-4 grid grid-cols-2 gap-y-4 text-xs">
              <div>
                <span className="text-slate-400 block">Aadhaar Number</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">{employee?.aadhaar_number || "N/A"}</span>
              </div>
              <div>
                <span className="text-slate-400 block">PAN Number</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">{employee?.pan_number || "N/A"}</span>
              </div>
              <div>
                <span className="text-slate-400 block">PF UAN</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">{employee?.pf_uan || "N/A"}</span>
              </div>
              <div>
                <span className="text-slate-400 block">ESI Number</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">{employee?.esi_number || "N/A"}</span>
              </div>
            </div>
          </div>

          {/* Employment details */}
          <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
            <h3 className="border-b border-slate-100 pb-3 text-sm font-bold text-slate-900 dark:text-slate-100 dark:border-slate-800">Employment Details</h3>
            <div className="mt-4 grid grid-cols-2 gap-y-4 text-xs">
              <div>
                <span className="text-slate-400 block">Staff Type</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">{employee?.staff_type || "N/A"}</span>
              </div>
              <div>
                <span className="text-slate-400 block">Date of Joining</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">{employee?.joining_date || "N/A"}</span>
              </div>
              <div>
                <span className="text-slate-400 block">CBSE Classification</span>
                <span className="mt-0.5 inline-block font-semibold">
                  {employee?.cbse_classification && employee.cbse_classification !== "None" ? (
                    <span className="rounded bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-400">
                      {employee.cbse_classification}
                    </span>
                  ) : "None"}
                </span>
              </div>
              <div>
                <span className="text-slate-400 block">CBSE Teacher Code</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">{employee?.cbse_teacher_code || "N/A"}</span>
              </div>
              <div>
                <span className="text-slate-400 block">Qualification</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">{employee?.qualification || "N/A"}</span>
              </div>
              <div>
                <span className="text-slate-400 block">Experience</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">
                  {employee?.experience_years != null ? `${employee.experience_years} yrs` : "N/A"}
                </span>
              </div>
            </div>
          </div>

          {/* Emergency Contact */}
          <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
            <h3 className="border-b border-slate-100 pb-3 text-sm font-bold text-slate-900 dark:text-slate-100 dark:border-slate-800">Emergency Contact</h3>
            <div className="mt-4 grid grid-cols-2 gap-y-4 text-xs">
              <div>
                <span className="text-slate-400 block">Contact Name</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">{employee?.emergency_contact_name || "N/A"}</span>
              </div>
              <div>
                <span className="text-slate-400 block">Contact Phone</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">{employee?.emergency_contact_phone || "N/A"}</span>
              </div>
            </div>
          </div>

          {/* Documents */}
          {employee?.documents_json && employee.documents_json.length > 0 && (
            <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
              <h3 className="border-b border-slate-100 pb-3 text-sm font-bold text-slate-900 dark:text-slate-100 dark:border-slate-800">Documents</h3>
              <ul className="mt-4 space-y-2 text-xs">
                {employee.documents_json.map((doc: any, idx: number) => (
                  <li key={idx} className="flex items-center justify-between border-b border-slate-50 pb-1.5 last:border-0 dark:border-slate-900">
                    <span className="font-semibold text-slate-700 dark:text-slate-300">{doc.name}</span>
                    {doc.url ? (
                      <a href={doc.url} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:underline dark:text-indigo-400">
                        View ↗
                      </a>
                    ) : (
                      <span className="text-slate-400 italic">No link</span>
                    )}
                  </li>
                ))}
              </ul>
            </div>
          )}

          {/* Banking details */}
          <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
            <h3 className="border-b border-slate-100 pb-3 text-sm font-bold text-slate-900 dark:text-slate-100 dark:border-slate-800">Bank Details</h3>
            <div className="mt-4 grid grid-cols-2 gap-y-4 text-xs">
              <div>
                <span className="text-slate-400 block">Bank Name</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">{employee?.bank_name || "N/A"}</span>
              </div>
              <div>
                <span className="text-slate-400 block">Account Number</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">{employee?.bank_account_number || "N/A"}</span>
              </div>
              <div className="col-span-2">
                <span className="text-slate-400 block">IFSC Code</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">{employee?.bank_ifsc_code || "N/A"}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
