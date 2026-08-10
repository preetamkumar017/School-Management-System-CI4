import { useEffect, useState, type FormEvent, type ChangeEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { inputClass, labelClass, primaryButtonClass } from "../../components/ui/form";
import type { Employee } from "../hr/EmployeesPage";

interface SchoolProfileData {
  school_id: number | null;
  school_name: string;
  short_name: string;
  school_code: string;
  address_line1: string;
  address_line2: string;
  city: string;
  state: string;
  district: string;
  block: string;
  pin_code: string;
  country: string;
  school_type: string;
  school_levels_offered: string[];
  management_type: string;
  medium_of_instruction: string;
  residential_status: string;
  board_affiliation_ref: string;
  board_affiliation_number: string;
  recognition_number: string;
  affiliation_validity_start: string;
  affiliation_validity_end: string;
  udise_code: string;
  state_board_code: string;
  principal_employee_id: number | null;
  principal_name: string;
  principal_email: string;
  principal_phone: string;
  school_email: string;
  school_phone: string;
  emergency_contact: string;
  primary_logo_path: string | null;
  document_logo_path: string | null;
  document_header_text: string;
  document_footer_text: string;
}

const SCHOOL_TYPES = ["Boys", "Girls", "Co-educational"];
const SCHOOL_LEVELS = ["Pre-Primary", "Primary", "Upper Primary", "Secondary", "Senior Secondary"];
const MANAGEMENT_TYPES = ["Government", "Government Aided", "Private Unaided", "Other"];
const MEDIUMS = ["English", "Hindi", "Regional Language", "Other"];
const RESIDENTIAL_STATUSES = ["Day School", "Residential", "Day-cum-Residential"];
const BOARDS = ["CBSE", "ICSE", "State Board", "International Board", "Other"];

export default function SchoolProfilePage() {
  const [profile, setProfile] = useState<SchoolProfileData>({
    school_id: null,
    school_name: "",
    short_name: "",
    school_code: "",
    address_line1: "",
    address_line2: "",
    city: "",
    state: "",
    district: "",
    block: "",
    pin_code: "",
    country: "India",
    school_type: "Co-educational",
    school_levels_offered: [],
    management_type: "Private Unaided",
    medium_of_instruction: "English",
    residential_status: "Day School",
    board_affiliation_ref: "CBSE",
    board_affiliation_number: "",
    recognition_number: "",
    affiliation_validity_start: "",
    affiliation_validity_end: "",
    udise_code: "",
    state_board_code: "",
    principal_employee_id: null,
    principal_name: "",
    principal_email: "",
    principal_phone: "",
    school_email: "",
    school_phone: "",
    emergency_contact: "",
    primary_logo_path: null,
    document_logo_path: null,
    document_header_text: "",
    document_footer_text: "",
  });

  const [employees, setEmployees] = useState<Employee[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Image base64 states
  const [primaryLogo, setPrimaryLogo] = useState<{ base64: string; ext: string } | null>(null);
  const [documentLogo, setDocumentLogo] = useState<{ base64: string; ext: string } | null>(null);

  interface GeoItem {
    state_id?: number;
    district_id?: number;
    block_id?: number;
    name: string;
  }

  const [statesList, setStatesList] = useState<GeoItem[]>([]);
  const [districtsList, setDistrictsList] = useState<GeoItem[]>([]);
  const [blocksList, setBlocksList] = useState<GeoItem[]>([]);

  const loadDistricts = async (stateName: string, availableStates: GeoItem[]) => {
    const matchedState = availableStates.find((s) => s.name === stateName);
    if (matchedState && matchedState.state_id) {
      try {
        const res = await api.get(`/administration/states/${matchedState.state_id}/districts`);
        setDistrictsList(res.data.data || []);
        return res.data.data || [];
      } catch (err) {
        console.error("Failed to load districts", err);
      }
    } else {
      setDistrictsList([]);
    }
    return [];
  };

  const loadBlocks = async (districtName: string, availableDistricts: GeoItem[]) => {
    const matchedDistrict = availableDistricts.find((d) => d.name === districtName);
    if (matchedDistrict && matchedDistrict.district_id) {
      try {
        const res = await api.get(`/administration/districts/${matchedDistrict.district_id}/blocks`);
        setBlocksList(res.data.data || []);
      } catch (err) {
        console.error("Failed to load blocks", err);
      }
    } else {
      setBlocksList([]);
    }
  };

  const handleStateChange = async (stateName: string) => {
    setProfile((prev) => ({ ...prev, state: stateName, district: "", block: "" }));
    setBlocksList([]);
    await loadDistricts(stateName, statesList);
  };

  const handleDistrictChange = async (districtName: string) => {
    setProfile((prev) => ({ ...prev, district: districtName, block: "" }));
    await loadBlocks(districtName, districtsList);
  };

  useEffect(() => {
    async function fetchData() {
      try {
        const [profileRes, empRes, statesRes] = await Promise.all([
          api.get("/administration/school-profile"),
          api.get("/hr-payroll/employees").catch(() => ({ data: { data: [] } })),
          api.get("/administration/states").catch(() => ({ data: { data: [] } })),
        ]);

        const loadedStates = statesRes.data?.data || [];
        setStatesList(loadedStates);

        if (profileRes.data && profileRes.data.data && profileRes.data.data.school_id) {
          const p = profileRes.data.data;
          setProfile({
            school_id: p.school_id,
            school_name: p.school_name || "",
            short_name: p.short_name || "",
            school_code: p.school_code || "",
            address_line1: p.address_line1 || "",
            address_line2: p.address_line2 || "",
            city: p.city || "",
            state: p.state || "",
            district: p.district || "",
            block: p.block || "",
            pin_code: p.pin_code || "",
            country: p.country || "India",
            school_type: p.school_type || "Co-educational",
            school_levels_offered: p.school_levels_offered || [],
            management_type: p.management_type || "Private Unaided",
            medium_of_instruction: p.medium_of_instruction || "English",
            residential_status: p.residential_status || "Day School",
            board_affiliation_ref: p.board_affiliation_ref || "CBSE",
            board_affiliation_number: p.board_affiliation_number || "",
            recognition_number: p.recognition_number || "",
            affiliation_validity_start: p.affiliation_validity_start || "",
            affiliation_validity_end: p.affiliation_validity_end || "",
            udise_code: p.udise_code || "",
            state_board_code: p.state_board_code || "",
            principal_employee_id: p.principal_employee_id || null,
            principal_name: p.principal_name || "",
            principal_email: p.principal_email || "",
            principal_phone: p.principal_phone || "",
            school_email: p.school_email || "",
            school_phone: p.school_phone || "",
            emergency_contact: p.emergency_contact || "",
            primary_logo_path: p.primary_logo_path || null,
            document_logo_path: p.document_logo_path || null,
            document_header_text: p.document_header_text || "",
            document_footer_text: p.document_footer_text || "",
          });

          if (p.state) {
            const loadedDistricts = await loadDistricts(p.state, loadedStates);
            if (p.district) {
              await loadBlocks(p.district, loadedDistricts);
            }
          }
        }
        if (empRes.data && empRes.data.data) {
          setEmployees(empRes.data.data);
        }
      } catch (err) {
        console.error("Failed to load school profile or employees list.", err);
      } finally {
        setLoading(false);
      }
    }

    fetchData();
  }, []);

  const handleLevelChange = (level: string, checked: boolean) => {
    setProfile((prev) => {
      const levels = checked
        ? [...prev.school_levels_offered, level]
        : prev.school_levels_offered.filter((l) => l !== level);
      return { ...prev, school_levels_offered: levels };
    });
  };

  const handleFileChange = (e: ChangeEvent<HTMLInputElement>, type: "primary" | "document") => {
    const file = e.target.files?.[0];
    if (!file) return;

    // Validate size (2MB)
    if (file.size > 2 * 1024 * 1024) {
      alert("File size exceeds 2MB limit.");
      return;
    }

    const reader = new FileReader();
    reader.onloadend = () => {
      const result = reader.result as string;
      const base64 = result.substring(result.indexOf(",") + 1);
      const ext = file.name.substring(file.name.lastIndexOf(".") + 1).toLowerCase();
      if (type === "primary") {
        setPrimaryLogo({ base64, ext });
      } else {
        setDocumentLogo({ base64, ext });
      }
    };
    reader.readAsDataURL(file);
  };

  const handlePrincipalChange = (e: ChangeEvent<HTMLSelectElement>) => {
    const val = e.target.value;
    if (val === "") {
      setProfile((prev) => ({ ...prev, principal_employee_id: null }));
    } else {
      const id = parseInt(val, 10);
      const emp = employees.find((x) => x.employee_id === id);
      setProfile((prev) => ({
        ...prev,
        principal_employee_id: id,
        principal_name: emp ? emp.full_name : prev.principal_name,
        principal_email: emp ? (emp as any).email || prev.principal_email : prev.principal_email,
        principal_phone: emp ? emp.emergency_contact_phone || prev.principal_phone : prev.principal_phone,
      }));
    }
  };

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setSuccessMsg(null);
    setErrors({});

    const payload = {
      school_name: profile.school_name,
      short_name: profile.short_name,
      school_code: profile.school_code || null,
      address_line1: profile.address_line1,
      address_line2: profile.address_line2,
      city: profile.city,
      state: profile.state,
      district: profile.district || null,
      block: profile.block || null,
      pin_code: profile.pin_code,
      country: profile.country,
      school_type: profile.school_type,
      school_levels_offered: profile.school_levels_offered,
      management_type: profile.management_type,
      medium_of_instruction: profile.medium_of_instruction,
      residential_status: profile.residential_status,
      board_affiliation_ref: profile.board_affiliation_ref,
      board_affiliation_number: profile.board_affiliation_number || null,
      recognition_number: profile.recognition_number || null,
      affiliation_validity_start: profile.affiliation_validity_start || null,
      affiliation_validity_end: profile.affiliation_validity_end || null,
      udise_code: profile.udise_code || null,
      state_board_code: profile.state_board_code || null,
      principal_employee_id: profile.principal_employee_id,
      principal_name: profile.principal_name || null,
      principal_email: profile.principal_email || null,
      principal_phone: profile.principal_phone || null,
      school_email: profile.school_email,
      school_phone: profile.school_phone,
      emergency_contact: profile.emergency_contact || null,
      primary_logo_base64: primaryLogo ? primaryLogo.base64 : null,
      primary_logo_extension: primaryLogo ? primaryLogo.ext : null,
      document_logo_base64: documentLogo ? documentLogo.base64 : null,
      document_logo_extension: documentLogo ? documentLogo.ext : null,
      document_header_text: profile.document_header_text || null,
      document_footer_text: profile.document_footer_text || null,
    };

    try {
      const res = await api.post("/administration/school-profile", payload);
      setSuccessMsg("School profile saved successfully!");
      if (res.data && res.data.data) {
        const p = res.data.data;
        setProfile((prev) => ({
          ...prev,
          school_id: p.school_id,
          primary_logo_path: p.primary_logo_path || prev.primary_logo_path,
          document_logo_path: p.document_logo_path || prev.document_logo_path,
        }));
        setPrimaryLogo(null);
        setDocumentLogo(null);
      }
    } catch (err: any) {
      if (err.response?.status === 422 && err.response?.data?.fields) {
        setErrors(err.response.data.fields);
      } else {
        alert(apiErrorMessage(err));
      }
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <div className="p-8 text-center text-slate-500">Loading School Profile Setup...</div>;
  }

  return (
    <form onSubmit={handleSubmit} className="max-w-5xl space-y-8 pb-12">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-black tracking-tight text-slate-900 dark:text-white">School Profile & Branding</h1>
          <p className="text-sm text-slate-500">Configure institutional identity, address hierarchy, classifications, boards and document templates.</p>
        </div>
        <button
          type="submit"
          disabled={saving}
          className={`${primaryButtonClass} px-6 py-2.5 shadow-md`}
        >
          {saving ? "Saving..." : "Save Profile Configuration"}
        </button>
      </div>

      {successMsg && (
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800 dark:bg-emerald-950/20 dark:border-emerald-800/30 dark:text-emerald-400">
          ✨ {successMsg}
        </div>
      )}

      {Object.keys(errors).length > 0 && (
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950/20 dark:border-red-800/30 dark:text-red-400">
          <p className="font-bold mb-1">Please correct the following validation errors:</p>
          <ul className="list-disc list-inside space-y-0.5">
            {Object.entries(errors).map(([key, msg]) => (
              <li key={key}>{msg}</li>
            ))}
          </ul>
        </div>
      )}

      {/* Grid of Sections */}
      <div className="grid grid-cols-1 gap-8 md:grid-cols-2">
        
        {/* SECTION 1: IDENTITY */}
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
          <h3 className="mb-4 text-lg font-bold text-slate-900 dark:text-white">1. School Identity</h3>
          <div className="space-y-4">
            <div>
              <label className={labelClass}>School Name *</label>
              <input
                type="text"
                className={inputClass}
                value={profile.school_name}
                onChange={(e) => setProfile({ ...profile, school_name: e.target.value })}
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className={labelClass}>Abbreviation / Short Name *</label>
                <input
                  type="text"
                  className={inputClass}
                  value={profile.short_name}
                  onChange={(e) => setProfile({ ...profile, short_name: e.target.value })}
                />
              </div>
              <div>
                <label className={labelClass}>Internal School Code</label>
                <input
                  type="text"
                  className={inputClass}
                  value={profile.school_code}
                  onChange={(e) => setProfile({ ...profile, school_code: e.target.value })}
                />
              </div>
            </div>
          </div>
        </div>

        {/* SECTION 2: ADDRESS */}
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
          <h3 className="mb-4 text-lg font-bold text-slate-900 dark:text-white">2. Address & Location</h3>
          <div className="space-y-4">
            <div>
              <label className={labelClass}>Address Line 1 *</label>
              <input
                type="text"
                className={inputClass}
                value={profile.address_line1}
                onChange={(e) => setProfile({ ...profile, address_line1: e.target.value })}
              />
            </div>
            <div>
              <label className={labelClass}>Address Line 2 *</label>
              <input
                type="text"
                className={inputClass}
                value={profile.address_line2}
                onChange={(e) => setProfile({ ...profile, address_line2: e.target.value })}
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className={labelClass}>City / Town *</label>
                <input
                  type="text"
                  className={inputClass}
                  value={profile.city}
                  onChange={(e) => setProfile({ ...profile, city: e.target.value })}
                />
              </div>
              <div>
                <label className={labelClass}>State *</label>
                <select
                  className={inputClass}
                  value={profile.state}
                  onChange={(e) => handleStateChange(e.target.value)}
                >
                  <option value="">-- Select State --</option>
                  {statesList.map((s) => (
                    <option key={s.state_id} value={s.name}>{s.name}</option>
                  ))}
                </select>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className={labelClass}>District (Optional)</label>
                <select
                  className={inputClass}
                  value={profile.district}
                  onChange={(e) => handleDistrictChange(e.target.value)}
                  disabled={!profile.state}
                >
                  <option value="">-- Select District --</option>
                  {districtsList.map((d) => (
                    <option key={d.district_id} value={d.name}>{d.name}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className={labelClass}>Block / Tehsil (Optional)</label>
                <select
                  className={inputClass}
                  value={profile.block}
                  onChange={(e) => setProfile({ ...profile, block: e.target.value })}
                  disabled={!profile.district}
                >
                  <option value="">-- Select Block/Tehsil --</option>
                  {blocksList.map((b) => (
                    <option key={b.block_id} value={b.name}>{b.name}</option>
                  ))}
                </select>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className={labelClass}>PIN Code *</label>
                <input
                  type="text"
                  className={inputClass}
                  value={profile.pin_code}
                  onChange={(e) => setProfile({ ...profile, pin_code: e.target.value })}
                />
              </div>
              <div>
                <label className={labelClass}>Country *</label>
                <input
                  type="text"
                  className={inputClass}
                  value={profile.country}
                  onChange={(e) => setProfile({ ...profile, country: e.target.value })}
                />
              </div>
            </div>
          </div>
        </div>

        {/* SECTION 3: CLASSIFICATION */}
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 md:col-span-2">
          <h3 className="mb-4 text-lg font-bold text-slate-900 dark:text-white">3. School Classification</h3>
          <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
            <div>
              <label className={labelClass}>School Type *</label>
              <select
                className={inputClass}
                value={profile.school_type}
                onChange={(e) => setProfile({ ...profile, school_type: e.target.value })}
              >
                {SCHOOL_TYPES.map((t) => (
                  <option key={t} value={t}>{t}</option>
                ))}
              </select>
            </div>
            <div>
              <label className={labelClass}>Management Type *</label>
              <select
                className={inputClass}
                value={profile.management_type}
                onChange={(e) => setProfile({ ...profile, management_type: e.target.value })}
              >
                {MANAGEMENT_TYPES.map((t) => (
                  <option key={t} value={t}>{t}</option>
                ))}
              </select>
            </div>
            <div>
              <label className={labelClass}>Medium of Instruction *</label>
              <select
                className={inputClass}
                value={profile.medium_of_instruction}
                onChange={(e) => setProfile({ ...profile, medium_of_instruction: e.target.value })}
              >
                {MEDIUMS.map((t) => (
                  <option key={t} value={t}>{t}</option>
                ))}
              </select>
            </div>
            <div>
              <label className={labelClass}>Residential Status *</label>
              <select
                className={inputClass}
                value={profile.residential_status}
                onChange={(e) => setProfile({ ...profile, residential_status: e.target.value })}
              >
                {RESIDENTIAL_STATUSES.map((t) => (
                  <option key={t} value={t}>{t}</option>
                ))}
              </select>
            </div>
            <div className="md:col-span-2">
              <label className={labelClass}>School Levels Offered *</label>
              <div className="mt-2 flex flex-wrap gap-4">
                {SCHOOL_LEVELS.map((l) => (
                  <label key={l} className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input
                      type="checkbox"
                      checked={profile.school_levels_offered.includes(l)}
                      onChange={(e) => handleLevelChange(l, e.target.checked)}
                      className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    {l}
                  </label>
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* SECTION 4: BOARD & RECOGNITION */}
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
          <h3 className="mb-4 text-lg font-bold text-slate-900 dark:text-white">4. Board & Recognition</h3>
          <div className="space-y-4">
            <div>
              <label className={labelClass}>Board Affiliation Reference *</label>
              <select
                className={inputClass}
                value={profile.board_affiliation_ref}
                onChange={(e) => setProfile({ ...profile, board_affiliation_ref: e.target.value })}
              >
                {BOARDS.map((b) => (
                  <option key={b} value={b}>{b}</option>
                ))}
              </select>
            </div>
            <div>
              <label className={labelClass}>Board Affiliation Number</label>
              <input
                type="text"
                className={inputClass}
                value={profile.board_affiliation_number}
                onChange={(e) => setProfile({ ...profile, board_affiliation_number: e.target.value })}
              />
            </div>
            <div>
              <label className={labelClass}>Recognition / Registration Number</label>
              <input
                type="text"
                className={inputClass}
                value={profile.recognition_number}
                onChange={(e) => setProfile({ ...profile, recognition_number: e.target.value })}
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className={labelClass}>Affiliation Validity Start</label>
                <input
                  type="date"
                  className={inputClass}
                  value={profile.affiliation_validity_start}
                  onChange={(e) => setProfile({ ...profile, affiliation_validity_start: e.target.value })}
                />
              </div>
              <div>
                <label className={labelClass}>Affiliation Validity End</label>
                <input
                  type="date"
                  className={inputClass}
                  value={profile.affiliation_validity_end}
                  onChange={(e) => setProfile({ ...profile, affiliation_validity_end: e.target.value })}
                />
              </div>
            </div>
          </div>
        </div>

        {/* SECTION 5: INSTITUTIONAL IDENTIFIERS */}
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
          <h3 className="mb-4 text-lg font-bold text-slate-900 dark:text-white">5. Institutional Identifiers</h3>
          <div className="space-y-4">
            <div>
              <label className={labelClass}>UDISE+ School Code</label>
              <input
                type="text"
                className={inputClass}
                placeholder="11-digit government code"
                value={profile.udise_code}
                onChange={(e) => setProfile({ ...profile, udise_code: e.target.value })}
              />
            </div>
            <div>
              <label className={labelClass}>State Board School Code</label>
              <input
                type="text"
                className={inputClass}
                value={profile.state_board_code}
                onChange={(e) => setProfile({ ...profile, state_board_code: e.target.value })}
              />
            </div>
          </div>
        </div>

        {/* SECTION 6: CONTACT & PRINCIPAL */}
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 md:col-span-2">
          <h3 className="mb-4 text-lg font-bold text-slate-900 dark:text-white">6. Official Contact & Principal</h3>
          <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
              <label className={labelClass}>School Official Email *</label>
              <input
                type="email"
                className={inputClass}
                value={profile.school_email}
                onChange={(e) => setProfile({ ...profile, school_email: e.target.value })}
              />
            </div>
            <div>
              <label className={labelClass}>School Official Phone *</label>
              <input
                type="text"
                className={inputClass}
                value={profile.school_phone}
                onChange={(e) => setProfile({ ...profile, school_phone: e.target.value })}
              />
            </div>
            <div>
              <label className={labelClass}>Emergency Contact Number</label>
              <input
                type="text"
                className={inputClass}
                value={profile.emergency_contact}
                onChange={(e) => setProfile({ ...profile, emergency_contact: e.target.value })}
              />
            </div>
            
            <div className="border-t border-slate-100 pt-4 dark:border-slate-800 md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="md:col-span-2">
                <h4 className="text-sm font-bold text-slate-800 dark:text-slate-200">Principal / Head of Institution Integration</h4>
                <p className="text-xs text-slate-400 mb-2">Link with HR payroll staff member or enter manual fallback below.</p>
              </div>
              <div>
                <label className={labelClass}>Select Staff Profile Reference</label>
                <select
                  className={inputClass}
                  value={profile.principal_employee_id || ""}
                  onChange={handlePrincipalChange}
                >
                  <option value="">-- No Integration (Manual Fallback) --</option>
                  {employees.map((e) => (
                    <option key={e.employee_id} value={e.employee_id}>
                      {e.full_name} ({e.employee_code})
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className={labelClass}>Principal Name</label>
                <input
                  type="text"
                  className={inputClass}
                  value={profile.principal_name}
                  onChange={(e) => setProfile({ ...profile, principal_name: e.target.value })}
                  disabled={profile.principal_employee_id !== null}
                />
              </div>
              <div>
                <label className={labelClass}>Principal Email</label>
                <input
                  type="email"
                  className={inputClass}
                  value={profile.principal_email}
                  onChange={(e) => setProfile({ ...profile, principal_email: e.target.value })}
                  disabled={profile.principal_employee_id !== null}
                />
              </div>
              <div>
                <label className={labelClass}>Principal Phone</label>
                <input
                  type="text"
                  className={inputClass}
                  value={profile.principal_phone}
                  onChange={(e) => setProfile({ ...profile, principal_phone: e.target.value })}
                  disabled={profile.principal_employee_id !== null}
                />
              </div>
            </div>
          </div>
        </div>

        {/* SECTION 7: BRANDING & TEMPLATES */}
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 md:col-span-2">
          <h3 className="mb-4 text-lg font-bold text-slate-900 dark:text-white">7. Branding & Layout Configuration</h3>
          <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
            
            {/* Primary Logo */}
            <div className="flex flex-col gap-2 rounded-xl border border-slate-100 p-4 dark:border-slate-800">
              <label className={labelClass}>Primary School Logo</label>
              <input
                type="file"
                accept="image/png, image/jpeg, image/jpg"
                onChange={(e) => handleFileChange(e, "primary")}
                className="text-xs"
              />
              <div className="mt-4 flex items-center gap-4">
                {profile.primary_logo_path && (
                  <div>
                    <span className="text-[10px] text-slate-400 block mb-1">Current Active Logo</span>
                    <img
                      src={`${api.defaults.baseURL?.replace("/api/v1", "/writable/uploads")}/${profile.primary_logo_path}`}
                      alt="Primary Logo"
                      className="h-16 w-16 object-contain rounded border border-slate-200"
                    />
                  </div>
                )}
                {primaryLogo && (
                  <div>
                    <span className="text-[10px] text-indigo-400 block mb-1">New Upload Preview</span>
                    <img
                      src={`data:image/${primaryLogo.ext};base64,${primaryLogo.base64}`}
                      alt="New Primary Logo Preview"
                      className="h-16 w-16 object-contain rounded border-2 border-dashed border-indigo-400"
                    />
                  </div>
                )}
              </div>
            </div>

            {/* Document Logo */}
            <div className="flex flex-col gap-2 rounded-xl border border-slate-100 p-4 dark:border-slate-800">
              <label className={labelClass}>Document-Optimized Logo</label>
              <input
                type="file"
                accept="image/png, image/jpeg, image/jpg"
                onChange={(e) => handleFileChange(e, "document")}
                className="text-xs"
              />
              <div className="mt-4 flex items-center gap-4">
                {profile.document_logo_path && (
                  <div>
                    <span className="text-[10px] text-slate-400 block mb-1">Current Active Logo</span>
                    <img
                      src={`${api.defaults.baseURL?.replace("/api/v1", "/writable/uploads")}/${profile.document_logo_path}`}
                      alt="Document Logo"
                      className="h-16 w-16 object-contain rounded border border-slate-200"
                    />
                  </div>
                )}
                {documentLogo && (
                  <div>
                    <span className="text-[10px] text-indigo-400 block mb-1">New Upload Preview</span>
                    <img
                      src={`data:image/${documentLogo.ext};base64,${documentLogo.base64}`}
                      alt="New Document Logo Preview"
                      className="h-16 w-16 object-contain rounded border-2 border-dashed border-indigo-400"
                    />
                  </div>
                )}
              </div>
            </div>

            <div className="md:col-span-2">
              <label className={labelClass}>Document Header Template Text</label>
              <textarea
                className={`${inputClass} h-20`}
                placeholder="Official text header printed at the top of fee receipts, report cards, etc."
                value={profile.document_header_text}
                onChange={(e) => setProfile({ ...profile, document_header_text: e.target.value })}
              />
            </div>

            <div className="md:col-span-2">
              <label className={labelClass}>Document Footer Template Text</label>
              <textarea
                className={`${inputClass} h-20`}
                placeholder="Official text footer printed at the bottom of official forms, receipts and reports."
                value={profile.document_footer_text}
                onChange={(e) => setProfile({ ...profile, document_footer_text: e.target.value })}
              />
            </div>

          </div>
        </div>

      </div>
    </form>
  );
}
