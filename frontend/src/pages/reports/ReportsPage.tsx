import { useState } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { useAcademicSessions } from "../../lib/academic";
import { inputClass, labelClass, secondaryButtonClass } from "../../components/ui/form";

interface FeeCollectionSummary {
  academic_session_id: number;
  total_collected: number;
  total_outstanding: number;
  collected_by_class: Record<string, number>;
  outstanding_by_class: Record<string, number>;
  defaulter_count: number;
  generated_at: string;
}

interface AttendanceOverview {
  from_date: string;
  to_date: string;
  school_wide_percentage: number;
  percentage_by_class: Record<string, number>;
  students_below_threshold: number[];
  eligibility_threshold: number;
  generated_at: string;
}

interface AdmissionsFunnel {
  academic_session_id: number;
  counts_by_status: Record<string, number>;
  seat_occupancy_by_class: Record<string, unknown>;
  generated_at: string;
}

interface AcademicPerformance {
  exam_id: number;
  report_card_count: number;
  average_gpa: number;
  pass_count: number;
  fail_count: number;
  pass_threshold_gpa: number;
  rank_distribution: Record<string, number>;
  generated_at: string;
}

async function downloadReport(path: string, extension: string) {
  const response = await api.get(path, { responseType: "blob" });
  const blobUrl = URL.createObjectURL(response.data as Blob);
  const a = document.createElement("a");
  a.href = blobUrl;
  a.download = `report.${extension}`;
  a.click();
}

function StatTile({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
      <p className="text-xs font-medium text-slate-500 dark:text-slate-400">{label}</p>
      <p className="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{value}</p>
    </div>
  );
}

function ExportButtons({ area, params }: { area: string; params: Record<string, string | number> }) {
  const qs = new URLSearchParams(params as Record<string, string>).toString();
  return (
    <div className="flex gap-2">
      <button
        type="button"
        onClick={() => downloadReport(`/reports/${area}/pdf?${qs}`, "pdf")}
        className={secondaryButtonClass}
      >
        PDF
      </button>
      <button
        type="button"
        onClick={() => downloadReport(`/reports/${area}/excel?${qs}`, "xlsx")}
        className={secondaryButtonClass}
      >
        Excel
      </button>
    </div>
  );
}

