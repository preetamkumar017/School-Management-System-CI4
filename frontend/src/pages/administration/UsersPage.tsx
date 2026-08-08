import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";
import { useRoles } from "./RolesPage";

interface User {
  user_id: number;
  username: string;
  role_id: number;
  owner_type: "EMPLOYEE" | "STUDENT" | "GUARDIAN";
  owner_ref_id: number;
  status: "ACTIVE" | "LOCKED" | "DEACTIVATED";
  last_login_at: string | null;
}

interface CreateFormState {
  username: string;
  password: string;
  role_id: string;
  owner_type: "EMPLOYEE" | "STUDENT" | "GUARDIAN";
  owner_ref_id: string;
}

const EMPTY_FORM: CreateFormState = { username: "", password: "", role_id: "", owner_type: "EMPLOYEE", owner_ref_id: "" };

const STATUS_STYLES: Record<User["status"], string> = {
  ACTIVE: "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400",
  LOCKED: "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400",
  DEACTIVATED: "bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-400",
};

export default function UsersPage() {
  const { roles } = useRoles();
  const [users, setUsers] = useState<User[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<CreateFormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function reload() {
    setIsLoading(true);
    api
      .get<{ data: User[] }>("/administration/users")
      .then((response) => setUsers(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  useEffect(reload, []);

  function roleName(roleId: number): string {
    return roles.find((r) => r.role_id === roleId)?.role_name ?? `Role #${roleId}`;
  }

  function openCreate() {
    setForm(EMPTY_FORM);
    setFormError(null);
    setIsCreating(true);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.post("/administration/users", {
        username: form.username,
        password: form.password,
        role_id: Number(form.role_id),
        owner_type: form.owner_type,
        owner_ref_id: Number(form.owner_ref_id),
      });
      setIsCreating(false);
      reload();
    } catch (err) {
      setFormError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleStatusChange(user: User, status: User["status"]) {
    if (status === "DEACTIVATED" && !confirm(`Deactivate "${user.username}"? This revokes all of their sessions.`)) {
      return;
    }
    try {
      await api.post(`/administration/users/${user.user_id}/status`, { status });
      reload();
    } catch (err) {
      alert(apiErrorMessage(err));
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Users</h2>
        <button type="button" onClick={openCreate} className={primaryButtonClass}>
          New User
        </button>
      </div>

      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {!isLoading && !error && (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th className="px-4 py-2 font-medium">Username</th>
                <th className="px-4 py-2 font-medium">Role</th>
                <th className="px-4 py-2 font-medium">Owner</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2 font-medium">Last login</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {users.map((user) => (
                <tr key={user.user_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{user.username}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{roleName(user.role_id)}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">
                    {user.owner_type} #{user.owner_ref_id}
                  </td>
                  <td className="px-4 py-2">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_STYLES[user.status]}`}>
                      {user.status}
                    </span>
                  </td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{user.last_login_at ?? "Never"}</td>
                  <td className="px-4 py-2 text-right">
                    {user.status !== "ACTIVE" && (
                      <button
                        type="button"
                        onClick={() => handleStatusChange(user, "ACTIVE")}
                        className="mr-3 text-green-700 hover:underline dark:text-green-400"
                      >
                        Activate
                      </button>
                    )}
                    {user.status === "ACTIVE" && (
                      <button
                        type="button"
                        onClick={() => handleStatusChange(user, "LOCKED")}
                        className="mr-3 text-amber-700 hover:underline dark:text-amber-400"
                      >
                        Lock
                      </button>
                    )}
                    {user.status !== "DEACTIVATED" && (
                      <button
                        type="button"
                        onClick={() => handleStatusChange(user, "DEACTIVATED")}
                        className="text-red-600 hover:underline dark:text-red-400"
                      >
                        Deactivate
                      </button>
                    )}
                  </td>
                </tr>
              ))}
              {users.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-4 py-6 text-center text-slate-400">
                    No users yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && (
        <Modal title="New User" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label htmlFor="username" className={labelClass}>
                Username
              </label>
              <input
                id="username"
                required
                value={form.username}
                onChange={(e) => setForm({ ...form, username: e.target.value })}
                className={inputClass}
              />
            </div>

            <div>
              <label htmlFor="password" className={labelClass}>
                Password
              </label>
              <input
                id="password"
                type="password"
                required
                value={form.password}
                onChange={(e) => setForm({ ...form, password: e.target.value })}
                className={inputClass}
              />
            </div>

            <div>
              <label htmlFor="role_id" className={labelClass}>
                Role
              </label>
              <select
                id="role_id"
                required
                value={form.role_id}
                onChange={(e) => setForm({ ...form, role_id: e.target.value })}
                className={inputClass}
              >
                <option value="" disabled>
                  Select a role
                </option>
                {roles.map((role) => (
                  <option key={role.role_id} value={role.role_id}>
                    {role.role_name}
                  </option>
                ))}
              </select>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <label htmlFor="owner_type" className={labelClass}>
                  Owner type
                </label>
                <select
                  id="owner_type"
                  value={form.owner_type}
                  onChange={(e) => setForm({ ...form, owner_type: e.target.value as CreateFormState["owner_type"] })}
                  className={inputClass}
                >
                  <option value="EMPLOYEE">Employee</option>
                  <option value="STUDENT">Student</option>
                  <option value="GUARDIAN">Guardian</option>
                </select>
              </div>

              <div>
                <label htmlFor="owner_ref_id" className={labelClass}>
                  Owner ID
                </label>
                <input
                  id="owner_ref_id"
                  type="number"
                  required
                  min={1}
                  value={form.owner_ref_id}
                  onChange={(e) => setForm({ ...form, owner_ref_id: e.target.value })}
                  className={inputClass}
                />
              </div>
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
                {isSubmitting ? "Creating…" : "Create"}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
