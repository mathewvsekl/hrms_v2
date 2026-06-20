import { useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import MainLayout from './components/layout/MainLayout';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import useAuthStore from './store/useAuthStore';

import Employees from './pages/Employees';
import EmployeeProfile from './pages/EmployeeProfile';
import Attendance from './pages/Attendance';
import Leave from './pages/Leave';
import Payroll from './pages/Payroll';
import Payslips from './pages/Payslips';
import EmployeePayslips from './pages/EmployeePayslips';
import SalaryAdvancesPage from './pages/SalaryAdvancesPage';
import EmployeeAdvances from './pages/EmployeeAdvances';
import AppraisalCycleList from './pages/Appraisal/AppraisalCycleList';
import AppraisalForm from './pages/Appraisal/AppraisalForm';
import AppraisalSettings from './pages/Appraisal/AppraisalSettings';
import KPIConfig from './pages/Appraisal/KPIConfig';
import AppraisalTemplateBuilder from './pages/Appraisal/AppraisalTemplateBuilder';
import AppraisalCycleManagement from './pages/Appraisal/AppraisalCycleManagement';
import AppraisalLetterView from './pages/Appraisal/AppraisalLetterView';
import AppraisalMatrixBuilder from './pages/Appraisal/AppraisalMatrixBuilder';
import Onboarding from './pages/Onboarding';
import Offboarding from './pages/Offboarding';
import Reports from './pages/Reports';
import AttendanceReport from './pages/AttendanceReport';
import Organization from './pages/Organization';
import Admin from './pages/Admin';
import CompanyDetail from './pages/CompanyDetail';
import EmployeePortal from './pages/EmployeePortal';
import Assets from './pages/Assets';
import Notifications from './pages/Notifications';
import Policies from './pages/Policies';
import NotFound from './pages/NotFound';
import EmployeeAssets from './pages/EmployeeAssets';
import ActionRequired from './pages/ActionRequired';

import useNotificationStore from './store/useNotificationStore';

// A simple wrapper to protect routes
const ProtectedRoute = ({ children }) => {
  const isAuthenticated = useAuthStore(state => state.isAuthenticated);
  return isAuthenticated ? children : <Navigate to="/login" replace />;
};

import AlertDialog from './components/ui/AlertDialog';

import { isAdmin as checkIsAdmin, isSessionExempt } from './utils/roleConstants';

function App() {
  const user = useAuthStore(state => state.user);
  const userRoleId = user?.role_id ?? 0;
  
  const hasAdminAccess = [
      ['admin portal', 'view'],
      ['configuration', 'view'],
      ['employees', 'view'],
      ['offboarding', 'view'],
      ['reports', 'view'],
      ['assets', 'view'],
      ['payroll', 'edit']
  ].some(([mod, act]) => useAuthStore.getState().hasPermission(mod, act));

  const isAdmin = checkIsAdmin(userRoleId) || hasAdminAccess;

  const isAuthenticated = useAuthStore(state => state.isAuthenticated);
  const syncUser = useAuthStore(state => state.syncUser);
  const logout = useAuthStore(state => state.logout);
  const showAlert = useNotificationStore(state => state.showAlert);
  const hasModuleAccess = useAuthStore(state => state.hasModuleAccess);

  useEffect(() => {
    if (isAuthenticated) {
      syncUser();
    }
  }, [isAuthenticated, syncUser]);

  useEffect(() => {
    let timeoutId;

    const isExemptAdmin = isSessionExempt(userRoleId);

    const resetTimer = () => {
      if (timeoutId) clearTimeout(timeoutId);
      // 15 minutes = 15 * 60 * 1000 = 900000 ms
      timeoutId = setTimeout(() => {
        logout();
        showAlert(
          'Session Expired', 
          'Your session has expired due to 15 minutes of inactivity. Please log in again.', 
          'warning'
        );
      }, 900000);
    };

    if (isAuthenticated && !isExemptAdmin) {
      resetTimer();
      const events = ['mousemove', 'keydown', 'click', 'scroll', 'api_activity'];
      const handleActivity = () => resetTimer();
      events.forEach(e => window.addEventListener(e, handleActivity));

      return () => {
        if (timeoutId) clearTimeout(timeoutId);
        events.forEach(e => window.removeEventListener(e, handleActivity));
      };
    }
  }, [isAuthenticated, isAdmin, logout]);

  return (
    <>
      <AlertDialog />
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<Login />} />

          <Route element={<ProtectedRoute><MainLayout /></ProtectedRoute>}>
            <Route path="/" element={<Navigate to={isAdmin ? "/dashboard" : "/employee-profile"} replace />} />
            <Route path="/dashboard" element={<Dashboard />} />
            <Route path="/portal" element={<EmployeePortal />} />

            <Route path="/employees" element={<Employees />} />
            <Route path="/employee-profile" element={<EmployeeProfile />} />
            <Route path="/onboarding" element={isAdmin && hasModuleAccess('employees') ? <Onboarding /> : <Navigate to="/dashboard" replace />} />
            <Route path="/attendance" element={<Attendance />} />
            <Route path="/leave" element={<Leave />} />
            <Route path="/payroll" element={isAdmin && hasModuleAccess('payroll') ? <Payroll /> : <Navigate to="/dashboard" replace />} />
            <Route path="/payslips" element={isAdmin ? <Payslips /> : <EmployeePayslips />} />
            <Route path="/salary-advances" element={isAdmin ? <SalaryAdvancesPage /> : <EmployeeAdvances />} />
            <Route path="/appraisals" element={<AppraisalCycleList />} />
            <Route path="/appraisals/settings" element={isAdmin ? <AppraisalSettings /> : <Navigate to="/appraisals" replace />} />
            <Route path="/appraisals/templates" element={isAdmin ? <AppraisalTemplateBuilder /> : <Navigate to="/appraisals" replace />} />
            <Route path="/appraisals/cycles" element={isAdmin ? <AppraisalCycleManagement /> : <Navigate to="/appraisals" replace />} />
            <Route path="/appraisals/matrices" element={isAdmin ? <AppraisalMatrixBuilder /> : <Navigate to="/appraisals" replace />} />
            <Route path="/appraisals/letter/:id" element={<AppraisalLetterView />} />
            <Route path="/appraisals/config/:employeeId" element={isAdmin && hasModuleAccess('appraisals') ? <KPIConfig /> : <Navigate to="/appraisals" replace />} />
            <Route path="/appraisals/:id" element={<AppraisalForm />} />
            <Route path="/offboarding" element={isAdmin && hasModuleAccess('offboarding') ? <Offboarding /> : <Navigate to="/dashboard" replace />} />
            <Route path="/reports" element={isAdmin && hasModuleAccess('reports') ? <Reports /> : <Navigate to="/dashboard" replace />} />
            <Route path="/attendance-report" element={isAdmin ? <AttendanceReport /> : <Navigate to="/dashboard" replace />} />
            <Route path="/organization" element={isAdmin ? <Organization /> : <Navigate to="/dashboard" replace />} />
            <Route path="/admin" element={isAdmin ? <Admin /> : <Navigate to="/dashboard" replace />} />
            <Route path="/admin/company/:id" element={isAdmin ? <CompanyDetail /> : <Navigate to="/admin" replace />} />
            <Route path="/assets" element={isAdmin ? <Assets /> : <EmployeeAssets />} />
            <Route path="/notifications" element={<Notifications />} />
            <Route path="/action-required" element={<ActionRequired />} />
            <Route path="/policies" element={<Policies />} />
            
            {/* Catch-all 404 inside layout */}
            <Route path="*" element={<NotFound />} />
          </Route>
        </Routes>
      </BrowserRouter>
    </>
  );
}

export default App;
