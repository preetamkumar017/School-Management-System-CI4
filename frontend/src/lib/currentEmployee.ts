import { useEffect, useState } from "react";
import { api } from "./api";
import { useAuth } from "./auth";
import type { Employee } from "../pages/hr/EmployeesPage";

interface UserRecord {
  user_id: number;
  owner_type: "EMPLOYEE" | "STUDENT" | "GUARDIAN";
  owner_ref_id: number;
}

export function useCurrentEmployee() {
  const { user } = useAuth();
  const [employee, setEmployee] = useState<Employee | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!user) {
      setIsLoading(false);
      return;
    }

    api
      .get<{ data: UserRecord }>(`/administration/users/${user.userId}`)
      .then((userResponse) => {
        const record = userResponse.data.data;
        if (record.owner_type !== "EMPLOYEE") {
          setError("This login is not linked to an Employee record.");
          return null;
        }
        return api.get<{ data: Employee }>(`/hr-payroll/employees/${record.owner_ref_id}`);
      })
      .then((employeeResponse) => {
        if (employeeResponse) setEmployee(employeeResponse.data.data);
      })
      .catch(() => setError("This login is not linked to a valid Employee record."))
      .finally(() => setIsLoading(false));
  }, [user]);

  return { employee, isLoading, error };
}
