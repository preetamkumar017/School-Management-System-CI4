import { useState, useEffect } from "react";
import { api } from "../../../lib/api";

export default function UpcomingEventsWidget() {
  const [events, setEvents] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchEvents();
  }, []);

  const fetchEvents = async () => {
    try {
      setLoading(true);
      const res = await api.get<{ data: any[] }>("/hr-payroll/communications/events");
      setEvents(res.data.data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const currentMonth = new Date().toLocaleString('default', { month: 'long' });

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <h2 className="text-lg font-bold text-slate-900 dark:text-white mb-1">Upcoming Events</h2>
      <p className="text-sm text-slate-500 mb-6 border-b border-slate-200 pb-4 dark:border-slate-800">For {currentMonth}</p>

      {loading ? (
        <p className="text-sm text-slate-500">Loading events...</p>
      ) : events.length === 0 ? (
        <div className="py-6 text-center">
          <span className="text-3xl block mb-2">🎈</span>
          <p className="text-sm text-slate-500">No birthdays or anniversaries coming up soon.</p>
        </div>
      ) : (
        <div className="space-y-4">
          {events.map((ev, idx) => (
            <div key={idx} className="flex items-center gap-4">
              <div className={`flex flex-col items-center justify-center h-12 w-12 rounded-lg ${ev.type === 'Birthday' ? 'bg-rose-100 text-rose-700' : 'bg-blue-100 text-blue-700'}`}>
                <span className="text-xs font-bold uppercase">{currentMonth.slice(0, 3)}</span>
                <span className="text-lg font-black leading-none">{ev.day}</span>
              </div>
              <div>
                <h4 className="font-semibold text-slate-900 dark:text-white text-sm">{ev.employee_name}</h4>
                <p className="text-xs text-slate-500 flex items-center gap-1">
                  {ev.type === 'Birthday' ? '🎂 Birthday' : `🏆 ${ev.years} Year Anniversary`}
                </p>
                {ev.department && <p className="text-[10px] text-slate-400 mt-0.5">{ev.department}</p>}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
