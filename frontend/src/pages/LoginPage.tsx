import { useState, type FormEvent } from "react";
import { Navigate, useLocation, useNavigate } from "react-router-dom";
import { useAuth, apiErrorMessage } from "../lib/auth";
import { api } from "../lib/api";
import Modal from "../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../components/ui/form";

export default function LoginPage() {
  const { login, isAuthenticated } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Forgot password flow states
  const [isForgotOpen, setIsForgotOpen] = useState(false);
  const [forgotStep, setForgotStep] = useState<1 | 2>(1);
  const [forgotUsername, setForgotUsername] = useState("");
  const [resetCode, setResetCode] = useState(""); // Exposes generated code for easy local testing
  const [verCode, setVerCode] = useState("");
  const [newPwd, setNewPwd] = useState("");
  const [confirmNewPwd, setConfirmNewPwd] = useState("");
  const [forgotErr, setForgotErr] = useState<string | null>(null);
  const [forgotSuccess, setForgotSuccess] = useState<string | null>(null);
  const [isForgotSubmitting, setIsForgotSubmitting] = useState(false);

  if (isAuthenticated) {
    const redirectTo = (location.state as { from?: string } | null)?.from ?? "/";
    return <Navigate to={redirectTo} replace />;
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);
    try {
      await login(username, password);
      navigate("/", { replace: true });
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleRequestCode(e: FormEvent) {
    e.preventDefault();
    setForgotErr(null);
    setIsForgotSubmitting(true);
    try {
      const res = await api.post<{ success: boolean; data: { reset_code?: string; message: string } }>(
        "/auth/forgot-password",
        { username: forgotUsername }
      );
      if (res.data.data?.reset_code) {
        setResetCode(res.data.data.reset_code);
      }
      setForgotStep(2);
    } catch (err) {
      setForgotErr(apiErrorMessage(err));
    } finally {
      setIsForgotSubmitting(false);
    }
  }

  async function handleResetPassword(e: FormEvent) {
    e.preventDefault();
    if (newPwd !== confirmNewPwd) {
      setForgotErr("New passwords do not match.");
      return;
    }
    setForgotErr(null);
    setIsForgotSubmitting(true);
    try {
      await api.post("/auth/reset-password", {
        username: forgotUsername,
        verification_code: verCode,
        new_password: newPwd,
      });
      setForgotSuccess("Password reset successfully! You can now sign in.");
      setTimeout(() => {
        setIsForgotOpen(false);
        setForgotStep(1);
        setForgotUsername("");
        setResetCode("");
        setVerCode("");
        setNewPwd("");
        setConfirmNewPwd("");
        setForgotSuccess(null);
      }, 3000);
    } catch (err) {
      setForgotErr(apiErrorMessage(err));
    } finally {
      setIsForgotSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 dark:bg-slate-900">
      <div className="w-full max-w-sm rounded-lg border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-950">
        <h1 className="mb-6 text-xl font-semibold text-slate-900 dark:text-slate-100">
          School ERP — Sign in
        </h1>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label htmlFor="username" className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
              Username
            </label>
            <input
              id="username"
              type="text"
              autoComplete="username"
              required
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            />
          </div>

          <div>
            <div className="flex justify-between items-center mb-1">
              <label htmlFor="password" className="text-sm font-medium text-slate-700 dark:text-slate-300">
                Password
              </label>
              <button
                type="button"
                onClick={() => {
                  setIsForgotOpen(true);
                  setForgotStep(1);
                  setForgotErr(null);
                }}
                className="text-xs text-indigo-600 hover:underline dark:text-indigo-400 font-semibold"
              >
                Forgot Password?
              </button>
            </div>
            <input
              id="password"
              type="password"
              autoComplete="current-password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            />
          </div>

          {error && (
            <p role="alert" className="text-sm text-red-600 dark:text-red-400">
              {error}
            </p>
          )}

          <button
            type="submit"
            disabled={isSubmitting}
            className="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-700 disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
          >
            {isSubmitting ? "Signing in..." : "Sign in"}
          </button>
        </form>
      </div>

      {/* Forgot Password Modal */}
      {isForgotOpen && (
        <Modal title="Password Recovery" onClose={() => setIsForgotOpen(false)}>
          {forgotStep === 1 ? (
            <form onSubmit={handleRequestCode} className="space-y-4">
              <p className="text-xs text-slate-500 dark:text-slate-400">
                Enter your username to request a password reset verification code.
              </p>
              <div>
                <label className={labelClass}>Username</label>
                <input
                  required
                  type="text"
                  placeholder="Enter your username"
                  value={forgotUsername}
                  onChange={(e) => setForgotUsername(e.target.value)}
                  className={inputClass}
                />
              </div>

              {forgotErr && (
                <p role="alert" className="text-xs text-red-600 dark:text-red-400 font-semibold">
                  {forgotErr}
                </p>
              )}

              <div className="flex justify-end gap-2 pt-2">
                <button type="button" onClick={() => setIsForgotOpen(false)} className={secondaryButtonClass}>
                  Cancel
                </button>
                <button type="submit" disabled={isForgotSubmitting} className={primaryButtonClass}>
                  {isForgotSubmitting ? "Sending..." : "Request Code"}
                </button>
              </div>
            </form>
          ) : (
            <form onSubmit={handleResetPassword} className="space-y-4">
              <div className="rounded-lg bg-indigo-50 p-3 text-xs text-indigo-800 dark:bg-indigo-950/20 dark:text-indigo-400">
                🔑 <strong>Local Verification Code:</strong> <span className="font-bold text-slate-900 dark:text-white">{resetCode || "123456"}</span>
                <p className="mt-1 text-[10px] text-slate-500 dark:text-slate-400">Use this generated code to reset your password instantly.</p>
              </div>

              <div>
                <label className={labelClass}>Verification Code</label>
                <input
                  required
                  type="text"
                  placeholder="Enter 6-digit code"
                  value={verCode}
                  onChange={(e) => setVerCode(e.target.value)}
                  className={inputClass}
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className={labelClass}>New Password</label>
                  <input
                    required
                    type="password"
                    placeholder="New password"
                    value={newPwd}
                    onChange={(e) => setNewPwd(e.target.value)}
                    className={inputClass}
                  />
                </div>
                <div>
                  <label className={labelClass}>Confirm Password</label>
                  <input
                    required
                    type="password"
                    placeholder="Confirm password"
                    value={confirmNewPwd}
                    onChange={(e) => setConfirmNewPwd(e.target.value)}
                    className={inputClass}
                  />
                </div>
              </div>

              {forgotErr && (
                <p role="alert" className="text-xs text-red-600 dark:text-red-400 font-semibold">
                  {forgotErr}
                </p>
              )}

              {forgotSuccess && (
                <p className="text-xs text-green-600 dark:text-green-400 font-semibold">
                  {forgotSuccess}
                </p>
              )}

              <div className="flex justify-end gap-2 pt-2">
                <button type="button" onClick={() => setForgotStep(1)} className={secondaryButtonClass}>
                  Back
                </button>
                <button type="submit" disabled={isForgotSubmitting || !verCode || !newPwd} className={primaryButtonClass}>
                  {isForgotSubmitting ? "Resetting..." : "Reset Password"}
                </button>
              </div>
            </form>
          )}
        </Modal>
      )}
    </div>
  );
}
