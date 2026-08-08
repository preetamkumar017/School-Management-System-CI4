import { useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";
import { useBooks } from "./BooksPage";

interface BookIssue {
  book_issue_id: number;
  book_id: number;
  borrower_type: "Student" | "Employee";
  borrower_ref_id: number;
  issue_date: string;
  due_date: string;
  return_date: string | null;
  fine_amount: number;
  status: "Issued" | "Returned" | "Lost";
  replacement_charge_amount: number | null;
  fine_settled: boolean;
}

interface FormState {
  book_id: string;
  borrower_type: "Student" | "Employee";
  borrower_ref_id: string;
  due_date: string;
}

const EMPTY_FORM: FormState = { book_id: "", borrower_type: "Student", borrower_ref_id: "", due_date: "" };

export default function BookIssuesPage() {
  const { books } = useBooks();
  const [borrowerType, setBorrowerType] = useState<"Student" | "Employee">("Student");
  const [borrowerRefIdInput, setBorrowerRefIdInput] = useState("");
  const [borrowerRefId, setBorrowerRefId] = useState<number | null>(null);
  const [issues, setIssues] = useState<BookIssue[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function bookTitle(id: number): string {
    return books.find((b) => b.book_id === id)?.title ?? `Book #${id}`;
  }

  function reload(type: "Student" | "Employee", refId: number) {
    setIsLoading(true);
    setError(null);
    api
      .get<{ data: BookIssue[] }>("/library/book-issues", { params: { borrower_type: type, borrower_ref_id: refId } })
      .then((response) => setIssues(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  function handleSearch(event: FormEvent) {
    event.preventDefault();
    const id = Number(borrowerRefIdInput);
    if (id > 0) {
      setBorrowerRefId(id);
      reload(borrowerType, id);
    }
  }

  function openCreate() {
    setForm({
      book_id: "",
      borrower_type: borrowerType,
      borrower_ref_id: borrowerRefId ? String(borrowerRefId) : "",
      due_date: "",
    });
    setFormError(null);
    setIsCreating(true);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.post("/library/book-issues", {
        book_id: Number(form.book_id),
        borrower_type: form.borrower_type,
        borrower_ref_id: Number(form.borrower_ref_id),
        due_date: form.due_date,
      });
      setIsCreating(false);
      if (borrowerRefId) reload(borrowerType, borrowerRefId);
    } catch (err) {
      setFormError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleReturn(issue: BookIssue) {
    setMessage(null);
    try {
      await api.post(`/library/book-issues/${issue.book_issue_id}/return`);
      if (borrowerRefId) reload(borrowerType, borrowerRefId);
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  async function handleReportLost(issue: BookIssue) {
    setMessage(null);
    try {
      await api.post(`/library/book-issues/${issue.book_issue_id}/report-lost`);
      if (borrowerRefId) reload(borrowerType, borrowerRefId);
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  async function handleSettleFine(issue: BookIssue) {
    setMessage(null);
    try {
      await api.post(`/library/book-issues/${issue.book_issue_id}/settle-fine`);
      if (borrowerRefId) reload(borrowerType, borrowerRefId);
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Book Issues</h2>
        <button type="button" onClick={openCreate} className={primaryButtonClass}>
          New Issue
        </button>
      </div>

      <form onSubmit={handleSearch} className="mb-4 flex gap-2">
        <select value={borrowerType} onChange={(e) => setBorrowerType(e.target.value as "Student" | "Employee")} className={inputClass}>
          <option value="Student">Student</option>
          <option value="Employee">Employee</option>
        </select>
        <input
          type="number"
          min={1}
          placeholder="Borrower ID"
          value={borrowerRefIdInput}
          onChange={(e) => setBorrowerRefIdInput(e.target.value)}
          className={`${inputClass} w-40`}
        />
        <button type="submit" className={secondaryButtonClass}>
          Search
        </button>
      </form>

      {message && <p className="mb-3 text-sm text-red-600 dark:text-red-400">{message}</p>}
      {borrowerRefId === null && <p className="text-sm text-slate-400">Search a borrower to see their book issues.</p>}
      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {borrowerRefId !== null && !isLoading && !error && (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th className="px-4 py-2 font-medium">Book</th>
                <th className="px-4 py-2 font-medium">Due</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2 font-medium">Fine</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {issues.map((issue) => (
                <tr key={issue.book_issue_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{bookTitle(issue.book_id)}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{issue.due_date}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{issue.status}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">
                    ₹{issue.fine_amount} {issue.fine_settled && "(settled)"}
                  </td>
                  <td className="px-4 py-2 text-right">
                    {issue.status === "Issued" && (
                      <>
                        <button
                          type="button"
                          onClick={() => handleReturn(issue)}
                          className="mr-2 text-xs text-green-700 hover:underline dark:text-green-400"
                        >
                          Return
                        </button>
                        <button
                          type="button"
                          onClick={() => handleReportLost(issue)}
                          className="mr-2 text-xs text-red-600 hover:underline dark:text-red-400"
                        >
                          Report Lost
                        </button>
                      </>
                    )}
                    {issue.fine_amount > 0 && !issue.fine_settled && (
                      <button
                        type="button"
                        onClick={() => handleSettleFine(issue)}
                        className="text-xs text-amber-700 hover:underline dark:text-amber-400"
                      >
                        Settle Fine
                      </button>
                    )}
                  </td>
                </tr>
              ))}
              {issues.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-slate-400">
                    No book issues for this borrower.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && (
        <Modal title="New Book Issue" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className={labelClass}>Book</label>
              <select
                required
                value={form.book_id}
                onChange={(e) => setForm({ ...form, book_id: e.target.value })}
                className={inputClass}
              >
                <option value="" disabled>
                  Select book
                </option>
                {books.filter((b) => b.is_available).map((b) => (
                  <option key={b.book_id} value={b.book_id}>
                    {b.title}
                  </option>
                ))}
              </select>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className={labelClass}>Borrower type</label>
                <select
                  value={form.borrower_type}
                  onChange={(e) => setForm({ ...form, borrower_type: e.target.value as FormState["borrower_type"] })}
                  className={inputClass}
                >
                  <option value="Student">Student</option>
                  <option value="Employee">Employee</option>
                </select>
              </div>
              <div>
                <label className={labelClass}>Borrower ID</label>
                <input
                  required
                  type="number"
                  min={1}
                  value={form.borrower_ref_id}
                  onChange={(e) => setForm({ ...form, borrower_ref_id: e.target.value })}
                  className={inputClass}
                />
              </div>
            </div>
            <div>
              <label className={labelClass}>Due date</label>
              <input
                required
                type="date"
                value={form.due_date}
                onChange={(e) => setForm({ ...form, due_date: e.target.value })}
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
                {isSubmitting ? "Saving…" : "Save"}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
