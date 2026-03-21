# Concurrent Endpoint Testing Plan (100 Live Students + Monitoring)

## Objective
Validate that the system can support approximately 100 students concurrently taking an exam while teacher/admin monitoring remains responsive and accurate.

## Scope
- Student runtime endpoints:
  - start exam session
  - begin exam (lobby/proceed)
  - save answer (high-frequency)
  - log violation (intermittent)
  - sync timer/status polling
  - submit exam
- Teacher/admin runtime endpoints:
  - monitoring page load
  - monitoring session polling endpoint
  - warning/resume/force-end actions under load
- Realtime behavior:
  - broadcast delivery to monitoring UI and student channels
  - event lag under concurrent traffic

## Test Philosophy
Run a realistic scenario test, not only synthetic endpoint hammering:
1. Users start together in a short window.
2. They continue mixed traffic for a sustained period.
3. Monitoring endpoints are polled in parallel.
4. A subset of moderation actions is injected during peak load.

## Recommended Setup

### Environment
- Use a staging-like environment with:
  - same DB engine and queue backend as production intent
  - queue worker running
  - Reverb running
  - APP_DEBUG disabled
- Ensure no unrelated jobs are running.

### Data Preparation
- Create one published exam with:
  - 40 to 60 questions (mixed types)
  - realistic timing (for example 60 minutes)
- Seed/load:
  - 1 teacher (or admin) owner
  - 100 student accounts
  - optional: pre-created sessions if you want to isolate take-flow from start-flow

## Workload Model (Realistic)

### Phase A: Warmup (2-3 minutes)
- 10 users start and enter exam.
- Verify zero functional errors.

### Phase B: Ramp (5 minutes)
- Increase from 10 to 100 active students.
- Students perform:
  - answer save every 15-40 seconds (randomized)
  - timer/status sync as app naturally does
  - occasional violation events for 5-10% of users

### Phase C: Steady State (15-20 minutes)
- Keep ~100 active users.
- Continue mixed student behavior.
- In parallel, monitoring traffic runs continuously:
  - monitoring sessions endpoint every 3-10 seconds
  - one monitoring page refresh pattern
- Inject moderation actions:
  - warning to random students
  - resume paused sessions
  - optional force-end for a small sample

### Phase D: Submission Burst (2-5 minutes)
- 60-100 students submit within a short window.
- Confirm response remains fast and grading continues asynchronously.

## Tooling Options

### Option 1 (Best Fit): k6 Scenario Script
Use k6 with multiple scenarios:
- scenario 1: student exam-taker flow (majority of VUs)
- scenario 2: monitoring poller flow (teacher/admin)
- scenario 3: moderation flow (few VUs)

Why k6:
- easy ramp/steady patterns
- thresholds for p95/p99 and error rate
- good CI integration

### Option 2: Artillery
Good for HTTP + socket style checks, but typically less developer-friendly than k6 for custom logic branching.

### Option 3: JMeter
Powerful GUI, heavier operational overhead; useful if team already uses it.

## Authentication Strategy
- Pre-login and cache auth cookies/tokens per virtual user.
- Avoid measuring login endpoint unless auth throughput is in scope.
- Keep CSRF/session handling realistic if using web routes.

## Metrics to Capture

### Application Metrics
- endpoint latency (p50/p95/p99)
- HTTP error rate by endpoint
- queue backlog and job throughput
- broadcast/event lag (event emit time vs receive/render time)

### Database Metrics
- query count for monitoring endpoint
- slow queries and lock wait time
- connection usage and saturation

### Infrastructure Metrics
- CPU/memory on app, DB, queue workers
- network throughput
- process restarts/timeouts

## Suggested Performance Thresholds (Initial)
- save answer p95 < 500 ms
- monitoring sessions endpoint p95 < 800 ms
- submit endpoint p95 < 1200 ms
- error rate < 1%
- no sustained queue backlog growth after submission burst
- no websocket disconnect storms

## Functional Assertions During Load
- students see progress updates correctly
- monitoring table reflects active users accurately
- violation counts increase consistently
- warning/resume/force-end actions are delivered and applied
- submitted sessions eventually reach graded/completed state

## Test Execution Checklist
1. Confirm environment health and workers running.
2. Reset test DB state (or isolate with dedicated test exam).
3. Run a small smoke load (10 users).
4. Run full scenario (100 users).
5. Export reports and server logs.
6. Analyze bottlenecks by endpoint and component.
7. Re-run after each optimization with same script for comparison.

## Reporting Template
For each run, capture:
- run id/date/commit hash
- config (VUs, duration, ramp)
- threshold pass/fail
- top 5 slow endpoints
- error breakdown
- queue and DB observations
- action items and priority

## Rollout Strategy
- baseline run (current state)
- optimize one area at a time
- rerun same workload
- compare deltas (latency, error rate, event lag)
- stop when thresholds are consistently met for at least 3 consecutive runs

## What Not to Do
- Do not test with debug mode enabled.
- Do not run with shared noisy background jobs.
- Do not change workload shape between comparisons.
- Do not rely only on average latency; track p95/p99.

## Next Step (After Approval)
After your consent, we can generate:
1. a concrete k6 script for student + monitoring + moderation scenarios,
2. seed/test-data setup commands,
3. a one-command runbook for repeatable benchmark runs.
