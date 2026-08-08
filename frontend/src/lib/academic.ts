import { useEffect, useState } from "react";
import { api, apiErrorMessage } from "./api";

export interface AcademicClass {
  class_id: number;
  class_name: string;
  sequence_order: number;
}

export interface Section {
  section_id: number;
  class_id: number;
  section_name: string;
  capacity: number;
}

export function useClasses() {
  const [classes, setClasses] = useState<AcademicClass[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api
      .get<{ data: AcademicClass[] }>("/academic/classes")
      .then((response) => setClasses(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }, []);

  return { classes, isLoading, error };
}

export interface AcademicSession {
  academic_session_id: number;
  session_name: string;
  start_date: string;
  end_date: string;
  status: "ACTIVE" | "CLOSED" | "UPCOMING";
}

export function useAcademicSessions() {
  const [sessions, setSessions] = useState<AcademicSession[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api
      .get<{ data: AcademicSession[] }>("/academic/sessions")
      .then((response) => setSessions(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }, []);

  return { sessions, isLoading, error };
}

export function useSections(classId: number | null) {
  const [sections, setSections] = useState<Section[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (classId === null) {
      setSections([]);
      return;
    }
    setIsLoading(true);
    api
      .get<{ data: Section[] }>("/academic/sections", { params: { class_id: classId } })
      .then((response) => setSections(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }, [classId]);

  return { sections, isLoading, error };
}
