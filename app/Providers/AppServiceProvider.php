<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Gate::policy(\App\Models\Exam::class, \App\Policies\ExamPolicy::class);
        Gate::policy(\App\Models\Question::class, \App\Policies\QuestionPolicy::class);
        Gate::policy(\App\Models\Subject::class, \App\Policies\SubjectPolicy::class);
        Gate::policy(\App\Models\ExamSession::class, \App\Policies\ExamSessionPolicy::class);
        Gate::policy(\App\Models\User::class, \App\Policies\UserPolicy::class);

        // Env-tunable rate limits (per IP). Test-safe defaults; tighten in
        // production via AUTH_RATE_LIMIT / EXAM_RATE_LIMIT (see .env.example).
        RateLimiter::for('auth', function ($request) {
            return Limit::perMinute((int) config('rate_limits.auth', 1000))->by($request->ip());
        });
        RateLimiter::for('exam', function ($request) {
            return Limit::perMinute((int) config('rate_limits.exam', 1000))->by($request->ip());
        });
        // Per-session write throttle so one spamming client can't eat the
        // shared per-IP exam budget of 100 students behind one NAT.
        // Limiters resolve through the default cache store — set
        // CACHE_STORE=failover in production to back them with Redis.
        RateLimiter::for('exam-answers', function ($request) {
            $session = $request->route('session');
            $sessionId = $session instanceof \App\Models\ExamSession ? $session->id : $session;

            return Limit::perMinute((int) config('rate_limits.exam_answers', 60))
                ->by('session:'.$sessionId.'|'.$request->ip());
        });
    }
}
