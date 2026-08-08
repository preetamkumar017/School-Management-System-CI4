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
  return entries.length > 0 ? entries : [{ name: "Basic", amount: "" }];
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
    <div>
      <label className={labelClass}>Salary structure (monthly components)</label>
      <div className="space-y-2">
        {components.map((c, i) => (
          <div key={i} className="flex gap-2">
            <input
              placeholder="Component (e.g. Basic)"
              value={c.name}
              onChange={(e) => updateAt(i, "name", e.target.value)}
              className={`${inputClass} flex-1`}
            />
            <input
              type="number"
              min={0}
              placeholder="Amount"
              value={c.amount}
              onChange={(e) => updateAt(i, "amount", e.target.value)}
              className={`${inputClass} w-32`}
            />
            <button
              type="button"
              onClick={() => removeAt(i)}
              disabled={components.length <= 1}
              className="px-2 text-slate-400 hover:text-red-600 disabled:opacity-30 dark:hover:text-red-400"
              aria-label="Remove component"
            >
              ✕
            </button>
          </div>
        ))}
      </div>

      <div className="mt-2 flex flex-wrap gap-1">
        {COMMON_COMPONENTS.filter((name) => !components.some((c) => c.name === name)).map((name) => (
          <button
            key={name}
            type="button"
            onClick={() => addComponent(name)}
            className={`${secondaryButtonClass} px-2 py-1 text-xs`}
          >
            + {name}
          </button>
        ))}
        <button type="button" onClick={() => addComponent()} className={`${secondaryButtonClass} px-2 py-1 text-xs`}>
          + Custom
        </button>
      </div>

      <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
        Total gross: <span className="font-medium text-slate-900 dark:text-slate-100">₹{total.toLocaleString()}</span>
      </p>
    </div>
  );
}
