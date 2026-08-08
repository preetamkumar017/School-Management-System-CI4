import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { useClasses } from "../../lib/academic";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";

interface Application {
  application_id: number;
  application_reference_no: string;
  applicant_name: string;
  dob: string;
  class_applied_id: number;
  aadhaar_number: string | null;
  category: "GENERAL" | "RTE";
  status: "SUBMITTED" | "VERIFIED" | "SHORTLISTED" | "WAITLISTED" | "ADMITTED" | "REJECTED";
  submitted_at: string;
  decided_at: string | null;
  hold_expires_at: string | null;
}

const STATUS_STYLES: Record<Application["status"], string> = {
  SUBMITTED: "bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-400",
  VERIFIED: "bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-400",
  SHORTLISTED: "bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-400",
  WAITLISTED: "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400",
  ADMITTED: "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400",
  REJECTED: "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-400",
};

// The only forward action(s) each status can take — matches the
// backend's own forward-only transition graph.
const NEXT_ACTIONS: Record<Application["status"], { action: string; label: string }[]> = {
  SUBMITTED: [{ action: "verify", label: "Verify" }],
  VERIFIED: [
    { action: "shortlist", label: "Shortlist" },
    { action: "waitlist", label: "Waitlist" },
    { action: "reject", label: "Reject" },
  ],
  SHORTLISTED: [
    { action: "confirm-enrollment", label: "Confirm Enrollment" },
    { action: "waitlist", label: "Waitlist" },
    { action: "reject", label: "Reject" },
  ],
  WAITLISTED: [
    { action: "confirm-enrollment", label: "Confirm Enrollment" },
    { action: "reject", label: "Reject" },
  ],
  ADMITTED: [],
  REJECTED: [],
};

interface CreateFormState {
  applicant_name: string;
  dob: string;
  class_applied_id: string;
  aadhaar_number: string;
  category: "GENERAL" | "RTE";
}

const EMPTY_FORM: CreateFormState = { applicant_name: "", dob: "", class_applied_id: "", aadhaar_number: "", category: "GENERAL" };

