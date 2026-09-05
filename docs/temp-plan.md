# Plan: Separate Student and Teacher Profile Tables

## Problem
Currently all users (students, teachers, admins) are stored in a single `users` table with a `role` column and a single `name` field. Student-specific attributes (roll number, department, semester, academic year) and teacher-specific attributes (department, designation) do not have a dedicated home, making the domain model less expressive and harder to extend.

## Proposed Approach
Keep `users` as the authentication/authorization table (credentials + role) and introduce dedicated `students` and `teachers` profile tables. This is the least disruptive path: existing role-based access control (`isStudent()`, `isTeacher()`, `isAdmin()`) continues to work, while profile data lives in its own tables with proper fields and relationships.

## Schema Changes
1. **Create `students` table**
    - `id` (PK)
    - `user_id` (unique FK → users.id, cascade on delete)
    - `name`
    - `roll_number` (unique)
    - `department`
    - `semester`
    - `academic_year`
    - timestamps

2. **Create `teachers` table**
    - `id` (PK)
    - `user_id` (unique FK → users.id, cascade on delete)
    - `name`
    - `department`
    - `designation`
    - timestamps

3. **Keep `users` table focused on auth**
    - `id`, `email`, `password`, `role` (enum: admin/student/teacher), `email_verified_at`, `remember_token`, timestamps
    - `name` can remain for display fallback/admin users, but student/teacher names should primarily come from their profile tables.

## Model Changes
1. **User model**
    - Add `student()` hasOne relationship.
    - Add `teacher()` hasOne relationship.
    - Keep `isStudent()` / `isTeacher()` / `isAdmin()` role helpers.
    - Add convenience accessors/methods to resolve display name from profile when available.

2. **New Student model**
    - Fillable: `user_id`, `name`, `roll_number`, `department`, `semester`, `academic_year`.
    - BelongsTo User.

3. **New Teacher model**
    - Fillable: `user_id`, `name`, `department`, `designation`.
    - BelongsTo User.

## Seeding
1. Update `DatabaseSeeder` to create matching `Student` and `Teacher` records for seeded users.
2. Update `UserFactory` so factory-created users can optionally generate a profile based on role, or create dedicated `StudentFactory` / `TeacherFactory`.

## Registration (Students Only)
The public `/register` route is for student self-registration only. Teachers and admins continue to be created through the admin panel.

1. **Update `AuthController::register()`**
    - Remove `role` from validation; hardcode `role` to `student`.
    - Validate and capture student profile fields: `name`, `roll_number`, `department`, `semester`, `academic_year`.
    - Create the `User` with `role = 'student'`.
    - Create the related `Student` profile record.

2. **Update `resources/views/auth/register.blade.php`**
    - Remove the role dropdown.
    - Add inputs for `roll_number`, `department`, `semester`, and `academic_year`.
    - Keep `name`, `email`, `password`, and `password_confirmation`.

## Admin User Management
1. Update `Admin\UserController::store()` and `update()` to create/update the related profile record based on role.
2. Update admin user create/edit Blade forms to capture profile fields.
   Admins can create users with roles **admin** or **teacher** only. Students register themselves via the public `/register` form, so the admin user creation form will not offer a "student" role.

1. **Update `Admin\UserController`**
    - Restrict `role` validation to `in:admin,teacher` in `store()` and `update()`.
    - When `role` is `teacher`, validate and create/update the related `Teacher` profile (`department`, `designation`).
    - When `role` is `admin`, create/update only the `User` record with no extra profile.
    - Keep existing soft-delete and self-delete protection.

2. **Update admin user create/edit Blade forms**
    - Role dropdown limited to **Admin** and **Teacher**.
    - Add dynamic profile fields (department, designation) shown only when **Teacher** is selected.
    - Use a small JS snippet to toggle teacher fields based on the selected role.

## Relationship Updates (optional but recommended)
- `Exam::teacher()` can continue pointing to `User` (teacher_id stays as user_id), or be updated to point to `Teacher` if the foreign key is changed. For minimal disruption, keep `teacher_id` as `users.id` and resolve the Teacher profile when needed.
- `ExamSession::student()` / `teacher()` can similarly stay as User relationships or be augmented with profile relationships.

## Verification
- Run migrations and seeders successfully.
- Confirm seeded students and teachers have profile records.
- Confirm student registration creates a `student` user and a matching `Student` profile.
- Confirm the register form no longer shows a role dropdown and accepts student profile fields.
- Confirm existing role-based access control still works.
- Run `composer run test` to ensure no regressions.

## Notes / Considerations
- This is a structural refactor. Existing data in the `users` table will not automatically gain profile records; a data migration or manual seeding step is needed for current users if they need profile data.
- Keeping the `role` column avoids rewriting middleware, gates, and dashboard routing logic.
- If the user later wants to drop `name` from `users`, admin users would need a profile table too, or `name` can stay as a display name on users while profile tables hold extended data.

