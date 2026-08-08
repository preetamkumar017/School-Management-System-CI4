import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";

export interface Role {
  role_id: number;
  role_name: string;
  description: string | null;
  is_system_role: boolean;
  permission_set: string[];
}

interface RoleFormState {
  role_name: string;
  description: string;
  permission_set: string;
}

const EMPTY_FORM: RoleFormState = { role_name: "", description: "", permission_set: "" };

export function useRoles() {
  const [roles, setRoles] = useState<Role[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  function reload() {
    setIsLoading(true);
    api
      .get<{ data: Role[] }>("/administration/roles")
      .then((response) => setRoles(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  useEffect(reload, []);

  return { roles, isLoading, error, reload };
}

export default function RolesPage() {
  const { roles, isLoading, error, reload } = useRoles();
  const [editing, setEditing] = useState<Role | "new" | null>(null);
  const [form, setForm] = useState<RoleFormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function openCreate() {
    setForm(EMPTY_FORM);
    setFormError(null);
    setEditing("new");
  }

  function openEdit(role: Role) {
    setForm({
      role_name: role.role_name,
      description: role.description ?? "",
      permission_set: role.permission_set.join(", "),
    });
    setFormError(null);
    setEditing(role);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setFormError(null);
    setIsSubmitting(true);

    const permissionSet = form.permission_set
      .split(",")
      .map((p) => p.trim())
      .filter((p) => p.length > 0);

    try {
      if (editing === "new") {
        await api.post("/administration/roles", {
          role_name: form.role_name,
          description: form.description || null,
          permission_set: permissionSet,
        });
      } else if (editing) {
        await api.patch(`/administration/roles/${editing.role_id}`, {
          role_name: form.role_name,
          description: form.description || null,
          permission_set: permissionSet,
        });
      }
      setEditing(null);
      reload();
    } catch (err) {
      setFormError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete(role: Role) {
    if (!confirm(`Delete role "${role.role_name}"? This cannot be undone.`)) return;
    try {
      await api.delete(`/administration/roles/${role.role_id}`);
      reload();
    } catch (err) {
      alert(apiErrorMessage(err));
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Roles</h2>
        <button type="button" onClick={openCreate} className={primaryButtonClass}>
          New Role
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
                <th className="px-4 py-2 font-medium">Name</th>
                <th className="px-4 py-2 font-medium">Description</th>
                <th className="px-4 py-2 font-medium">Permissions</th>
                <th className="px-4 py-2 font-medium">System?</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {roles.map((role) => (
                <tr key={role.role_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{role.role_name}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{role.description ?? "—"}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{role.permission_set.join(", ") || "—"}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{role.is_system_role ? "Yes" : "No"}</td>
                  <td className="px-4 py-2 text-right">
                    <button
                      type="button"
                      onClick={() => openEdit(role)}
                      className="mr-3 text-slate-600 hover:underline dark:text-slate-400"
                    >
                      Edit
                    </button>
                    {!role.is_system_role && (
                      <button
                        type="button"
                        onClick={() => handleDelete(role)}
                        className="text-red-600 hover:underline dark:text-red-400"
                      >
                        Delete
                      </button>
                    )}
                  </td>
                </tr>
              ))}
              {roles.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-slate-400">
                    No roles yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {editing && (
        <Modal title={editing === "new" ? "New Role" : "Edit Role"} onClose={() => setEditing(null)}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label htmlFor="role_name" className={labelClass}>
                Role name
              </label>
              <input
                id="role_name"
                required
                value={form.role_name}
                onChange={(e) => setForm({ ...form, role_name: e.target.value })}
                className={inputClass}
              />
            </div>

            <div>
              <label htmlFor="description" className={labelClass}>
                Description
              </label>
              <input
                id="description"
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
                className={inputClass}
              />
            </div>

            <div>
              <label htmlFor="permission_set" className={labelClass}>
                Permissions (comma-separated)
              </label>
              <input
                id="permission_set"
                value={form.permission_set}
                onChange={(e) => setForm({ ...form, permission_set: e.target.value })}
                placeholder="read, create, update, delete"
                className={inputClass}
              />
            </div>

            {formError && (
              <p role="alert" className="text-sm text-red-600 dark:text-red-400">
                {formError}
              </p>
            )}

            <div className="flex justify-end gap-2 pt-2">
              <button type="button" onClick={() => setEditing(null)} className={secondaryButtonClass}>
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
