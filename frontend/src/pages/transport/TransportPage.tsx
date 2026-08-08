import { useState } from "react";
import VehiclesPage from "./VehiclesPage";
import DriversPage from "./DriversPage";
import RoutesPage from "./RoutesPage";
import AllocationsAndTripsPage from "./AllocationsAndTripsPage";

const TABS = [
  { key: "routes", label: "Routes" },
  { key: "vehicles", label: "Vehicles" },
  { key: "drivers", label: "Drivers" },
  { key: "allocations", label: "Allocations & Trips" },
] as const;

type TabKey = (typeof TABS)[number]["key"];

export default function TransportPage() {
  const [tab, setTab] = useState<TabKey>("routes");

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

      {tab === "routes" && <RoutesPage />}
      {tab === "vehicles" && <VehiclesPage />}
      {tab === "drivers" && <DriversPage />}
      {tab === "allocations" && <AllocationsAndTripsPage />}
    </div>
  );
}
