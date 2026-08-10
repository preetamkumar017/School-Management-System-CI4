import { BrowserRouter, Route, Routes } from "react-router-dom";
import { AuthProvider } from "./lib/auth";
import ProtectedRoute from "./components/ProtectedRoute";
import DashboardLayout from "./components/DashboardLayout";
import LoginPage from "./pages/LoginPage";
import DashboardPage from "./pages/DashboardPage";
import AdministrationPage from "./pages/administration/AdministrationPage";
import AcademicPage from "./pages/academic/AcademicPage";
import StudentsPage from "./pages/sis/StudentsPage";
import AdmissionPage from "./pages/admission/AdmissionPage";
import FeesPage from "./pages/fees/FeesPage";
import AttendancePage from "./pages/attendance/AttendancePage";
import ExaminationPage from "./pages/examination/ExaminationPage";
import TimetablePage from "./pages/timetable/TimetablePage";
import HrPayrollPage from "./pages/hr/HrPayrollPage";
import MyHrPage from "./pages/hr/MyHrPage";
import LibraryPage from "./pages/library/LibraryPage";
import TransportPage from "./pages/transport/TransportPage";
import CommunicationPage from "./pages/communication/CommunicationPage";
import ReportsPage from "./pages/reports/ReportsPage";
import ProfilePage from "./pages/ProfilePage";

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
                    <Route path="/academic" element={<AcademicPage />} />
                    <Route path="/students" element={<StudentsPage />} />
                    <Route path="/admission" element={<AdmissionPage />} />
                    <Route path="/fees" element={<FeesPage />} />
                    <Route path="/attendance" element={<AttendancePage />} />
                    <Route path="/examination" element={<ExaminationPage />} />
                    <Route path="/timetable" element={<TimetablePage />} />
                    <Route path="/hr-payroll" element={<HrPayrollPage />} />
                    <Route path="/my-hr" element={<MyHrPage />} />
                    <Route path="/library" element={<LibraryPage />} />
                    <Route path="/transport" element={<TransportPage />} />
                    <Route path="/communication" element={<CommunicationPage />} />
                    <Route path="/reports" element={<ReportsPage />} />
                    <Route path="/profile" element={<ProfilePage />} />
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
