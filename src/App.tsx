import { useState } from 'react';
import Field from './components/Field';

type Theme = 'slate' | 'indigo' | 'emerald' | 'contrast';
type FontSize = 'standard' | 'large';

function App() {
  const [theme, setTheme] = useState<Theme>('indigo');
  const [fontSize, setFontSize] = useState<FontSize>('standard');
  const [showSettings, setShowSettings] = useState(false);
  const [score, setScore] = useState(0);
  const [streak, setStreak] = useState(0);

  // Generate a persistent user ID for local storage
  const [userId] = useState<string>(() => {
    let id = localStorage.getItem('trivia_user_id');
    if (!id) {
      id = 'user_' + Math.random().toString(36).substring(2, 11);
      localStorage.setItem('trivia_user_id', id);
    }
    return id;
  });

  // Accessibility theme configurations
  const themeClasses: Record<Theme, string> = {
    indigo: 'bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-indigo-950/70 via-slate-950 to-slate-950 text-slate-100',
    slate: 'bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-slate-900 via-slate-950 to-slate-950 text-slate-100',
    emerald: 'bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-emerald-950/60 via-slate-950 to-slate-950 text-slate-100',
    contrast: 'bg-black text-white border-white',
  };

  const textScaleClasses = {
    standard: {
      title: 'text-lg font-bold tracking-tight',
      heading: 'text-2xl font-bold tracking-tight leading-snug',
      body: 'text-sm text-slate-400',
    },
    large: {
      title: 'text-xl font-bold tracking-tight',
      heading: 'text-3xl font-extrabold tracking-tight leading-snug',
      body: 'text-base text-slate-300',
    },
  };

  return (
    <div className={`h-screen w-screen flex flex-col justify-between overflow-hidden relative transition-colors duration-500 select-none ${themeClasses[theme]}`}>
      {/* Background ambient glows (disabled in high-contrast mode) */}
      {theme !== 'contrast' && (
        <div className="absolute inset-0 pointer-events-none overflow-hidden">
          <div className={`absolute top-[-20%] left-[-20%] w-[80%] aspect-square rounded-full blur-[120px] opacity-40 transition-colors duration-700 ${
            theme === 'indigo' ? 'bg-indigo-600' : theme === 'emerald' ? 'bg-emerald-600' : 'bg-slate-700'
          }`} />
          <div className={`absolute bottom-[-10%] right-[-10%] w-[60%] aspect-square rounded-full blur-[100px] opacity-25 transition-colors duration-700 ${
            theme === 'indigo' ? 'bg-purple-800' : theme === 'emerald' ? 'bg-teal-800' : 'bg-slate-800'
          }`} />
        </div>
      )}

      {/* HEADER */}
      <header className="h-16 shrink-0 border-b border-white/5 bg-slate-900/40 backdrop-blur-md px-4 flex items-center justify-between z-10">
        <div className="flex items-center gap-2">
          <span className="w-8 h-8 rounded-lg bg-gradient-to-tr from-indigo-500 to-purple-500 flex items-center justify-center font-bold text-white shadow-lg shadow-indigo-500/20">
            T
          </span>
          <div>
            <h1 className={textScaleClasses[fontSize].title}>TriviaTrain</h1>
            <span className="text-[10px] text-slate-500 block leading-none font-medium">Player App</span>
          </div>
        </div>

        {/* Player Stats */}
        <div className="flex items-center gap-4">
          <div className="flex items-center gap-1.5 bg-white/5 border border-white/5 rounded-full px-3 py-1 shadow-inner">
            <svg className="w-4 h-4 text-amber-500 fill-amber-500 animate-pulse" viewBox="0 0 24 24">
              <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
            </svg>
            <span className="text-xs font-bold font-mono tracking-tight">{score} XP</span>
          </div>

          <div className="flex items-center gap-1 bg-amber-500/10 border border-amber-500/20 text-amber-500 rounded-full px-2.5 py-1">
            <span className="text-xs">🔥</span>
            <span className="text-xs font-extrabold font-mono">{streak}</span>
          </div>

          <button 
            onClick={() => setShowSettings(!showSettings)}
            className="p-2 rounded-full bg-white/5 border border-white/5 hover:bg-white/10 active:scale-95 transition-all cursor-pointer"
            aria-label="Accessibility Settings"
          >
            <svg className="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </button>
        </div>
      </header>

      {/* MAIN CONTAINER / FIELD */}
      <main className="flex-1 w-full max-w-md mx-auto px-4 flex flex-col justify-center items-center overflow-hidden relative z-0">
        <Field
          userId={userId}
          fontSize={fontSize}
          onUpdateScore={setScore}
          onUpdateStreak={setStreak}
          score={score}
          streak={streak}
        />
      </main>

      {/* SETTINGS DRAWER / DIALOG */}
      {showSettings && (
        <div className="absolute inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-end justify-center">
          <div className="w-full max-w-md bg-slate-900 border-t border-white/10 rounded-t-3xl p-6 pb-8 shadow-2xl transform transition-transform animate-slide-up">
            <div className="flex justify-between items-center mb-6">
              <h3 className="text-lg font-bold text-white">Accessibility & Themes</h3>
              <button 
                onClick={() => setShowSettings(false)}
                className="text-xs text-slate-400 hover:text-white font-medium bg-white/5 px-3 py-1 rounded-full cursor-pointer"
              >
                Close
              </button>
            </div>

            <div className="space-y-6">
              {/* Color Themes */}
              <div>
                <label className="text-xs uppercase font-bold tracking-widest text-slate-500 block mb-2.5">
                  Visual Theme
                </label>
                <div className="grid grid-cols-4 gap-2">
                  {(['indigo', 'slate', 'emerald', 'contrast'] as Theme[]).map((t) => (
                    <button
                      key={t}
                      onClick={() => setTheme(t)}
                      className={`h-11 rounded-xl border flex flex-col items-center justify-center cursor-pointer capitalize text-xs font-semibold ${
                        theme === t 
                          ? 'border-indigo-500 bg-indigo-500/10 text-indigo-400' 
                          : 'border-white/5 bg-slate-950/40 text-slate-400 hover:bg-white/5'
                      }`}
                    >
                      {t}
                    </button>
                  ))}
                </div>
              </div>

              {/* Font Size */}
              <div>
                <label className="text-xs uppercase font-bold tracking-widest text-slate-500 block mb-2.5">
                  Text Scaling
                </label>
                <div className="grid grid-cols-2 gap-2">
                  {(['standard', 'large'] as FontSize[]).map((sz) => (
                    <button
                      key={sz}
                      onClick={() => setFontSize(sz)}
                      className={`h-11 rounded-xl border flex items-center justify-center cursor-pointer capitalize text-xs font-semibold ${
                        fontSize === sz 
                          ? 'border-indigo-500 bg-indigo-500/10 text-indigo-400' 
                          : 'border-white/5 bg-slate-950/40 text-slate-400 hover:bg-white/5'
                      }`}
                    >
                      {sz} Text
                    </button>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* FOOTER BAR */}
      <footer className="h-16 shrink-0 border-t border-white/5 bg-slate-900/40 backdrop-blur-md flex items-center justify-around px-6 z-10">
        <button className="flex flex-col items-center gap-1 text-slate-400 hover:text-white cursor-pointer active:scale-95 transition-all">
          <svg className="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
          <span className="text-[10px] font-medium leading-none">Home</span>
        </button>

        <button className="flex flex-col items-center gap-1 text-indigo-400 cursor-pointer active:scale-95 transition-all">
          <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5L6.8 12.247a4 4 0 106.4 0L10.867 7.5A1 1 0 0010 7z" clipRule="evenodd" />
          </svg>
          <span className="text-[10px] font-bold leading-none">The Field</span>
        </button>

        <button className="flex flex-col items-center gap-1 text-slate-400 hover:text-white cursor-pointer active:scale-95 transition-all">
          <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
          <span className="text-[10px] font-medium leading-none">Profile</span>
        </button>
      </footer>
    </div>
  );
}

export default App;
