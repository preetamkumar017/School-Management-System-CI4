import { useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";

interface NotificationLog {
  notification_log_id: number;
  recipient_type: "Guardian" | "Employee" | "User" | "Student";
  recipient_ref_id: number;
  channel: "SMS" | "Email" | "Push";
  trigger_event: string;
  message_body: string | null;
  status: "Queued" | "Dispatched" | "Delivered" | "Failed";
  dispatched_at: string | null;
  failure_reason: string | null;
}

interface FormState {
  recipient_type: NotificationLog["recipient_type"];
  recipient_ref_id: string;
  channel: NotificationLog["channel"];
  trigger_event: string;
  message_body: string;
}

const EMPTY_FORM: FormState = {
  recipient_type: "Guardian",
  recipient_ref_id: "",
  channel: "SMS",
  trigger_event: "",
  message_body: "",
};

const STATUS_STYLES: Record<NotificationLog["status"], string> = {
  Queued: "bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-400",
  Dispatched: "bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-400",
  Delivered: "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400",
  Failed: "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-400",
};

export default function NotificationLogsPage() {
  const [recipientType, setRecipientType] = useState<NotificationLog["recipient_type"]>("Guardian");
  const [recipientRefIdInput, setRecipientRefIdInput] = useState("");
  const [recipientRefId, setRecipientRefId] = useState<number | null>(null);
  const [logs, setLogs] = useState<NotificationLog[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function reload(type: NotificationLog["recipient_type"], refId: number) {
    setIsLoading(true);
    setError(null);
    api
      .get<{ data: NotificationLog[] }>("/communication/notification-logs", {
        params: { recipient_type: type, recipient_ref_id: refId },
      })
      .then((response) => setLogs(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  function handleSearch(event: FormEvent) {
    event.preventDefault();
    const id = Number(recipientRefIdInput);
    if (id > 0) {
      setRecipientRefId(id);
      reload(recipientType, id);
    }
  }

  function openCreate() {
    setForm({ ...EMPTY_FORM, recipient_type: recipientType, recipient_ref_id: recipientRefId ? String(recipientRefId) : "" });
    setFormError(null);
    setIsCreating(true);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.post("/communication/notification-logs", {
        recipient_type: form.recipient_type,
        recipient_ref_id: Number(form.recipient_ref_id),
        channel: form.channel,
        trigger_event: form.trigger_event,
        message_body: form.message_body || null,
      });
      setIsCreating(false);
      if (recipientRefId) reload(recipientType, recipientRefId);
    } catch (err) {
      setFormError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDispatch(log: NotificationLog) {
    setMessage(null);
    try {
      await api.post(`/communication/notification-logs/${log.notification_log_id}/dispatch`);
      if (recipientRefId) reload(recipientType, recipientRefId);
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Notification Logs</h2>
        <button type="button" onClick={openCreate} className={primaryButtonClass}>
          New Notification Log
        </button>
      </div>

      <p className="mb-4 text-sm text-slate-400">
        MSG91 gateway is wired (ADR-021) but needs real credentials in <code className="rounded bg-slate-100 px-1 dark:bg-slate-900">backend/.env</code> to
        actually send — otherwise Dispatch marks it Failed gracefully. Only Guardian (direct) and Student
        (via primary-contact Guardian) recipients can actually be dispatched; Employee/User have no
        contact field in the approved schema.
      </p>

      <form onSubmit={handleSearch} className="mb-4 flex gap-2">
        <select
          value={recipientType}
          onChange={(e) => setRecipientType(e.target.value as NotificationLog["recipient_type"])}
          className={inputClass}
        >
          <option value="Guardian">Guardian</option>
          <option value="Student">Student</option>
          <option value="Employee">Employee</option>
          <option value="User">User</option>
        </select>
        <input
          type="number"
          min={1}
          placeholder="Recipient ID"
          value={recipientRefIdInput}
          onChange={(e) => setRecipientRefIdInput(e.target.value)}
          className={`${inputClass} w-40`}
        />
        <button type="submit" className={secondaryButtonClass}>
          Search
        </button>
      </form>

      {message && <p className="mb-3 text-sm text-red-600 dark:text-red-400">{message}</p>}
      {recipientRefId === null && <p className="text-sm text-slate-400">Search a recipient to see their notification logs.</p>}
      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {recipientRefId !== null && !isLoading && !error && (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th className="px-4 py-2 font-medium">Channel</th>
                <th className="px-4 py-2 font-medium">Trigger</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2 font-medium">Failure reason</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {logs.map((log) => (
                <tr key={log.notification_log_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{log.channel}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{log.trigger_event}</td>
                  <td className="px-4 py-2">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_STYLES[log.status]}`}>
                      {log.status}
                    </span>
                  </td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{log.failure_reason ?? "—"}</td>
                  <td className="px-4 py-2 text-right">
                    {log.status === "Queued" && (
                      <button
                        type="button"
                        onClick={() => handleDispatch(log)}
                        className="text-xs text-slate-600 hover:underline dark:text-slate-400"
                      >
                        Dispatch
                      </button>
                    )}
                  </td>
                </tr>
              ))}
              {logs.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-slate-400">
                    No notification logs for this recipient.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && (
        <Modal title="New Notification Log" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className={labelClass}>Recipient type</label>
                <select
                  value={form.recipient_type}
                  onChange={(e) => setForm({ ...form, recipient_type: e.target.value as FormState["recipient_type"] })}
                  className={inputClass}
                >
                  <option value="Guardian">Guardian</option>
                  <option value="Student">Student</option>
                  <option value="Employee">Employee</option>
                  <option value="User">User</option>
                </select>
              </div>
              <div>
                <label className={labelClass}>Recipient ID</label>
                <input
                  required
                  type="number"
                  min={1}
                  value={form.recipient_ref_id}
                  onChange={(e) => setForm({ ...form, recipient_ref_id: e.target.value })}
                  className={inputClass}
                />
              </div>
            </div>
            <div>
              <label className={labelClass}>Channel</label>
              <select value={form.channel} onChange={(e) => setForm({ ...form, channel: e.target.value as FormState["channel"] })} className={inputClass}>
                <option value="SMS">SMS</option>
                <option value="Email">Email</option>
                <option value="Push">Push</option>
              </select>
            </div>
            <div>
              <label className={labelClass}>Trigger event</label>
              <input
                required
                value={form.trigger_event}
                onChange={(e) => setForm({ ...form, trigger_event: e.target.value })}
                placeholder="e.g. Fee Due Reminder"
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>Message body</label>
              <textarea
                value={form.message_body}
                onChange={(e) => setForm({ ...form, message_body: e.target.value })}
                className={`${inputClass} min-h-20`}
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
                {isSubmitting ? "Saving…" : "Save"}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
