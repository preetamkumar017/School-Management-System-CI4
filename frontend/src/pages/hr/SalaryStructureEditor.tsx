import { inputClass, labelClass, secondaryButtonClass } from "../../components/ui/form";

export type SalaryComponents = { name: string; amount: string }[];

const COMMON_COMPONENTS = ["Basic", "HRA", "DA", "Special Allowance", "Conveyance"];

export function salaryComponentsToJson(components: SalaryComponents): Record<string, number> {
  const json: Record<string, number> = {};
  for (const c of components) {
    if (c.name.trim() && c.amount) json[c.name.trim()] = Number(c.amount);
  }
  return json;
}

export function jsonToSalaryComponents(json: Record<string, number>): SalaryComponents {
  const entries = Object.entries(json).map(([name, amount]) => ({ name, amount: String(amount) }));
  if (entries.length > 0) return entries;
  // Default pre-filled Indian school salary components
  return [
    { name: "Basic", amount: "" },
    { name: "HRA", amount: "" },
    { name: "DA", amount: "" },
  ];
}

export default function SalaryStructureEditor({
  components,
  onChange,
}: {
  components: SalaryComponents;
  onChange: (components: SalaryComponents) => void;
}) {
  const total = components.reduce((sum, c) => sum + (Number(c.amount) || 0), 0);

  function updateAt(index: number, field: "name" | "amount", value: string) {
    const next = components.slice();
    next[index] = { ...next[index], [field]: value };
    onChange(next);
  }

  function removeAt(index: number) {
    onChange(components.filter((_, i) => i !== index));
  }

  function addComponent(name = "") {
    onChange([...components, { name, amount: "" }]);
  }

  return (
    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3 dark:border-slate-800 dark:bg-slate-900/50">
      <div className="flex items-center justify-between">
        <label className={`${labelClass} font-semibold text-slate-800 dark:text-slate-200`}>
          Monthly Salary Structure & Breakdown
        </label>
        <span className="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
          Total Gross: ₹{total.toLocaleString("en-IN")} / month
        </span>
      </div>

      <div className="space-y-2">
        {components.map((c, i) => (
          <div key={i} className="flex items-center gap-3">
            <input
              placeholder="Component (e.g. Basic, HRA, DA)"
              value={c.name}
              onChange={(e) => updateAt(i, "name", e.target.value)}
              className={`${inputClass} flex-1 font-medium`}
            />
            <div className="relative flex items-center">
              <span className="absolute left-3 text-sm text-slate-400">₹</span>
              <input
                type="number"
                min={0}
                placeholder="0"
                value={c.amount}
                onChange={(e) => updateAt(i, "amount", e.target.value)}
                className={`${inputClass} w-36 pl-7 font-semibold`}
              />
            </div>
            <button
              type="button"
              onClick={() => removeAt(i)}
              disabled={components.length <= 1}
              className="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-30 dark:hover:bg-red-950 dark:hover:text-red-400"
              title="Remove Component"
            >
              ✕
            </button>
          </div>
        ))}
      </div>

      <div className="mt-3 flex flex-wrap items-center gap-1.5 pt-1">
        <span className="text-xs text-slate-500 mr-1">Add Component:</span>
        {COMMON_COMPONENTS.filter((name) => !components.some((c) => c.name === name)).map((name) => (
          <button
            key={name}
            type="button"
            onClick={() => addComponent(name)}
            className={`${secondaryButtonClass} px-2.5 py-1 text-xs font-medium`}
          >
            + {name}
          </button>
        ))}
        <button
          type="button"
          onClick={() => addComponent()}
          className={`${secondaryButtonClass} px-2.5 py-1 text-xs font-medium`}
        >
          + Custom
        </button>
      </div>
    </div>
  );
}
