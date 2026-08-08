import { BrowserRouter, Route, Routes } from "react-router-dom";
import { AuthProvider } from "./lib/auth";
import ProtectedRoute from "./components/ProtectedRoute";
import DashboardLayout from "./components/DashboardLayout";
import LoginPage from "./pages/LoginPage";
import DashboardPage from "./pages/DashboardPage";
import AdministrationPage from "./pages/administration/AdministrationPage";
import StudentsPage from "./pages/sis/StudentsPage";
import AdmissionPage from "./pages/admission/AdmissionPage";
import FeesPage from "./pages/fees/FeesPage";
import AttendancePage from "./pages/attendance/AttendancePage";
import ExaminationPage from "./pages/examination/ExaminationPage";

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route
            path="/*"
            element={
              <ProtectedRoute>
                <DashboardLayout>
                  <Routes>
                    <Route path="/" element={<DashboardPage />} />
                    <Route path="/administration" element={<AdministrationPage />} />
                    <Route path="/students" element={<StudentsPage />} />
                    <Route path="/admission" element={<AdmissionPage />} />
                    <Route path="/fees" element={<FeesPage />} />
                    <Route path="/attendance" element={<AttendancePage />} />
                    <Route path="/examination" element={<ExaminationPage />} />
                  </Routes>
                </DashboardLayout>
              </ProtectedRoute>
            }
          />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
