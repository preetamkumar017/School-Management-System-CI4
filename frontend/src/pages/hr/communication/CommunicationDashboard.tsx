import NoticeBoard from "./NoticeBoard";
import UpcomingEventsWidget from "./UpcomingEventsWidget";

export default function CommunicationDashboard() {
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-lg font-bold text-slate-900 dark:text-white">Staff Communication</h2>
          <p className="text-sm text-slate-500 dark:text-slate-400">Manage notices, circulars, and track upcoming staff events.</p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2">
          <NoticeBoard />
        </div>
        <div className="lg:col-span-1">
          <UpcomingEventsWidget />
        </div>
      </div>
    </div>
  );
}
