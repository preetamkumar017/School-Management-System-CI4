import { useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";
import { useBooks } from "./BooksPage";

interface Reservation {
  reservation_id: number;
  book_id: number;
  borrower_type: "Student" | "Employee";
  borrower_ref_id: number;
  requested_at: string;
  status: "Waiting" | "Notified" | "Fulfilled" | "Expired" | "Cancelled";
  notified_at: string | null;
  notification_expires_at: string | null;
}

interface FormState {
  borrower_type: "Student" | "Employee";
  borrower_ref_id: string;
}

export default function ReservationsPage() {
  const { books } = useBooks();
  const [bookId, setBookId] = useState<number | null>(null);
  const [borrowerType, setBorrowerType] = useState<"Student" | "Employee">("Student");
  const [borrowerRefIdInput, setBorrowerRefIdInput] = useState("");
  const [borrowerRefId, setBorrowerRefId] = useState<number | null>(null);
  const [reservations, setReservations] = useState<Reservation[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>({ borrower_type: "Student", borrower_ref_id: "" });
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function bookTitle(id: number): string {
    return books.find((b) => b.book_id === id)?.title ?? `Book #${id}`;
  }

  function reload() {
    if (bookId === null || borrowerRefId === null) return;
    setIsLoading(true);
    setError(null);
    api
      .get<{ data: Reservation[] }>("/library/reservations", {
        params: { book_id: bookId, borrower_type: borrowerType, borrower_ref_id: borrowerRefId },
      })
      .then((response) => setReservations(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  function handleSearch(event: FormEvent) {
    event.preventDefault();
    const bId = bookId;
    const refId = Number(borrowerRefIdInput);
    if (bId && refId > 0) {
      setBorrowerRefId(refId);
      setTimeout(reload, 0);
    }
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    if (bookId === null) return;
    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.post("/library/reservations", {
        book_id: bookId,
        borrower_type: form.borrower_type,
        borrower_ref_id: Number(form.borrower_ref_id),
      });
      setIsCreating(false);
      reload();
    } catch (err) {
      setFormError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleCancel(reservation: Reservation) {
    setMessage(null);
    try {
      await api.post(`/library/reservations/${reservation.reservation_id}/cancel`);
      reload();
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  async function handleProcessExpired() {
    setMessage(null);
    try {
      const response = await api.post<{ data: unknown }>("/library/reservations/process-expired-notifications");
      setMessage(`Processed: ${JSON.stringify(response.data.data)}`);
      reload();
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Reservations</h2>
        <div className="flex gap-2">
          <button type="button" onClick={handleProcessExpired} className={secondaryButtonClass}>
            Process Expired Notifications
          </button>
          {bookId !== null && (
            <button
              type="button"
              onClick={() => {
                setForm({ borrower_type: borrowerType, borrower_ref_id: borrowerRefId ? String(borrowerRefId) : "" });
                setFormError(null);
                setIsCreating(true);
              }}
              className={primaryButtonClass}
            >
              New Reservation
            </button>
          )}
        </div>
      </div>

      <form onSubmit={handleSearch} className="mb-4 flex flex-wrap gap-2">
        <select
          value={bookId ?? ""}
          onChange={(e) => setBookId(e.target.value ? Number(e.target.value) : null)}
          className={`${inputClass} w-56`}
        >
          <option value="">Select book</option>
          {books.map((b) => (
            <option key={b.book_id} value={b.book_id}>
              {b.title}
            </option>
          ))}
        </select>
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

      {message && <p className="mb-3 text-sm text-slate-500 dark:text-slate-400">{message}</p>}
      {(bookId === null || borrowerRefId === null) && (
        <p className="text-sm text-slate-400">Pick a book and search a borrower to see their reservations.</p>
      )}
      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {bookId !== null && borrowerRefId !== null && !isLoading && !error && (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th className="px-4 py-2 font-medium">Book</th>
                <th className="px-4 py-2 font-medium">Requested at</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2 font-medium">Notification expires</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {reservations.map((r) => (
                <tr key={r.reservation_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{bookTitle(r.book_id)}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{r.requested_at}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{r.status}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{r.notification_expires_at ?? "—"}</td>
                  <td className="px-4 py-2 text-right">
                    {(r.status === "Waiting" || r.status === "Notified") && (
                      <button
                        type="button"
                        onClick={() => handleCancel(r)}
                        className="text-xs text-red-600 hover:underline dark:text-red-400"
                      >
                        Cancel
                      </button>
                    )}
                  </td>
                </tr>
              ))}
              {reservations.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-slate-400">
                    No reservations for this book/borrower.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && bookId !== null && (
        <Modal title="New Reservation" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleSubmit} className="space-y-4">
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