export default function ApplicationsPage() {
  const { classes } = useClasses();
  const [applications, setApplications] = useState<Application[]>([]);
  const [statusFilter, setStatusFilter] = useState<string>("");
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<CreateFormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [actionMessage, setActionMessage] = useState<string | null>(null);
  const [releaseHoldsMessage, setReleaseHoldsMessage] = useState<string | null>(null);

  function classNameFor(id: number): string {
    return classes.find((c) => c.class_id === id)?.class_name ?? `Class #${id}`;
  }

  function reload() {
    setIsLoading(true);
    api
      .get<{ data: Application[] }>("/admission/applications", {
        params: statusFilter ? { status: statusFilter } : {},
      })
      .then((response) => setApplications(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  useEffect(reload, [statusFilter]);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.post("/admission/applications", {
        applicant_name: form.applicant_name,
        dob: form.dob,
        class_applied_id: Number(form.class_applied_id),
        aadhaar_number: form.aadhaar_number || null,
        category: form.category,
      });
      setIsCreating(false);
      reload();
    } catch (err) {
      setFormError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleAction(application: Application, action: string) {
    setActionMessage(null);
    try {
      const response = await api.post<{ data: Record<string, unknown> }>(
        `/admission/applications/${application.application_id}/${action}`,
      );
      if (action === "confirm-enrollment") {
        setActionMessage(`Admitted — Student #${response.data.data.student_id} created.`);
      }
      reload();
    } catch (err) {
      setActionMessage(apiErrorMessage(err));
    }
  }

  async function handleReleaseExpiredHolds() {
    setReleaseHoldsMessage(null);
    try {
      const response = await api.post<{ data: { released_count: number; promoted_count: number } }>(
        "/admission/applications/release-expired-holds",
      );
      const { released_count, promoted_count } = response.data.data;
      setReleaseHoldsMessage(`Released ${released_count}, promoted ${promoted_count} from waitlist.`);
      reload();
    } catch (err) {
      setReleaseHoldsMessage(apiErrorMessage(err));
    }
  }

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Applications</h2>
        <div className="flex gap-2">
          <button type="button" onClick={handleReleaseExpiredHolds} className={secondaryButtonClass}>
            Release Expired Holds
          </button>
          <button
            type="button"
            onClick={() => {
              setForm(EMPTY_FORM);
              setFormError(null);
              setIsCreating(true);
            }}
            className={primaryButtonClass}
          >
            New Application
          </button>
        </div>
      </div>

      {releaseHoldsMessage && <p className="mb-3 text-sm text-slate-500 dark:text-slate-400">{releaseHoldsMessage}</p>}
      {actionMessage && <p className="mb-3 text-sm text-slate-500 dark:text-slate-400">{actionMessage}</p>}

      <div className="mb-4">
        <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className={`${inputClass} w-56`}>
          <option value="">All statuses</option>
          {Object.keys(STATUS_STYLES).map((s) => (
            <option key={s} value={s}>
              {s}
            </option>
          ))}
        </select>
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
                <th className="px-4 py-2 font-medium">Reference #</th>
                <th className="px-4 py-2 font-medium">Applicant</th>
                <th className="px-4 py-2 font-medium">Class applied</th>
                <th className="px-4 py-2 font-medium">Category</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2 font-medium">Hold expires</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {applications.map((application) => (
                <tr key={application.application_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{application.application_reference_no}</td>
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{application.applicant_name}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{classNameFor(application.class_applied_id)}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{application.category}</td>
                  <td className="px-4 py-2">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_STYLES[application.status]}`}>
                      {application.status}
                    </span>
                  </td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{application.hold_expires_at ?? "—"}</td>
                  <td className="px-4 py-2 text-right">
                    <div className="flex justify-end gap-2">
                      {NEXT_ACTIONS[application.status].map(({ action, label }) => (
                        <button
                          key={action}
                          type="button"
                          onClick={() => handleAction(application, action)}
                          className="text-xs text-slate-600 hover:underline dark:text-slate-400"
                        >
                          {label}
                        </button>
                      ))}
                    </div>
                  </td>
                </tr>
              ))}
              {applications.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-4 py-6 text-center text-slate-400">
                    No applications.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && (
        <Modal title="New Application" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className={labelClass}>Applicant name</label>
              <input
                required
                value={form.applicant_name}
                onChange={(e) => setForm({ ...form, applicant_name: e.target.value })}
                className={inputClass}
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className={labelClass}>Date of birth</label>
                <input
                  required
                  type="date"
                  value={form.dob}
                  onChange={(e) => setForm({ ...form, dob: e.target.value })}
                  className={inputClass}
                />
              </div>
              <div>
                <label className={labelClass}>Category</label>
                <select
                  value={form.category}
                  onChange={(e) => setForm({ ...form, category: e.target.value as CreateFormState["category"] })}
                  className={inputClass}
                >
                  <option value="GENERAL">General</option>
                  <option value="RTE">RTE</option>
                </select>
              </div>
            </div>
            <div>
              <label className={labelClass}>Class applied for</label>
              <select
                required
                value={form.class_applied_id}
                onChange={(e) => setForm({ ...form, class_applied_id: e.target.value })}
                className={inputClass}
              >
                <option value="" disabled>
                  Select class
                </option>
                {classes.map((c) => (
                  <option key={c.class_id} value={c.class_id}>
                    {c.class_name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className={labelClass}>Aadhaar number (optional)</label>
              <input
                value={form.aadhaar_number}
                onChange={(e) => setForm({ ...form, aadhaar_number: e.target.value })}
                placeholder="12-digit, checksum-valid"
                className={inputClass}
              />
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
                {isSubmitting ? "Submitting…" : "Submit"}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
