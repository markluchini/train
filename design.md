# Micro-Training Trivia Application

## Architecture

This document outlines the architectural design for a high-performance, asynchronous micro-training trivia platform designed for early-career employee engagement.

## System Overview

The system consists of two primary applications: the **Player App** (client-facing) and the **Admin Portal**. Both communicate with a central **API Service**. The architecture prioritizes low latency, mobile-responsiveness, and an asynchronous, non-blocking user experience.

## Core Technical Stack

*   **Frontend:** React (SPA) with Tailwind CSS for responsive styling.
*   **State Management:** Client-side state managed via a sliding-window buffer (history, current, pre-fetch).
*   **API Layer:** RESTful JSON services.
*   **Data Storage:** Initially a local JSON file store, abstracted via a repository pattern to ensure seamless migration to SQL/NoSQL databases.
*   **Language:** PHP
*   **Application FQDN:** [https://drluchini.com/training/](https://drluchini.com/training/)
*   **Admin FQDN:** [https://drluchini.com/training/admin](https://drluchini.com/training/admin)
*   **Default Question Data File:** [data_questions.json](file:///Users/mark/github/drluchini/training/data_questions.json)

## Data Structure Specifications

Questions are structured to support rich interactivity and analytics:

| Field | Description |
| :--- | :--- |
| **Prompt** | Text (Markdown supported) |
| **Image/Graphic Path** | Optional |
| **Difficulty** | Beginner, Intermediate, Complex |
| **Answer Metadata** | List of options with scores (percentage of total value) and explanation text for specific feedback. |

## Player App Interaction Logic

The `Field` component acts as the primary game container. Key interactions include:

*   **Card Flip Animation:** Transition between prompt and answer states.
*   **Configurable Timing:** User-defined reading timers; 2-second submission grace period; instant submission via long-press/double-click.
*   **Accessibility:** Dedicated menu for color themes, high-contrast modes, and screen reader compatibility.

## API & Logging

The backend logs detailed telemetry including device metadata, receipt/start/end timestamps, selection history, and score metrics. The Admin API supports full CRUD operations, bulk import/export (CSV/JSON), and usage summary reporting.

## Sequential Implementation Prompts

1.  **Project Initialization:** "Create a React boilerplate with Tailwind CSS. Implement the base layout structure using `100vh`/`100vw` container logic to ensure a non-scrolling mobile experience."
2.  **Data Model & Service Layer:** "Create a repository pattern for a JSON-based question store. Define the TypeScript interface for a `Question` object, including markdown, media, and scoring metadata. Create the REST endpoints for fetching a question by user ID."
3.  **Player UI - The Field:** "Implement the `Field` component with a CSS card-flip animation. Integrate the state management to hold the last 3, current, and next 3 questions."
4.  **Interaction Engine:** "Build the answer interaction logic: radio-button toggling, the 5-second reading timer, and the 2-second submission grace period. Implement long-press/double-click immediate submission."
5.  **Analytics & Logging:** "Create the submission API endpoint. Implement the tracking service that collects device metadata, answer history, and timestamps, and returns scores/feedback for the 'slide-up' display."
6.  **Admin Portal:** "Build a CRUD interface for administrators to create, read, update, and delete questions, including bulk CSV import/export functionality and basic analytics dashboards."

---

## Verification Results

All sequential implementation prompts have been successfully executed and validated:

### 1. Project Initialization
- **Files Created/Modified:**
  - [index.html](file:///Users/mark/github/drluchini/training/index.html): Standardized viewport tags to restrict mobile zoom/scaling and load premium fonts.
  - [package.json](file:///Users/mark/github/drluchini/training/package.json): Named package `micro-training-trivia` and configured Vite + Tailwind CSS v4 dependencies.
  - [vite.config.ts](file:///Users/mark/github/drluchini/training/vite.config.ts): Integrated the rust-powered `@tailwindcss/vite` plugin.
  - [src/index.css](file:///Users/mark/github/drluchini/training/src/index.css): Loaded Tailwind directives and custom 3D card rotation utility classes.
- **Status:** **Verified**. Dev environment builds successfully with zero compilation or syntax errors.

### 2. Data Model & Service Layer
- **Files Created/Modified:**
  - [src/types.ts](file:///Users/mark/github/drluchini/training/src/types.ts): Configured type safety interfaces for questions, selections, and sliding-window formats.
  - [data_questions.json](file:///Users/mark/github/drluchini/training/data_questions.json): Populated a mock database of 7 detailed technical questions with multiple difficulties and detailed explanations.
  - [api/QuestionRepository.php](file:///Users/mark/github/drluchini/training/api/QuestionRepository.php): Encapsulated JSON database file reading, querying, and slicing logic.
  - [api/UserProgressRepository.php](file:///Users/mark/github/drluchini/training/api/UserProgressRepository.php): Tracks and persists the active question index index for each user.
  - [api/question.php](file:///Users/mark/github/drluchini/training/api/question.php): Evaluates progress and serves structured `history`, `current`, and `prefetch` window collections.
- **Status:** **Verified**. Frontend strict types compile cleanly and API endpoints handle cross-origin preflights natively.

### 3. Player UI - The Field
- **Files Created/Modified:**
  - [src/components/Field.tsx](file:///Users/mark/github/drluchini/training/src/components/Field.tsx): Designed physical stacked-deck visuals representing buffered prefetch contents.
  - [src/App.tsx](file:///Users/mark/github/drluchini/training/src/App.tsx): Wired the game container, linking active scores, streak badges, and accessibility setting states.
- **Status:** **Verified**. The layout fills the device screen fully (`100vh`/`100vw`) and cards flip cleanly via 3D transforms.

### 4. Interaction Engine
- **Files Created/Modified:**
  - [src/components/Field.tsx](file:///Users/mark/github/drluchini/training/src/components/Field.tsx): Managed relative hooks for timers and click listeners.
- **Status:** **Verified**. The 5s auto-flip reading timer, 2s grace correction period, double-click bypass, and touch long-press triggers execute as specified.

### 5. Analytics & Logging
- **Files Created/Modified:**
  - [api/submit.php](file:///Users/mark/github/drluchini/training/api/submit.php): Decodes telemetry payloads and saves session details to `data_telemetry.json`.
  - [src/components/Field.tsx](file:///Users/mark/github/drluchini/training/src/components/Field.tsx): Wires the options to the submit POST route and displays explanation cards.
- **Status:** **Verified**. XP scores and streak counters increase correctly, and wrong answers reset status with appropriate corrections.

### 6. Admin Portal
- **Files Created/Modified:**
  - [admin/index.php](file:///Users/mark/github/drluchini/training/admin/index.php): Self-contained dashboard rendering question inventories, CRUD controls, JSON/CSV imports/exports, and reporting tables.
- **Status:** **Verified**. Admin functions perform correct question mutations and compute telemetry accuracy metrics.