export default function ReportsPage() {
  const { sessions } = useAcademicSessions();

  const [feeSessionId, setFeeSessionId] = useState("");
  const [feeSummary, setFeeSummary] = useState<FeeCollectionSummary | null>(null);
  const [feeError, setFeeError] = useState<string | null>(null);

  const [fromDate, setFromDate] = useState("");
  const [toDate, setToDate] = useState("");
  const [attendance, setAttendance] = useState<AttendanceOverview | null>(null);
  const [attendanceError, setAttendanceError] = useState<string | null>(null);

  const [funnelSessionId, setFunnelSessionId] = useState("");
  const [funnel, setFunnel] = useState<AdmissionsFunnel | null>(null);
  const [funnelError, setFunnelError] = useState<string | null>(null);

  const [examId, setExamId] = useState("");
  const [performance, setPerformance] = useState<AcademicPerformance | null>(null);
  const [performanceError, setPerformanceError] = useState<string | null>(null);

  async function loadFeeCollection() {
    setFeeError(null);
    try {
      const response = await api.get<{ data: FeeCollectionSummary }>("/reports/fee-collection", {
        params: { academic_session_id: feeSessionId },
      });
      setFeeSummary(response.data.data);
    } catch (err) {
      setFeeError(apiErrorMessage(err));
    }
  }

  async function loadAttendance() {
    setAttendanceError(null);
    try {
      const response = await api.get<{ data: AttendanceOverview }>("/reports/attendance-overview", {
        params: { from_date: fromDate, to_date: toDate },
      });
      setAttendance(response.data.data);
    } catch (err) {
      setAttendanceError(apiErrorMessage(err));
    }
  }

  async function loadFunnel() {
    setFunnelError(null);
    try {
      const response = await api.get<{ data: AdmissionsFunnel }>("/reports/admissions-funnel", {
        params: { academic_session_id: funnelSessionId },
      });
      setFunnel(response.data.data);
    } catch (err) {
      setFunnelError(apiErrorMessage(err));
    }
  }

  async function loadPerformance() {
    setPerformanceError(null);
    try {
      const response = await api.get<{ data: AcademicPerformance }>("/reports/academic-performance", {
        params: { exam_id: examId },
      });
      setPerformance(response.data.data);
    } catch (err) {
      setPerformanceError(apiErrorMessage(err));
    }
  }

  return (
    <div className="space-y-10">
      <section>
        <div className="mb-3 flex items-center justify-between">
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Fee Collection Summary</h2>
          {feeSummary && <ExportButtons area="fee-collection" params={{ academic_session_id: feeSessionId }} />}
        </div>
        <div className="mb-3 flex gap-2">
          <select value={feeSessionId} onChange={(e) => setFeeSessionId(e.target.value)} className={`${inputClass} w-56`}>
            <option value="">Select academic session</option>
            {sessions.map((s) => (
              <option key={s.academic_session_id} value={s.academic_session_id}>
                {s.session_name}
              </option>
            ))}
          </select>
          <button type="button" onClick={loadFeeCollection} disabled={!feeSessionId} className={secondaryButtonClass}>
            Load
          </button>
        </div>
        {feeError && <p className="text-sm text-red-600 dark:text-red-400">{feeError}</p>}
        {feeSummary && (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <StatTile label="Total collected" value={`₹${feeSummary.total_collected}`} />
            <StatTile label="Total outstanding" value={`₹${feeSummary.total_outstanding}`} />
            <StatTile label="Defaulters" value={feeSummary.defaulter_count} />
          </div>
        )}
      </section>

      <section>
        <div className="mb-3 flex items-center justify-between">
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Attendance Overview</h2>
          {attendance && <ExportButtons area="attendance-overview" params={{ from_date: fromDate, to_date: toDate }} />}
        </div>
        <div className="mb-3 flex gap-2">
          <input type="date" value={fromDate} onChange={(e) => setFromDate(e.target.value)} className={inputClass} />
          <input type="date" value={toDate} onChange={(e) => setToDate(e.target.value)} className={inputClass} />
          <button type="button" onClick={loadAttendance} disabled={!fromDate || !toDate} className={secondaryButtonClass}>
            Load
          </button>
        </div>
        {attendanceError && <p className="text-sm text-red-600 dark:text-red-400">{attendanceError}</p>}
        {attendance && (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <StatTile label="School-wide %" value={`${attendance.school_wide_percentage}%`} />
            <StatTile label="Eligibility threshold" value={`${attendance.eligibility_threshold}%`} />
            <StatTile label="Students below threshold" value={attendance.students_below_threshold.length} />
          </div>
        )}
      </section>

      <section>
        <div className="mb-3 flex items-center justify-between">
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Admissions Funnel</h2>
          {funnel && <ExportButtons area="admissions-funnel" params={{ academic_session_id: funnelSessionId }} />}
        </div>
        <div className="mb-3 flex gap-2">
          <select value={funnelSessionId} onChange={(e) => setFunnelSessionId(e.target.value)} className={`${inputClass} w-56`}>
            <option value="">Select academic session</option>
            {sessions.map((s) => (
              <option key={s.academic_session_id} value={s.academic_session_id}>
                {s.session_name}
              </option>
            ))}
          </select>
          <button type="button" onClick={loadFunnel} disabled={!funnelSessionId} className={secondaryButtonClass}>
            Load
          </button>
        </div>
        {funnelError && <p className="text-sm text-red-600 dark:text-red-400">{funnelError}</p>}
        {funnel && (
          <div className="flex flex-wrap gap-3">
            {Object.entries(funnel.counts_by_status).map(([status, count]) => (
              <StatTile key={status} label={status} value={count} />
            ))}
          </div>
        )}
      </section>

      <section>
        <div className="mb-3 flex items-center justify-between">
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Academic Performance</h2>
          {performance && <ExportButtons area="academic-performance" params={{ exam_id: examId }} />}
        </div>
        <div className="mb-3 flex gap-2">
          <input
            type="number"
            min={1}
            placeholder="Exam ID"
            value={examId}
            onChange={(e) => setExamId(e.target.value)}
            className={`${inputClass} w-40`}
          />
          <button type="button" onClick={loadPerformance} disabled={!examId} className={secondaryButtonClass}>
            Load
          </button>
        </div>
        {performanceError && <p className="text-sm text-red-600 dark:text-red-400">{performanceError}</p>}
        {performance && (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <StatTile label="Report cards" value={performance.report_card_count} />
            <StatTile label="Average GPA" value={performance.average_gpa} />
            <StatTile label="Pass" value={performance.pass_count} />
            <StatTile label="Fail" value={performance.fail_count} />
          </div>
        )}
      </section>

      <p className={labelClass}>
        Reports are a pure composition layer over other modules' own data (ADR-010 §7/ADR-022) — nothing is stored
        here.
      </p>
    </div>
  );
}
