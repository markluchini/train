export type Difficulty = 'Beginner' | 'Intermediate' | 'Complex';

export interface AnswerOption {
  text: string;
  score: number; // percentage of total value (e.g. 100 for correct, 0 for incorrect)
  explanation: string;
}

export interface Question {
  id: number;
  prompt: string;
  mediaPath?: string;
  difficulty: Difficulty;
  options: AnswerOption[];
}

export interface QuestionWindow {
  history: Question[];
  current: Question | null;
  prefetch: Question[];
}
