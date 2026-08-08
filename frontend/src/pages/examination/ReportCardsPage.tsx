import { useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { inputClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";

interface ReportCard {
  report_card_id: number;
  student_id: number;
  exam_id: number;
  grade_summary: unknown;
  gpa: number;
  class_rank: number;
  is_published: boolean;
  published_at: string | null;
}

export default function ReportCardsPage() {
  const [examIdInput, setExamIdInput] = useState("");
  const [examId, setExamId] = useState<number | null>(null);
  const [reportCards, setReportCards] = useState<ReportCard[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [isPublishing, setIsPublishing] = useState(false);

  function reload(forExamId: number) {
    setIsLoading(true);
    setError(null);
    api
      .get<{ data: ReportCard[] }>("/examination/report-cards", { params: { exam_id: forExamId } })
      .then((response) => setReportCards(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  function handleSearch(event: FormEvent) {
    event.preventDefault();
    const id = Number(examIdInput);
    if (id > 0) {
      setExamId(id);
      reload(id);
    }
  }

  async function handlePublish() {
    if (examId === null) return;
    setMessage(null);
    setIsPublishing(true);
    try {
      await api.post("/examination/report-cards/publish", null, { params: { exam_id: examId } });
      setMessage("Report cards published (BR-EXM-001) — Exam locked -> closed.");
      reload(examId);
    } catch (err) {
      setMessage(apiErrorMessage(err));
    } finally {
      setIsPublishing(false);
    }
  }

  async function handleGeneratePdf(reportCard: ReportCard) {
    setMessage(null);
    try {
      const response = await api.post<{ data: { document_id: number } }>(
        `/examination/report-cards/${reportCard.report_card_id}/generate-pdf`,
      );
      const documentId = response.data.data.document_id;
      const pdfResponse = await api.get(`/administration/documents/${documentId}/download`, { responseType: "blob" });
      const blobUrl = URL.createObjectURL(pdfResponse.data as Blob);
      window.open(blobUrl, "_blank");
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Report Cards</h2>
        {examId !== null && (
          <button type="button" onClick={handlePublish} disabled={isPublishing} className={primaryButtonClass}>
            {isPublishing ? "Publishing…" : "Publish All (exam must be Locked)"}
          </button>
        )}
      </div>

      <form onSubmit={handleSearch} className="mb-4 flex gap-2">
        <input
          type="number"
          min={1}
          placeholder="Exam ID"
          value={examIdInput}
          onChange={(e) => setExamIdInput(e.target.value)}
          className={`${inputClass} w-40`}
        />
        <button type="submit" className={secondaryButtonClass}>
          Search
        </button>
      </form>

      {message && <p className="mb-3 text-sm text-slate-500 dark:text-slate-400">{message}</p>}
      {examId === null && (
        <p className="text-sm text-slate-400">
          Enter an Exam ID to see report cards. Report cards are auto-generated when an exam is locked (every marks
          record must be locked first).
        </p>
      )}
      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {examId !== null && !isLoading && !error && (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th className="px-4 py-2 font-medium">Student</th>
                <th className="px-4 py-2 font-medium">GPA</th>
                <th className="px-4 py-2 font-medium">Class rank</th>
                <th className="px-4 py-2 font-medium">Published?</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {reportCards.map((rc) => (
                <tr key={rc.report_card_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">#{rc.student_id}</td>
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{rc.gpa}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{rc.class_rank}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{rc.is_published ? "Yes" : "No"}</td>
                  <td className="px-4 py-2 text-right">
                    <button
                      type="button"
                      onClick={() => handleGeneratePdf(rc)}
                      className="text-xs text-slate-600 hover:underline dark:text-slate-400"
                    >
                      Generate PDF
                    </button>
                  </td>
                </tr>
              ))}
              {reportCards.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-slate-400">
                    No report cards for this exam.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
