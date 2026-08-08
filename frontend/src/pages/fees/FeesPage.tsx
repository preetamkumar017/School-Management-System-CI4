import { useState } from "react";
import FeeHeadsPage from "./FeeHeadsPage";
import FeeStructuresPage from "./FeeStructuresPage";
import InvoicesPage from "./InvoicesPage";
import ScholarshipWaiversPage from "./ScholarshipWaiversPage";

const TABS = [
  { key: "invoices", label: "Invoices" },
  { key: "fee-heads", label: "Fee Heads" },
  { key: "fee-structures", label: "Fee Structures" },
  { key: "waivers", label: "Waivers" },
] as const;

type TabKey = (typeof TABS)[number]["key"];

export default function FeesPage() {
  const [tab, setTab] = useState<TabKey>("invoices");

  return (
    <div>
      <div className="mb-6 flex gap-1 border-b border-slate-200 dark:border-slate-800">
        {TABS.map((t) => (
          <button
            key={t.key}
            type="button"
            onClick={() => setTab(t.key)}
            className={`border-b-2 px-4 py-2 text-sm font-medium transition ${
              tab === t.key
                ? "border-slate-900 text-slate-900 dark:border-slate-100 dark:text-slate-100"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {tab === "invoices" && <InvoicesPage />}
      {tab === "fee-heads" && <FeeHeadsPage />}
      {tab === "fee-structures" && <FeeStructuresPage />}
      {tab === "waivers" && <ScholarshipWaiversPage />}
    </div>
  );
}
