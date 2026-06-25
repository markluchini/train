import { useState, useEffect, useRef } from 'react';
import type { QuestionWindow } from '../types';

interface FieldProps {
  userId: string;
  fontSize: 'standard' | 'large';
  onUpdateScore: (updater: (s: number) => number) => void;
  onUpdateStreak: (updater: (s: number) => number) => void;
  score: number;
  streak: number;
}

const API_BASE = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
  ? 'http://localhost:8000/api'
  : '/api';

export default function Field({
  userId,
  fontSize,
  onUpdateScore,
  onUpdateStreak,
}: FieldProps) {
  const [windowState, setWindowState] = useState<QuestionWindow | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Interaction engine states
  const [isFlipped, setIsFlipped] = useState(false);
  const [selectedAnswer, setSelectedAnswer] = useState<number | null>(null);
  const [readingTimeLeft, setReadingTimeLeft] = useState(5);
  const [graceTimeLeft, setGraceTimeLeft] = useState(2);
  const [isGraceActive, setIsGraceActive] = useState(false);
  const [submissionResult, setSubmissionResult] = useState<{
    correct: boolean;
    score: number;
    explanation: string;
  } | null>(null);

  // Telemetry track values
  const [selectionHistory, setSelectionHistory] = useState<number[]>([]);
  const timeDetails = useRef<{
    receiptTime: string;
    startTime: number;
    readingTime: number;
    optionSelectTime: number;
  }>({
    receiptTime: '',
    startTime: 0,
    readingTime: 0,
    optionSelectTime: 0,
  });

  // Long press refs
  const longPressTimer = useRef<any>(null);
  const isLongPressActive = useRef(false);

  // 1. Fetch Question Window
  const loadQuestionWindow = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`${API_BASE}/question.php?userId=${encodeURIComponent(userId)}`);
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      const json = await res.json();
      if (json.status === 'success') {
        setWindowState(json.data);
        resetQuestionState();
      } else {
        throw new Error(json.data?.message || 'Failed to fetch question');
      }
    } catch (err: any) {
      console.error(err);
      setError(err.message || 'Connection error. Make sure the PHP backend is running.');
    } finally {
      setLoading(false);
    }
  };

  const resetQuestionState = () => {
    setIsFlipped(false);
    setSelectedAnswer(null);
    setReadingTimeLeft(5);
    setGraceTimeLeft(2);
    setIsGraceActive(false);
    setSubmissionResult(null);
    setSelectionHistory([]);

    const now = new Date().toISOString();
    timeDetails.current = {
      receiptTime: now,
      startTime: Date.now(),
      readingTime: 0,
      optionSelectTime: 0,
    };
  };

  // Load question window on start or userId change
  useEffect(() => {
    loadQuestionWindow();
  }, [userId]);

  // 2. Reading Timer (5s)
  useEffect(() => {
    if (loading || error || isFlipped || selectedAnswer !== null) return;

    const timer = setInterval(() => {
      setReadingTimeLeft((prev) => {
        if (prev <= 0.1) {
          clearInterval(timer);
          // Auto-flip card when reading time expires
          setIsFlipped(true);
          timeDetails.current.readingTime = 5;
          timeDetails.current.optionSelectTime = Date.now();
          return 0;
        }
        return prev - 0.1;
      });
    }, 100);

    return () => clearInterval(timer);
  }, [loading, error, isFlipped, selectedAnswer]);

  // 3. Grace Submission Timer (2s)
  useEffect(() => {
    if (!isGraceActive || selectedAnswer === null) return;

    const timer = setInterval(() => {
      setGraceTimeLeft((prev) => {
        if (prev <= 0.1) {
          clearInterval(timer);
          triggerSubmission();
          return 0;
        }
        return prev - 0.1;
      });
    }, 100);

    return () => clearInterval(timer);
  }, [isGraceActive, selectedAnswer]);

  // Handle manual flip by user tapping card front
  const handleCardFrontTap = () => {
    if (isFlipped) return;
    setIsFlipped(true);
    // Record reading duration
    const duration = (Date.now() - timeDetails.current.startTime) / 1000;
    timeDetails.current.readingTime = parseFloat(duration.toFixed(2));
    timeDetails.current.optionSelectTime = Date.now();
  };

  // Select an option with standard click (triggers 2s grace)
  const handleSelectOption = (idx: number) => {
    if (submissionResult) return;
    setSelectedAnswer(idx);
    setSelectionHistory((prev) => [...prev, idx]);
    setGraceTimeLeft(2);
    setIsGraceActive(true);
  };

  // Submit telemetry to backend
  const triggerSubmission = async (immediateIndex?: number) => {
    setIsGraceActive(false);

    const finalAnswerIndex = immediateIndex !== undefined ? immediateIndex : selectedAnswer;
    if (finalAnswerIndex === null || !windowState?.current) return;

    // Calculate decision time
    const decTime = (Date.now() - timeDetails.current.optionSelectTime) / 1000;
    const finalDecideTime = parseFloat(decTime.toFixed(2));

    const finalSelectionHistory = immediateIndex !== undefined 
      ? [...selectionHistory, immediateIndex]
      : selectionHistory;

    const payload = {
      userId,
      questionId: windowState.current.id,
      selectedOptionIndex: finalAnswerIndex,
      readingTime: timeDetails.current.readingTime,
      decideTime: finalDecideTime,
      selectionHistory: finalSelectionHistory,
      deviceMetadata: {
        userAgent: navigator.userAgent,
        screenSize: `${window.innerWidth}x${window.innerHeight}`,
      },
      timestamps: {
        receiptTime: timeDetails.current.receiptTime,
        startTime: new Date(timeDetails.current.startTime).toISOString(),
        endTime: new Date().toISOString(),
      }
    };

    try {
      const res = await fetch(`${API_BASE}/submit.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      if (!res.ok) {
        throw new Error(`Submit failed with HTTP status ${res.status}`);
      }

      const json = await res.json();
      if (json.status === 'success') {
        setSubmissionResult(json.data);
        
        // Update score & streak in layout
        if (json.data.correct) {
          onUpdateScore((s) => s + json.data.score);
          onUpdateStreak((st) => st + 1);
        } else {
          onUpdateStreak(() => 0);
        }
      } else {
        throw new Error(json.data?.message || 'Submission failed');
      }
    } catch (err: any) {
      console.error(err);
      alert('Failed to log submission: ' + err.message);
    }
  };

  // Gesture handling: Double-Click (Immediate submission)
  const handleDoubleClickOption = (idx: number) => {
    if (submissionResult) return;
    clearTimeout(longPressTimer.current!);
    setSelectedAnswer(idx);
    triggerSubmission(idx);
  };

  // Gesture handling: Long Press Start (Immediate submission after 500ms)
  const handleTouchStartOption = (idx: number) => {
    if (submissionResult) return;
    isLongPressActive.current = false;
    longPressTimer.current = setTimeout(() => {
      isLongPressActive.current = true;
      setSelectedAnswer(idx);
      triggerSubmission(idx);
    }, 550); // > 500ms is standard long press
  };

  const handleTouchEndOption = () => {
    clearTimeout(longPressTimer.current!);
  };

  // Next question action
  const handleNextQuestion = () => {
    loadQuestionWindow();
  };

  // Text scaling styles
  const fontStyles = fontSize === 'large' 
    ? {
        heading: 'text-2xl font-bold leading-snug',
        body: 'text-base',
        option: 'text-base font-semibold',
      }
    : {
        heading: 'text-lg font-bold leading-snug',
        body: 'text-xs',
        option: 'text-sm font-medium',
      };

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center h-[350px]">
        <div className="w-8 h-8 rounded-full border-4 border-indigo-500 border-t-transparent animate-spin mb-4" />
        <p className="text-slate-400 text-xs font-semibold">Buffering deck sliding window...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex flex-col items-center justify-center h-[350px] text-center px-6">
        <span className="text-3xl mb-4">⚠️</span>
        <h3 className="text-sm font-bold text-white mb-2">Backend Connection Error</h3>
        <p className="text-slate-400 text-xs mb-6 leading-relaxed">{error}</p>
        <button 
          onClick={loadQuestionWindow}
          className="bg-indigo-600 hover:bg-indigo-500 active:scale-95 text-white text-xs font-bold px-4 py-2.5 rounded-xl cursor-pointer transition-all"
        >
          Retry Connection
        </button>
      </div>
    );
  }

  const currentQuestion = windowState?.current;
  if (!currentQuestion) {
    return (
      <div className="flex flex-col items-center justify-center h-[350px] text-center px-6">
        <span className="text-3xl mb-4">🏁</span>
        <h3 className="text-sm font-bold text-white mb-2">No Questions Available</h3>
        <p className="text-slate-400 text-xs">Create questions using the admin portal first!</p>
      </div>
    );
  }

  return (
    <div className="w-full relative flex flex-col items-center justify-center h-[420px] select-none">
      
      {/* Visual buffer cards (sliding-window indicator) */}
      <div className="absolute w-[86%] h-[340px] rounded-3xl bg-slate-900/10 border border-white/5 translate-y-8 opacity-10 scale-[0.92] blur-[0.5px] z-[-2] pointer-events-none" />
      <div className="absolute w-[93%] h-[340px] rounded-3xl bg-slate-900/20 border border-white/5 translate-y-4 opacity-35 scale-[0.96] z-[-1] pointer-events-none" />

      {/* Main card */}
      <div className="w-full h-[340px] perspective-1000 relative">
        <div className={`w-full h-full relative transform-style-3d transition-transform duration-500 ${isFlipped ? 'rotate-y-180' : ''}`}>
          
          {/* CARD FRONT: Question Prompt & Reading Timer */}
          <div 
            onClick={handleCardFrontTap}
            className="absolute inset-0 w-full h-full backface-hidden rounded-3xl glass-panel p-6 flex flex-col justify-between shadow-2xl transition-all duration-300 hover:border-white/12 cursor-pointer"
          >
            <div className="flex justify-between items-center">
              <span className="text-[9px] uppercase font-bold tracking-widest text-indigo-400 bg-indigo-500/10 px-2.5 py-1 rounded-md border border-indigo-500/20">
                {currentQuestion.difficulty}
              </span>
              <span className="text-[10px] font-mono text-slate-500 font-medium">
                Active Slot
              </span>
            </div>

            {/* Reading Timer Progress Bar */}
            <div className="w-full h-1 bg-white/5 rounded-full overflow-hidden mt-4 shrink-0">
              <div 
                className="h-full bg-indigo-500 transition-all duration-100" 
                style={{ width: `${(readingTimeLeft / 5) * 100}%` }}
              />
            </div>

            <div className="my-auto py-2">
              <h2 className={`${fontStyles.heading} text-white`}>
                {currentQuestion.prompt}
              </h2>
              <p className="mt-3 text-[10px] text-indigo-400/80 font-medium">
                Tap card or wait to reveal answer options.
              </p>
            </div>

            <div className="flex items-center gap-3 border-t border-white/5 pt-4 shrink-0">
              <div className="w-8 h-8 rounded-full bg-slate-800 flex items-center justify-center text-xs">
                ⏱️
              </div>
              <div>
                <span className="text-[9px] text-slate-500 uppercase font-semibold block">Reading Time</span>
                <span className="text-xs font-bold text-slate-300">{Math.ceil(readingTimeLeft)}s remaining</span>
              </div>
            </div>
          </div>

          {/* CARD BACK: Answer Options & Grace Timer */}
          <div className="absolute inset-0 w-full h-full backface-hidden rounded-3xl bg-slate-900/90 border border-white/10 p-5 flex flex-col justify-between shadow-2xl rotate-y-180 overflow-hidden">
            <div className="flex justify-between items-center shrink-0">
              <span className="text-[9px] uppercase font-bold tracking-widest text-emerald-400 bg-emerald-500/10 px-2.5 py-1 rounded-md border border-emerald-500/20">
                Select Option
              </span>
              {/* Submission Grace Period Timer indicator */}
              {isGraceActive && (
                <span className="text-[10px] text-amber-500 font-mono font-medium animate-pulse">
                  Submitting in {graceTimeLeft.toFixed(1)}s
                </span>
              )}
            </div>

            {/* Grace period indicator bar */}
            <div className="w-full h-1 bg-white/5 rounded-full overflow-hidden mt-3 shrink-0">
              <div 
                className="h-full bg-amber-500 transition-all duration-100" 
                style={{ width: isGraceActive ? `${(graceTimeLeft / 2) * 100}%` : '0%' }}
              />
            </div>

            {/* Options list */}
            <div className="my-auto flex flex-col gap-2 py-3 overflow-y-auto">
              {currentQuestion.options.map((opt, idx) => {
                const isSelected = selectedAnswer === idx;
                const isCorrect = opt.score > 0;

                let borderClass = 'border-white/5 hover:bg-white/5 hover:border-white/10';
                let bgClass = 'bg-slate-950/40';
                let indicator = (
                  <span className="w-5 h-5 rounded-full border border-slate-700 flex items-center justify-center text-[10px] font-bold text-slate-500">
                    {String.fromCharCode(65 + idx)}
                  </span>
                );

                if (submissionResult) {
                  if (isSelected) {
                    if (submissionResult.correct) {
                      borderClass = 'border-emerald-500/50';
                      bgClass = 'bg-emerald-500/15';
                      indicator = <span className="text-emerald-500 font-bold text-sm">✓</span>;
                    } else {
                      borderClass = 'border-rose-500/50';
                      bgClass = 'bg-rose-500/15';
                      indicator = <span className="text-rose-500 font-bold text-sm">✗</span>;
                    }
                  } else if (isCorrect) {
                    // Reveal the correct option
                    borderClass = 'border-emerald-500/30';
                    bgClass = 'bg-emerald-500/5';
                  }
                } else if (isSelected) {
                  borderClass = 'border-amber-500/40 bg-amber-500/5';
                }

                return (
                  <button
                    key={idx}
                    disabled={submissionResult !== null}
                    onClick={() => handleSelectOption(idx)}
                    onDoubleClick={() => handleDoubleClickOption(idx)}
                    onTouchStart={() => handleTouchStartOption(idx)}
                    onTouchEnd={handleTouchEndOption}
                    onMouseDown={() => handleTouchStartOption(idx)}
                    onMouseUp={handleTouchEndOption}
                    onMouseLeave={handleTouchEndOption}
                    className={`w-full text-left p-3.5 rounded-xl border flex items-center justify-between gap-3 transition-all active:scale-[0.99] cursor-pointer ${bgClass} ${borderClass}`}
                  >
                    <span className={`${fontStyles.option} text-slate-200 leading-tight`}>
                      {opt.text}
                    </span>
                    {indicator}
                  </button>
                );
              })}
            </div>

            {/* Hint or Instruction footer */}
            <div className="border-t border-white/5 pt-2 shrink-0 flex items-center justify-between text-[10px] text-slate-500 italic">
              <span>Double-click option to submit instantly</span>
              <span>2s grace allows selection correction</span>
            </div>
          </div>
        </div>
      </div>

      {/* Telemetry submission slide-up feedback panel */}
      {submissionResult && (
        <div className="absolute inset-x-0 bottom-0 bg-slate-950/95 border-t border-white/10 rounded-t-3xl p-5 shadow-2xl z-20 flex flex-col justify-between animate-slide-up h-[180px]">
          <div className="flex items-start gap-3">
            <span className="text-2xl shrink-0">
              {submissionResult.correct ? '🎉' : '💡'}
            </span>
            <div>
              <h4 className={`font-bold leading-none ${submissionResult.correct ? 'text-emerald-400' : 'text-rose-400'}`}>
                {submissionResult.correct ? 'Excellent Answer!' : 'Incorrect Choice'}
              </h4>
              <p className="mt-2 text-xs text-slate-400 leading-relaxed max-h-[70px] overflow-y-auto">
                {submissionResult.explanation}
              </p>
            </div>
          </div>

          <div className="flex justify-between items-center border-t border-white/5 pt-3 mt-auto shrink-0">
            <span className="text-[10px] font-mono text-slate-500">
              Points: {submissionResult.correct ? `+${submissionResult.score} XP` : '0 XP'}
            </span>
            <button
              onClick={handleNextQuestion}
              className="bg-indigo-600 hover:bg-indigo-500 active:scale-95 text-white text-xs font-bold px-4 py-2 rounded-xl transition-all cursor-pointer shadow-lg shadow-indigo-500/10"
            >
              Next Question
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
