import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";

export interface Book {
  book_id: number;
  barcode: string;
  title: string;
  author: string | null;
  classification: "Circulating" | "Reference";
  is_available: boolean;
}

interface FormState {
  barcode: string;
  title: string;
  author: string;
  classification: Book["classification"];
}

const EMPTY_FORM: FormState = { barcode: "", title: "", author: "", classification: "Circulating" };

export function useBooks() {
  const [books, setBooks] = useState<Book[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  function reload() {
    setIsLoading(true);
    api
      .get<{ data: Book[] }>("/library/books")
      .then((response) => setBooks(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  useEffect(reload, []);

  return { books, isLoading, error, reload };
}

export default function BooksPage() {
  const { books, isLoading, error, reload } = useBooks();
  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

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
      await api.post("/library/books", {
        barcode: form.barcode,
        title: form.title,
        author: form.author || null,
        classification: form.classification,
      });
      setIsCreating(false);
      reload();
    } catch (err) {
      setFormError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Books</h2>
        <button type="button" onClick={openCreate} className={primaryButtonClass}>
          New Book
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
                <th className="px-4 py-2 font-medium">Barcode</th>
                <th className="px-4 py-2 font-medium">Title</th>
                <th className="px-4 py-2 font-medium">Author</th>
                <th className="px-4 py-2 font-medium">Classification</th>
                <th className="px-4 py-2 font-medium">Available?</th>
              </tr>
            </thead>
            <tbody>
              {books.map((b) => (
                <tr key={b.book_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{b.barcode}</td>
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{b.title}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{b.author ?? "—"}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{b.classification}</td>
                  <td className="px-4 py-2">
                    <span
                      className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                        b.is_available
                          ? "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400"
                          : "bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-400"
                      }`}
                    >
                      {b.is_available ? "Available" : "Issued"}
                    </span>
                  </td>
                </tr>
              ))}
              {books.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-slate-400">
                    No books yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && (
        <Modal title="New Book" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className={labelClass}>Barcode</label>
              <input required value={form.barcode} onChange={(e) => setForm({ ...form, barcode: e.target.value })} className={inputClass} />
            </div>
            <div>
              <label className={labelClass}>Title</label>
              <input required value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} className={inputClass} />
            </div>
            <div>
              <label className={labelClass}>Author</label>
              <input value={form.author} onChange={(e) => setForm({ ...form, author: e.target.value })} className={inputClass} />
            </div>
            <div>
              <label className={labelClass}>Classification</label>
              <select
                value={form.classification}
                onChange={(e) => setForm({ ...form, classification: e.target.value as Book["classification"] })}
                className={inputClass}
              >
                <option value="Circulating">Circulating</option>
                <option value="Reference">Reference</option>
              </select>
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
