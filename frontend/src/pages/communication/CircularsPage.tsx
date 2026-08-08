import { useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";
import { useAuth } from "../../lib/auth";

interface Circular {
  circular_id: number;
  author_id: number;
  post_type: "Homework" | "Circular" | "Announcement";
  title: string;
  body: string;
  target_audience: string;
  posted_at: string;
  status: "Posted" | "Retracted";
}

interface FormState {
  post_type: Circular["post_type"];
  title: string;
  body: string;
  target_audience: string;
}

const EMPTY_FORM: FormState = { post_type: "Circular", title: "", body: "", target_audience: "" };

export default function CircularsPage() {
  const { user } = useAuth();
  const [targetAudienceInput, setTargetAudienceInput] = useState("");
  const [targetAudience, setTargetAudience] = useState<string | null>(null);
  const [circulars, setCirculars] = useState<Circular[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function reload(audience: string) {
    setIsLoading(true);
    setError(null);
    api
      .get<{ data: Circular[] }>("/communication/circulars", { params: { target_audience: audience } })
      .then((response) => setCirculars(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  function handleSearch(event: FormEvent) {
    event.preventDefault();
    if (targetAudienceInput) {
      setTargetAudience(targetAudienceInput);
      reload(targetAudienceInput);
    }
  }

  function openCreate() {
    setForm({ ...EMPTY_FORM, target_audience: targetAudience ?? "" });
    setFormError(null);
    setIsCreating(true);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.post("/communication/circulars", {
        author_id: user?.userId,
        post_type: form.post_type,
        title: form.title,
        body: form.body,
        target_audience: form.target_audience,
      });
      setIsCreating(false);
      if (targetAudience) reload(targetAudience);
    } catch (err) {
      setFormError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleRetract(circular: Circular) {
    setMessage(null);
    try {
      await api.post(`/communication/circulars/${circular.circular_id}/retract`);
      if (targetAudience) reload(targetAudience);
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Circulars</h2>
        <button type="button" onClick={openCreate} className={primaryButtonClass}>
          New Circular
        </button>
      </div>

      <form onSubmit={handleSearch} className="mb-4 flex gap-2">
        <input
          placeholder="Target audience (e.g. Class-6, All)"
          value={targetAudienceInput}
          onChange={(e) => setTargetAudienceInput(e.target.value)}
          className={`${inputClass} w-64`}
        />
        <button type="submit" className={secondaryButtonClass}>
          Search
        </button>
      </form>

      {message && <p className="mb-3 text-sm text-red-600 dark:text-red-400">{message}</p>}
      {targetAudience === null && <p className="text-sm text-slate-400">Enter a target audience to see circulars.</p>}
      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {targetAudience !== null && !isLoading && !error && (
        <div className="space-y-3">
          {circulars.map((c) => (
            <div key={c.circular_id} className="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
              <div className="mb-1 flex items-center justify-between">
                <span className="text-sm font-semibold text-slate-900 dark:text-slate-100">{c.title}</span>
                <span className="text-xs text-slate-400">{c.post_type} · {c.status}</span>
              </div>
              <p className="text-sm text-slate-600 dark:text-slate-400">{c.body}</p>
              <div className="mt-2 flex items-center justify-between">
                <span className="text-xs text-slate-400">{c.posted_at}</span>
                {c.status === "Posted" && (
                  <button
                    type="button"
                    onClick={() => handleRetract(c)}
                    className="text-xs text-red-600 hover:underline dark:text-red-400"
                  >
                    Retract
                  </button>
                )}
              </div>
            </div>
          ))}
          {circulars.length === 0 && <p className="text-sm text-slate-400">No circulars for this audience.</p>}
        </div>
      )}

      {isCreating && (
        <Modal title="New Circular" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className={labelClass}>Post type</label>
              <select
                value={form.post_type}
                onChange={(e) => setForm({ ...form, post_type: e.target.value as Circular["post_type"] })}
                className={inputClass}
              >
                <option value="Circular">Circular</option>
                <option value="Homework">Homework</option>
                <option value="Announcement">Announcement</option>
              </select>
            </div>
            <div>
              <label className={labelClass}>Title</label>
              <input required value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} className={inputClass} />
            </div>
            <div>
              <label className={labelClass}>Body</label>
              <textarea
                required
                value={form.body}
                onChange={(e) => setForm({ ...form, body: e.target.value })}
                className={`${inputClass} min-h-24`}
              />
            </div>
            <div>
              <label className={labelClass}>Target audience</label>
              <input
                required
                value={form.target_audience}
                onChange={(e) => setForm({ ...form, target_audience: e.target.value })}
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
                {isSubmitting ? "Posting…" : "Post"}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
