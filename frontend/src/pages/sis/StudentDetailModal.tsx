import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { useSections } from "../../lib/academic";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";
import type { Student } from "./StudentsPage";

interface Guardian {
  guardian_id: number;
  full_name: string;
  relationship: string;
  mobile_number: string;
  email: string | null;
}

interface GuardianLink {
  student_id: number;
  guardian_id: number;
  is_primary_contact: boolean;
}

const STATUS_OPTIONS: Student["status"][] = ["DRAFT", "ACTIVE", "PROMOTED", "EXITED", "ARCHIVED"];

export default function StudentDetailModal({
  student,
  classId,
  onClose,
  onSaved,
}: {
  student: Student;
  classId: number;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [fullName, setFullName] = useState(student.full_name);
  const [dob, setDob] = useState(student.dob);
  const [category, setCategory] = useState(student.category);
  const [aadhaarNumber, setAadhaarNumber] = useState(student.aadhaar_number ?? "");
  const [medicalInfo, setMedicalInfo] = useState(student.medical_info ?? "");
  const [error, setError] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  const [newSectionId, setNewSectionId] = useState<number | null>(null);
  const { sections } = useSections(classId);

  const [guardians, setGuardians] = useState<(Guardian & { isPrimaryContact: boolean })[]>([]);
  const [guardiansError, setGuardiansError] = useState<string | null>(null);

  const [photoFile, setPhotoFile] = useState<File | null>(null);
  const [isUploadingPhoto, setIsUploadingPhoto] = useState(false);
  const [photoMessage, setPhotoMessage] = useState<string | null>(null);

  const [idCardMessage, setIdCardMessage] = useState<string | null>(null);
  const [isGeneratingIdCard, setIsGeneratingIdCard] = useState(false);

  useEffect(() => {
    api
      .get<{ data: GuardianLink[] }>(`/sis/student-guardian-links/by-student/${student.student_id}`)
      .then(async (linksResponse) => {
        const links = linksResponse.data.data;
        const guardianDetails = await Promise.all(
          links.map((link) =>
            api
              .get<{ data: Guardian }>(`/sis/guardians/${link.guardian_id}`)
              .then((r) => ({ ...r.data.data, isPrimaryContact: link.is_primary_contact })),
          ),
        );
        setGuardians(guardianDetails);
      })
      .catch((err) => setGuardiansError(apiErrorMessage(err)));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [student.student_id]);

  async function handleSave(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSaving(true);
    try {
      await api.patch(`/sis/students/${student.student_id}`, {
        full_name: fullName,
        dob,
        category,
        aadhaar_number: aadhaarNumber || null,
        medical_info: medicalInfo || null,
      });
      onSaved();
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsSaving(false);
    }
  }

  async function handleStatusChange(status: Student["status"]) {
    try {
      await api.post(`/sis/students/${student.student_id}/status`, { status });
      onSaved();
    } catch (err) {
      alert(apiErrorMessage(err));
    }
  }

  async function handleSectionTransfer() {
    if (newSectionId === null) return;
    try {
      await api.post(`/sis/students/${student.student_id}/section-transfer`, { new_section_id: newSectionId });
      onSaved();
    } catch (err) {
      alert(apiErrorMessage(err));
    }
  }

  async function handlePhotoUpload() {
    if (!photoFile) return;
    setIsUploadingPhoto(true);
    setPhotoMessage(null);
    try {
      const extension = photoFile.name.split(".").pop()?.toLowerCase() ?? "jpg";
      const base64 = await fileToBase64(photoFile);
      await api.post(`/sis/students/${student.student_id}/photo`, {
        image_base64: base64,
        extension,
      });
      setPhotoMessage("Photo uploaded.");
      onSaved();
    } catch (err) {
      setPhotoMessage(apiErrorMessage(err));
    } finally {
      setIsUploadingPhoto(false);
    }
  }

  async function handleGenerateIdCard() {
    setIsGeneratingIdCard(true);
    setIdCardMessage(null);
    try {
      const response = await api.post<{ data: { document_id: number } }>(`/sis/students/${student.student_id}/id-card`);
      const documentId = response.data.data.document_id;

      const pdfResponse = await api.get(`/administration/documents/${documentId}/download`, {
        responseType: "blob",
      });
      const blobUrl = URL.createObjectURL(pdfResponse.data as Blob);
      window.open(blobUrl, "_blank");

      setIdCardMessage("ID card generated.");
    } catch (err) {
      setIdCardMessage(apiErrorMessage(err));
    } finally {
      setIsGeneratingIdCard(false);
    }
  }

  return (
    <Modal title={`Student — ${student.admission_number}`} onClose={onClose}>
      <div className="max-h-[70vh] space-y-6 overflow-y-auto pr-1">
        <form onSubmit={handleSave} className="space-y-3">
          <div>
            <label className={labelClass}>Full name</label>
            <input required value={fullName} onChange={(e) => setFullName(e.target.value)} className={inputClass} />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className={labelClass}>Date of birth</label>
              <input required type="date" value={dob} onChange={(e) => setDob(e.target.value)} className={inputClass} />
            </div>
            <div>
              <label className={labelClass}>Category</label>
              <select
                value={category}
                onChange={(e) => setCategory(e.target.value as Student["category"])}
                className={inputClass}
              >
                <option value="GENERAL">General</option>
                <option value="RTE">RTE</option>
              </select>
            </div>
          </div>
          <div>
            <label className={labelClass}>Aadhaar number</label>
            <input value={aadhaarNumber} onChange={(e) => setAadhaarNumber(e.target.value)} className={inputClass} />
          </div>
          <div>
            <label className={labelClass}>Medical info</label>
            <input value={medicalInfo} onChange={(e) => setMedicalInfo(e.target.value)} className={inputClass} />
          </div>
          {error && (
            <p role="alert" className="text-sm text-red-600 dark:text-red-400">
              {error}
            </p>
          )}
          <div className="flex justify-end gap-2">
            <button type="button" onClick={onClose} className={secondaryButtonClass}>
              Close
            </button>
            <button type="submit" disabled={isSaving} className={primaryButtonClass}>
              {isSaving ? "Saving…" : "Save"}
            </button>
          </div>
        </form>

        <div className="border-t border-slate-200 pt-4 dark:border-slate-800">
          <p className={labelClass}>Status: {student.status}</p>
          <div className="flex flex-wrap gap-2">
            {STATUS_OPTIONS.filter((s) => s !== student.status).map((s) => (
              <button
                key={s}
                type="button"
                onClick={() => handleStatusChange(s)}
                className="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-900"
              >
                → {s}
              </button>
            ))}
          </div>
        </div>

        <div className="border-t border-slate-200 pt-4 dark:border-slate-800">
          <p className={labelClass}>Transfer section</p>
          <div className="flex gap-2">
            <select
              value={newSectionId ?? ""}
              onChange={(e) => setNewSectionId(e.target.value ? Number(e.target.value) : null)}
              className={inputClass}
            >
              <option value="">Select section</option>
              {sections.map((section) => (
                <option key={section.section_id} value={section.section_id}>
                  {section.section_name}
                </option>
              ))}
            </select>
            <button type="button" onClick={handleSectionTransfer} className={secondaryButtonClass}>
              Transfer
            </button>
          </div>
        </div>

        <div className="border-t border-slate-200 pt-4 dark:border-slate-800">
          <p className={labelClass}>Guardians</p>
          {guardiansError && <p className="text-sm text-red-600 dark:text-red-400">{guardiansError}</p>}
          {guardians.length === 0 && !guardiansError && (
            <p className="text-sm text-slate-400">No linked guardians.</p>
          )}
          <ul className="space-y-1 text-sm text-slate-600 dark:text-slate-400">
            {guardians.map((g) => (
              <li key={g.guardian_id}>
                {g.full_name} ({g.relationship}) — {g.mobile_number}
                {g.isPrimaryContact && (
                  <span className="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs dark:bg-slate-900">
                    Primary
                  </span>
                )}
              </li>
            ))}
          </ul>
        </div>

        <div className="border-t border-slate-200 pt-4 dark:border-slate-800">
          <p className={labelClass}>Photo</p>
          <div className="flex items-center gap-2">
            <input
              type="file"
              accept="image/jpeg,image/png"
              onChange={(e) => setPhotoFile(e.target.files?.[0] ?? null)}
              className="text-sm"
            />
            <button
              type="button"
              onClick={handlePhotoUpload}
              disabled={!photoFile || isUploadingPhoto}
              className={secondaryButtonClass}
            >
              {isUploadingPhoto ? "Uploading…" : "Upload"}
            </button>
          </div>
          {photoMessage && <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{photoMessage}</p>}
        </div>

        <div className="border-t border-slate-200 pt-4 dark:border-slate-800">
          <button type="button" onClick={handleGenerateIdCard} disabled={isGeneratingIdCard} className={secondaryButtonClass}>
            {isGeneratingIdCard ? "Generating…" : "Generate ID Card PDF"}
          </button>
          {idCardMessage && <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{idCardMessage}</p>}
        </div>
      </div>
    </Modal>
  );
}

function fileToBase64(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
      const result = reader.result as string;
      resolve(result.split(",")[1] ?? "");
    };
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}
