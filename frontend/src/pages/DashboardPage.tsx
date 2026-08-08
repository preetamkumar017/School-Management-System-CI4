import { useEffect, useState } from "react";
import { api, apiErrorMessage } from "../lib/api";

interface SummaryData {
  generated_at: string;
  total_users: number;
  total_classes: number;
  total_academic_sessions: number;
  total_departments: number;
  total_designations: number;
  total_employees: number;
  total_books: number;
  books_available: number;
  total_vehicles: number;
  total_routes: number;
  total_fee_heads: number;
}

const TILES: { key: keyof SummaryData; label: string }[] = [
  { key: "total_users", label: "Users" },
  { key: "total_classes", label: "Classes" },
  { key: "total_academic_sessions", label: "Academic Sessions" },
  { key: "total_employees", label: "Employees" },
  { key: "total_departments", label: "Departments" },
  { key: "total_books", label: "Books" },
  { key: "books_available", label: "Books Available" },
  { key: "total_vehicles", label: "Vehicles" },
  { key: "total_routes", label: "Routes" },
  { key: "total_fee_heads", label: "Fee Heads" },
];

export default function DashboardPage() {
  const [summary, setSummary] = useState<SummaryData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;

    api
      .get<{ data: SummaryData }>("/reports/summary")
      .then((response) => {
        if (!cancelled) setSummary(response.data.data);
      })
      .catch((err) => {
        if (!cancelled) setError(apiErrorMessage(err));
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div>
      <h1 className="mb-6 text-lg font-semibold text-slate-900 dark:text-slate-100">Dashboard</h1>

      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}

      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {summary && (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
          {TILES.map((tile) => (
            <div
              key={tile.key}
              className="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950"
            >
              <p className="text-xs font-medium text-slate-500 dark:text-slate-400">{tile.label}</p>
              <p className="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{summary[tile.key]}</p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
