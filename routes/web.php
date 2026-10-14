<?php

use App\Http\Controllers\ArrearsController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeeStructureController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentPaymentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;

// Authentication Routes
Route::middleware('guest')->group(function () {
    // Admin/Staff Login
    Route::get('login', [\App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [\App\Http\Controllers\Auth\LoginController::class, 'login']);
    
    // Student Login
    Route::get('student/login', [\App\Http\Controllers\Auth\StudentAuthController::class, 'showLoginForm'])->name('student.login');
    Route::post('student/login', [\App\Http\Controllers\Auth\StudentAuthController::class, 'login']);

    // Student Password Reset (via SMS)
    Route::get('student/forgot-password', [\App\Http\Controllers\Auth\StudentPasswordResetController::class, 'showRequestForm'])->name('student.password.request');
    Route::post('student/forgot-password', [\App\Http\Controllers\Auth\StudentPasswordResetController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('student.password.email');
    Route::post('student/forgot-password/email', [\App\Http\Controllers\Auth\StudentPasswordResetController::class, 'sendResetLinkByEmail'])->middleware('throttle:5,1')->name('student.password.email-request');
    Route::get('student/reset-password/{token}', [\App\Http\Controllers\Auth\StudentPasswordResetController::class, 'showResetForm'])->name('student.password.reset');
    Route::post('student/reset-password', [\App\Http\Controllers\Auth\StudentPasswordResetController::class, 'reset'])->name('student.password.update');
    
    // Password Reset
    Route::get('password/reset', [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('password/email', [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:5,1')->name('password.email');
    Route::get('password/reset/{token}', [\App\Http\Controllers\Auth\ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('password/reset', [\App\Http\Controllers\Auth\ResetPasswordController::class, 'reset'])->name('password.update');
});

Route::post('logout', [\App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');
Route::post('student/logout', [\App\Http\Controllers\Auth\StudentAuthController::class, 'logout'])->name('student.logout');

// Public report card - no login required, only reachable via a signed URL texted to a
// parent (see GradeNotificationService). The `signed` middleware rejects any request
// whose signature is missing, tampered with, or past its expiry.
Route::get('report-card/{student}', [\App\Http\Controllers\ReportCardController::class, 'show'])
    ->name('report-card.show')
    ->middleware('signed');

// Guest Routes
Route::middleware(['guest'])->group(function () {
    // Route::redirect() (not a closure) - closures can't survive route:cache, since
    // Laravel serializes the route file with var_export() and a Closure has no valid
    // __set_state() representation, causing "Call to undefined method Closure::__set_state()"
    // as soon as the cached routes file is loaded.
    Route::redirect('/', '/login');
});

// Admin Dashboard and Root Routes
Route::middleware(['auth', 'admin'])->group(function () {
    Route::middleware('permission:view-activity-logs')->group(function () {
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('/activity-logs/export', [ActivityLogController::class, 'export'])->name('activity-logs.export');
    });
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
});

// Protected Routes
Route::middleware(['auth'])->group(function () {
    // /dashboard is intentionally NOT redefined here - it's already registered above under
    // ['auth', 'admin']. A duplicate registration here previously silently dropped that
    // middleware (Laravel's route table dedupes by method+URI, last registration wins),
    // letting a logged-in student bypass the admin-only gate by visiting /dashboard directly.

    // Password Change Routes
    Route::get('/password/change', [\App\Http\Controllers\PasswordController::class, 'edit'])->name('password.change');
    // Named distinctly from 'password.update' below (the guest forgot-password-via-token POST
    // route) - sharing that name made route('password.update') always resolve to whichever was
    // registered last, breaking whichever feature's form needed the other one.
    Route::put('/password/update', [\App\Http\Controllers\PasswordController::class, 'update'])->name('password.change.update');

    // Top-bar live pupil search (access checked in the controller)
    Route::get('/search/pupils', \App\Http\Controllers\PupilSearchController::class)->name('search.pupils');

    // User Profile
    Route::get('/profile', [UserController::class, 'profile'])->name('profile');
    Route::put('/profile', [UserController::class, 'updateProfile'])->name('profile.update');

    // Class lists: admins and office staff for any class, teachers for their own classes (checked in the controller).
    Route::get('/class-lists', [\App\Http\Controllers\ClassListController::class, 'index'])->name('class-lists.index');
    Route::get('/class-lists/{classGroup}', [\App\Http\Controllers\ClassListController::class, 'show'])->name('class-lists.show');
    Route::get('/class-lists/{classGroup}/file', [\App\Http\Controllers\ClassListController::class, 'file'])->name('class-lists.file');
    Route::get('/class-lists/{classGroup}/excel', [\App\Http\Controllers\ClassListController::class, 'excel'])->name('class-lists.excel');

    // Teacher self-service (not permission-gated - any account linked to a teacher profile)
    Route::get('/my-profile', [\App\Http\Controllers\TeacherPortalController::class, 'profile'])->name('teacher.profile.edit');
    Route::put('/my-profile', [\App\Http\Controllers\TeacherPortalController::class, 'updateProfile'])->name('teacher.profile.update');
    Route::get('/my-timetable', [\App\Http\Controllers\TeacherPortalController::class, 'timetable'])->name('teacher.timetable');
    Route::get('/my-timetable/print', [\App\Http\Controllers\TeacherPortalController::class, 'printTimetable'])->name('teacher.timetable.print');
    // Students
    Route::middleware('permission:manage-students')->group(function () {
        Route::get('/students/{id}/create-account', [StudentController::class, 'createUserAccount'])->name('students.create-account');
        Route::post('/students/bulk-create-accounts', [StudentController::class, 'bulkCreateUserAccounts'])->name('students.bulk-create-accounts');
        Route::post('/students/bulk-reset-passwords', [StudentController::class, 'bulkResetPasswords'])->name('students.bulk-reset-passwords');
        Route::get('/students/check-duplicates', [StudentController::class, 'duplicates'])->name('students.duplicates');
        Route::get('/students/find-family', [StudentController::class, 'family'])->name('students.family');
        Route::resource('students', StudentController::class);
        Route::get('/students/{student}/results', [StudentController::class, 'results'])->name('students.results');
        Route::get('/students-import', [StudentController::class, 'importForm'])->name('students.import.form');
        Route::post('/students-import', [StudentController::class, 'import'])->name('students.import');
        Route::get('/students/export/excel', [StudentController::class, 'exportExcel'])->name('students.export.excel');
        Route::get('/students/export/pdf', [StudentController::class, 'exportPdf'])->name('students.export.pdf');
        Route::get('/students-import-template', [StudentController::class, 'downloadTemplate'])->name('students.import.template');
    });

    // Student Directory - read-only, no grades (e.g. for Accountant)
    Route::middleware('permission:view-student-directory')->group(function () {
        Route::get('/student-directory', [\App\Http\Controllers\StudentDirectoryController::class, 'index'])->name('student-directory.index');
        Route::get('/student-directory/{student}', [\App\Http\Controllers\StudentDirectoryController::class, 'show'])->name('student-directory.show');
    });

    // Bulk SMS
    Route::middleware('permission:send-sms')->group(function () {
        Route::get('/sms', [\App\Http\Controllers\SmsController::class, 'index'])->name('sms.index');
        Route::post('/sms/send', [\App\Http\Controllers\SmsController::class, 'send'])->name('sms.send');
        Route::get('/sms/history', [\App\Http\Controllers\SmsController::class, 'history'])->name('sms.history');
        Route::get('/sms/history/{campaign}', [\App\Http\Controllers\SmsController::class, 'showCampaign'])->name('sms.history.show');
        Route::get('/parent-notices', [\App\Http\Controllers\ParentNoticeController::class, 'index'])->name('parent-notices.index');
        Route::post('/parent-notices', [\App\Http\Controllers\ParentNoticeController::class, 'send'])->name('parent-notices.send');
        Route::get('/sms/parent-notifications', [\App\Http\Controllers\SmsController::class, 'parentNotifications'])->name('sms.parent-notifications');
        Route::post('/students/{student}/send-grades', [StudentController::class, 'sendGrades'])->name('students.send-grades');
        Route::post('/semesters/{semester}/notify-parents', [\App\Http\Controllers\SemesterController::class, 'notifyParentsOfResults'])->name('semesters.notify-parents');
        Route::get('/sms/templates', [\App\Http\Controllers\SmsController::class, 'templates'])->name('sms.templates.index');
        Route::post('/sms/templates', [\App\Http\Controllers\SmsController::class, 'storeTemplate'])->name('sms.templates.store');
        Route::put('/sms/templates/{template}', [\App\Http\Controllers\SmsController::class, 'updateTemplate'])->name('sms.templates.update');
        Route::delete('/sms/templates/{template}', [\App\Http\Controllers\SmsController::class, 'destroyTemplate'])->name('sms.templates.destroy');
    });

    // Subjects
    Route::middleware('permission:manage-subjects')->group(function () {
        Route::resource('subjects', SubjectController::class);
        Route::get('/subjects/{subject}/students', [SubjectController::class, 'students'])->name('subjects.students');
        Route::get('/subjects/{subject}/students/export', [SubjectController::class, 'exportStudents'])->name('subjects.students.export');
        Route::get('/subjects/{subject}/students/attendance', [SubjectController::class, 'printAttendance'])->name('subjects.students.attendance');
        Route::get('/subjects/{subject}/results', [SubjectController::class, 'results'])->name('subjects.results');
        Route::get('/subjects/{subject}/export', [SubjectController::class, 'export'])->name('subjects.export');

        // Teachers
        Route::get('/teachers/check-duplicates', [\App\Http\Controllers\TeacherController::class, 'duplicates'])->name('teachers.duplicates');
        Route::get('/teachers/import',[\App\Http\Controllers\TeacherController::class, 'importForm'])->name('teachers.import.form');
        Route::post('/teachers/import', [\App\Http\Controllers\TeacherController::class, 'import'])->name('teachers.import.store');
        Route::get('/teachers/import/template', [\App\Http\Controllers\TeacherController::class, 'downloadTemplate'])->name('teachers.import.template');
        Route::get('/teachers/export/excel', [\App\Http\Controllers\TeacherController::class, 'exportExcel'])->name('teachers.export.excel');
        Route::get('/teachers/export/pdf', [\App\Http\Controllers\TeacherController::class, 'exportPdf'])->name('teachers.export.pdf');
        Route::post('/teachers/{teacher}/create-account', [\App\Http\Controllers\TeacherController::class, 'createUserAccount'])->name('teachers.create-account');
        Route::post('/teachers/{teacher}/reset-password', [\App\Http\Controllers\TeacherController::class, 'resetPassword'])->name('teachers.reset-password');
        Route::resource('teachers', \App\Http\Controllers\TeacherController::class);

        // Departments
        Route::resource('departments', \App\Http\Controllers\DepartmentController::class)->except(['show']);
    });

    // Results: scores are entered and changed on the class score sheet (/scores). The old
    // add/edit/bulk-upload URLs below only redirect there, so anyone who may enter scores can
    // follow them; deleting a score is limited to manage-results. These literal paths are
    // registered before the /results/{result} wildcard further down.
    Route::middleware('permission:enter-scores|manage-results')->group(function () {
        Route::resource('results', ResultController::class)->only(['create', 'store', 'edit', 'update']);
        Route::get('/results/bulk-upload', [ResultController::class, 'bulkCreate'])->name('results.bulk-create');
        Route::post('/results/bulk-store', [ResultController::class, 'bulkStore'])->name('results.bulk-store');
        Route::get('/bulkresults/download-template', [ResultController::class, 'downloadTemplate'])->name('results.download-template');
    });
    Route::delete('/results/{result}', [ResultController::class, 'destroy'])->middleware('permission:manage-results')->name('results.destroy');

    Route::middleware('permission:manage-results|view-results|enter-scores|manage-term-reports')->group(function () {
        Route::get('/results/search', [ResultController::class, 'search'])->name('results.search');
        Route::get('/results/filter/academic-year', [ResultController::class, 'filterByAcademicYear'])->name('results.filter.academic-year');
        Route::get('/results/filter/semester', [ResultController::class, 'filterBySemester'])->name('results.filter.semester');
        Route::get('/results/filter/student', [ResultController::class, 'filterByStudent'])->name('results.filter.student');
        Route::get('/results/filter/subject', [ResultController::class, 'filterBySubject'])->name('results.filter.subject');
        Route::resource('results', ResultController::class)->only(['index', 'show']);

        // Report cards viewed/printed directly from the admin panel - individually, for a
        // hand-picked selection of students, or for a whole class at once. Distinct from the
        // signed public link in report-card.show, which parents use without logging in.
        Route::get('/students/{student}/report-card', [\App\Http\Controllers\ReportCardController::class, 'adminShow'])->name('students.report-card');
        Route::get('/report-cards/bulk', [\App\Http\Controllers\ReportCardController::class, 'bulkForm'])->name('report-cards.bulk-form');
        Route::get('/report-cards/print', [\App\Http\Controllers\ReportCardController::class, 'bulkPrint'])->name('report-cards.bulk-print');
    });

    // Terminal report score entry (class score + exam score per subject) and class broadsheet.
    // Teachers are limited to their own classes/subjects inside ScoreSheetController.
    Route::middleware('permission:enter-scores|manage-results')->group(function () {
        Route::get('/scores', [\App\Http\Controllers\ScoreSheetController::class, 'index'])->name('scores.index');
        Route::get('/scores/{classGroup}/broadsheet', [\App\Http\Controllers\ScoreSheetController::class, 'broadsheet'])->name('scores.broadsheet');
        Route::get('/scores/{classGroup}/{subject}', [\App\Http\Controllers\ScoreSheetController::class, 'sheet'])->name('scores.sheet');
        Route::post('/scores/{classGroup}/{subject}', [\App\Http\Controllers\ScoreSheetController::class, 'save'])->name('scores.save');
    });

    // Transcripts: a pupil's record across several classes (e.g. Basic 1 to Basic 6).
    Route::middleware('permission:manage-results|manage-students|view-reports')->group(function () {
        Route::get('/transcripts', [\App\Http\Controllers\TranscriptController::class, 'index'])->name('transcripts.index');
        Route::get('/transcripts/print', [\App\Http\Controllers\TranscriptController::class, 'bulk'])->name('transcripts.bulk');
        Route::get('/students/{student}/transcript', [\App\Http\Controllers\TranscriptController::class, 'show'])->name('students.transcript');
    });

    // Attendance, conduct and remarks for the terminal report (class teachers / head teacher).
    Route::middleware('permission:manage-term-reports')->group(function () {
        Route::get('/term-reports', [\App\Http\Controllers\TermReportController::class, 'index'])->name('term-reports.index');
        Route::post('/term-reports/{classGroup}', [\App\Http\Controllers\TermReportController::class, 'save'])->name('term-reports.save');
    });

    // Semesters
    Route::middleware('permission:manage-semesters')->group(function () {
        Route::get('/semesters/filter/academic-year', [SemesterController::class, 'filterByAcademicYear'])->name('semesters.filter.academic-year');
        Route::put('/semesters/{semester}/set-current', [SemesterController::class, 'setCurrent'])->name('semesters.set-current');
        Route::get('/semesters/{semester}/results', [SemesterController::class, 'results'])->name('semesters.results');
        Route::resource('semesters', SemesterController::class);
    });

    // Fee Management
    Route::middleware('permission:manage-fees')->group(function () {
        // Term fee schedule (fee items x class levels, per term) and term billing.
        Route::get('/fee-structures', [FeeStructureController::class, 'index'])->name('fee-structures.index');
        Route::post('/fee-structures/save', [FeeStructureController::class, 'save'])->name('fee-structures.save');
        Route::post('/fee-structures/copy', [FeeStructureController::class, 'copy'])->name('fee-structures.copy');
        Route::get('/fees/billing', [FeeStructureController::class, 'billingForm'])->name('fees.billing');
        Route::post('/fees/billing', [FeeStructureController::class, 'bill'])->name('fees.billing.store');

        // Items billed to pupils (term bills + one-off items like bus fee, uniform, discounts).
        Route::get('/fees/charges', [\App\Http\Controllers\StudentFeeChargeController::class, 'index'])->name('fees.charges.index');
        Route::get('/fees/charges/upload', [\App\Http\Controllers\StudentFeeChargeController::class, 'uploadForm'])->name('fees.charges.upload');
        Route::post('/fees/charges/import', [\App\Http\Controllers\StudentFeeChargeController::class, 'import'])->name('fees.charges.import');
        Route::get('/fees/charges/template', [\App\Http\Controllers\StudentFeeChargeController::class, 'downloadTemplate'])->name('fees.charges.template');
        Route::post('/fees/charges/bulk-destroy', [\App\Http\Controllers\StudentFeeChargeController::class, 'bulkDestroy'])->name('fees.charges.bulk-destroy');
        Route::delete('/fees/charges/{charge}', [\App\Http\Controllers\StudentFeeChargeController::class, 'destroy'])->name('fees.charges.destroy');

        // Fee items (tuition, feeding, PTA levy, and any custom ones an admin adds).
        Route::post('/fees/categories', [\App\Http\Controllers\FeeCategoryController::class, 'store'])->name('fees.categories.store');
        Route::delete('/fees/categories/{category}', [\App\Http\Controllers\FeeCategoryController::class, 'destroy'])->name('fees.categories.destroy');

        Route::get('/fees', [StudentPaymentController::class, 'index'])->name('fees.index');
        Route::post('/fees/reminders', [StudentPaymentController::class, 'sendReminders'])->name('fees.reminders');
        Route::get('/fees/export/excel', [StudentPaymentController::class, 'exportExcel'])->name('fees.export.excel');
        Route::get('/fees/export/pdf', [StudentPaymentController::class, 'exportPdf'])->name('fees.export.pdf');
        Route::get('/fees/report', [StudentPaymentController::class, 'report'])->name('fees.report');
        Route::get('/fees/report/print', [StudentPaymentController::class, 'printReport'])->name('fees.report.print');
        Route::get('/fees/report/export', [StudentPaymentController::class, 'exportReport'])->name('fees.report.export');
        Route::get('/fees/receipts/{payment}', [StudentPaymentController::class, 'receipt'])->name('fees.receipt');

        // Arrears (debtors list) routes must be registered before the /fees/{student} wildcard below.
        Route::get('/fees/arrears', [ArrearsController::class, 'index'])->name('fees.arrears.index');
        Route::get('/fees/arrears/upload', [ArrearsController::class, 'uploadForm'])->name('fees.arrears.upload');
        Route::post('/fees/arrears/import', [ArrearsController::class, 'import'])->name('fees.arrears.import');
        Route::get('/fees/arrears/template', [ArrearsController::class, 'downloadTemplate'])->name('fees.arrears.template');
        Route::post('/fees/arrears/bulk-destroy', [ArrearsController::class, 'bulkDestroy'])->name('fees.arrears.bulk-destroy');
        Route::delete('/fees/arrears/{arrear}', [ArrearsController::class, 'destroy'])->name('fees.arrears.destroy');

        // Bulk billing and collection (before the /fees/{student} wildcard below).
        Route::get('/fees/bulk-bill', [\App\Http\Controllers\BulkBillController::class, 'create'])->name('fees.bulk-bill');
        Route::post('/fees/bulk-bill/preview', [\App\Http\Controllers\BulkBillController::class, 'preview'])->name('fees.bulk-bill.preview');
        Route::post('/fees/bulk-bill', [\App\Http\Controllers\BulkBillController::class, 'store'])->name('fees.bulk-bill.store');
        Route::get('/fees/collect', [\App\Http\Controllers\PaymentCollectionController::class, 'sheet'])->name('fees.collect');
        Route::post('/fees/collect', [\App\Http\Controllers\PaymentCollectionController::class, 'storeSheet'])->name('fees.collect.store');
        Route::get('/fees/payments/upload', [\App\Http\Controllers\PaymentCollectionController::class, 'uploadForm'])->name('fees.payments.upload');
        Route::post('/fees/payments/import', [\App\Http\Controllers\PaymentCollectionController::class, 'import'])->name('fees.payments.import');
        Route::get('/fees/payments/template', [\App\Http\Controllers\PaymentCollectionController::class, 'template'])->name('fees.payments.template');
        Route::post('/fees/payments/bulk-destroy', [\App\Http\Controllers\PaymentCollectionController::class, 'bulkDestroy'])->name('fees.payments.bulk-destroy');

        Route::get('/fees/{student}', [StudentPaymentController::class, 'show'])->name('fees.show');
        Route::post('/fees/{student}/charges', [\App\Http\Controllers\StudentFeeChargeController::class, 'store'])->name('fees.charges.store');
        Route::get('/fees/{student}/print', [StudentPaymentController::class, 'printLedger'])->name('fees.print');
        Route::post('/fees/{student}/payments', [StudentPaymentController::class, 'store'])->name('fees.payments.store');
        Route::put('/fees/payments/{payment}', [StudentPaymentController::class, 'update'])->name('fees.payments.update');
        Route::delete('/fees/payments/{payment}', [StudentPaymentController::class, 'destroy'])->name('fees.payments.destroy');
    });

    // Classes
    Route::middleware('permission:manage-classes')->group(function () {
        Route::get('/class-groups/import', [\App\Http\Controllers\ClassGroupController::class, 'importForm'])->name('class-groups.import.form');
        Route::post('/class-groups/import', [\App\Http\Controllers\ClassGroupController::class, 'import'])->name('class-groups.import.store');
        Route::get('/class-groups/import/template', [\App\Http\Controllers\ClassGroupController::class, 'downloadTemplate'])->name('class-groups.import.template');
        Route::get('/class-groups/{classGroup}/print', [\App\Http\Controllers\ClassGroupController::class, 'print'])->name('class-groups.print');
        Route::get('/class-groups/{classGroup}/assign-students', [\App\Http\Controllers\ClassGroupController::class, 'assignStudentsForm'])->name('class-groups.assign-students');
        Route::post('/class-groups/{classGroup}/assign-students', [\App\Http\Controllers\ClassGroupController::class, 'assignStudents'])->name('class-groups.assign-students.store');
        Route::resource('class-groups', \App\Http\Controllers\ClassGroupController::class)->except(['show']);

        // Student level promotion (new academic year workflow)
        Route::get('/promotions', [\App\Http\Controllers\PromotionController::class, 'index'])->name('promotions.index');
        Route::post('/promotions/preview', [\App\Http\Controllers\PromotionController::class, 'preview'])->name('promotions.preview');
        Route::post('/promotions', [\App\Http\Controllers\PromotionController::class, 'store'])->name('promotions.store');
    });

    // Timetable
    Route::middleware('permission:manage-timetable')->group(function () {
        Route::get('/timetable/print', [\App\Http\Controllers\TimetableController::class, 'print'])->name('timetable.print');
        Route::resource('timetable', \App\Http\Controllers\TimetableController::class)->except(['show']);
        Route::resource('venues', \App\Http\Controllers\VenueController::class)->except(['show']);
    });

    // Continuous assessment is now part of the class score sheet (Enter Scores), with the columns
    // designed under Settings > Score Sheets. The old college-style screens redirect there.
    Route::middleware('permission:manage-continuous-assessment')->group(function () {
        Route::redirect('/continuous-assessment', '/scores')->name('continuous-assessment.index');
        Route::redirect('/continuous-assessment/{any}', '/scores')->where('any', '.*');
    });

    // Quizzes - a teacher only sees/manages quizzes for subjects they're assigned to teach
    // (enforced in QuizController::authorizeQuiz), same scoping as continuous assessment above.
    Route::middleware('permission:manage-quizzes')->group(function () {
        Route::get('/quizzes', [\App\Http\Controllers\QuizController::class, 'index'])->name('quizzes.index');
        Route::get('/quizzes/create', [\App\Http\Controllers\QuizController::class, 'create'])->name('quizzes.create');
        Route::post('/quizzes', [\App\Http\Controllers\QuizController::class, 'store'])->name('quizzes.store');
        Route::get('/quizzes/{quiz}', [\App\Http\Controllers\QuizController::class, 'show'])->name('quizzes.show');
        Route::get('/quizzes/{quiz}/edit', [\App\Http\Controllers\QuizController::class, 'edit'])->name('quizzes.edit');
        Route::put('/quizzes/{quiz}', [\App\Http\Controllers\QuizController::class, 'update'])->name('quizzes.update');
        Route::delete('/quizzes/{quiz}', [\App\Http\Controllers\QuizController::class, 'destroy'])->name('quizzes.destroy');
        Route::get('/quizzes/{quiz}/results', [\App\Http\Controllers\QuizController::class, 'results'])->name('quizzes.results');

        Route::get('/quizzes/{quiz}/questions/create', [\App\Http\Controllers\QuizController::class, 'createQuestion'])->name('quizzes.questions.create');
        Route::post('/quizzes/{quiz}/questions', [\App\Http\Controllers\QuizController::class, 'storeQuestion'])->name('quizzes.questions.store');
        Route::get('/quizzes/{quiz}/questions/{question}/edit', [\App\Http\Controllers\QuizController::class, 'editQuestion'])->name('quizzes.questions.edit');
        Route::put('/quizzes/{quiz}/questions/{question}', [\App\Http\Controllers\QuizController::class, 'updateQuestion'])->name('quizzes.questions.update');
        Route::delete('/quizzes/{quiz}/questions/{question}', [\App\Http\Controllers\QuizController::class, 'destroyQuestion'])->name('quizzes.questions.destroy');
    });

    // Assignments - a teacher only sees/manages assignments for subjects they're assigned to
    // teach (enforced in AssignmentController::authorizeAssignment), same scoping as quizzes.
    Route::middleware('permission:manage-assignments')->group(function () {
        Route::get('/assignments', [\App\Http\Controllers\AssignmentController::class, 'index'])->name('assignments.index');
        Route::get('/assignments/create', [\App\Http\Controllers\AssignmentController::class, 'create'])->name('assignments.create');
        Route::post('/assignments', [\App\Http\Controllers\AssignmentController::class, 'store'])->name('assignments.store');
        Route::get('/assignments/{assignment}', [\App\Http\Controllers\AssignmentController::class, 'show'])->name('assignments.show');
        Route::get('/assignments/{assignment}/edit', [\App\Http\Controllers\AssignmentController::class, 'edit'])->name('assignments.edit');
        Route::put('/assignments/{assignment}', [\App\Http\Controllers\AssignmentController::class, 'update'])->name('assignments.update');
        Route::delete('/assignments/{assignment}', [\App\Http\Controllers\AssignmentController::class, 'destroy'])->name('assignments.destroy');
        Route::post('/assignments/{assignment}/notify', [\App\Http\Controllers\AssignmentController::class, 'notifyNow'])->name('assignments.notify');
        Route::post('/assignments/{assignment}/submissions/{submission}/grade', [\App\Http\Controllers\AssignmentController::class, 'gradeSubmission'])->name('assignments.submissions.grade');
    });

    // Reports
    Route::middleware('permission:view-reports')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    });

    // Student Dashboard Routes
    Route::middleware(['auth'])->group(function () {
        // 'student' alias (StudentMiddleware, registered in AdminServiceProvider), not a raw
        // closure - closures can't survive route:cache (var_export() has no valid
        // __set_state() form for a Closure), which crashed production with "Call to undefined
        // method Closure::__set_state()" as soon as this route file was cached.
        Route::group(['middleware' => ['student', 'first.login'], 'prefix' => 'student', 'as' => 'student.'], function () {
            Route::get('/dashboard', [\App\Http\Controllers\Auth\StudentAuthController::class, 'dashboard'])->name('dashboard');
            Route::get('/report-card', [\App\Http\Controllers\ReportCardController::class, 'studentShow'])->name('report-card');


            Route::get('/fees', [\App\Http\Controllers\StudentFeeController::class, 'index'])->name('fees.index');

            // Quizzes
            Route::get('/quizzes', [\App\Http\Controllers\StudentQuizController::class, 'index'])->name('quizzes.index');
            Route::get('/quizzes/{quiz}', [\App\Http\Controllers\StudentQuizController::class, 'show'])->name('quizzes.show');
            Route::post('/quizzes/{quiz}/start', [\App\Http\Controllers\StudentQuizController::class, 'start'])->name('quizzes.start');
            Route::get('/quizzes/{quiz}/take', [\App\Http\Controllers\StudentQuizController::class, 'take'])->name('quizzes.take');
            Route::post('/quizzes/{quiz}/save-answer', [\App\Http\Controllers\StudentQuizController::class, 'saveAnswer'])->name('quizzes.save-answer');
            Route::post('/quizzes/{quiz}/submit', [\App\Http\Controllers\StudentQuizController::class, 'submit'])->name('quizzes.submit');
            Route::get('/quizzes/attempts/{attempt}/result', [\App\Http\Controllers\StudentQuizController::class, 'result'])->name('quizzes.result');

            // Assignments
            Route::get('/assignments', [\App\Http\Controllers\StudentAssignmentController::class, 'index'])->name('assignments.index');
            Route::get('/assignments/{assignment}', [\App\Http\Controllers\StudentAssignmentController::class, 'show'])->name('assignments.show');
            Route::post('/assignments/{assignment}/submit', [\App\Http\Controllers\StudentAssignmentController::class, 'submit'])->name('assignments.submit');

            Route::get('/timetable', [\App\Http\Controllers\Auth\StudentAuthController::class, 'timetable'])->name('timetable');
            Route::get('/timetable/print', [\App\Http\Controllers\Auth\StudentAuthController::class, 'printTimetable'])->name('timetable.print');

            Route::get('/profile/edit', [\App\Http\Controllers\Auth\StudentAuthController::class, 'editProfile'])->name('profile.edit');
            Route::post('/profile/update', [\App\Http\Controllers\Auth\StudentAuthController::class, 'updateProfile'])->name('profile.update');
            Route::post('/profile/photo/remove', [\App\Http\Controllers\Auth\StudentAuthController::class, 'removeProfilePhoto'])->name('profile.photo.remove');

            // Password change - kept inside this properly student-gated group (a previous,
            // separate registration for these two routes had only 'auth' middleware, with no
            // student-role check, letting any logged-in non-student account reach them).
            Route::get('/change-password', [\App\Http\Controllers\Auth\StudentAuthController::class, 'showChangePasswordForm'])->name('change-password');
            Route::post('/update-password', [\App\Http\Controllers\Auth\StudentAuthController::class, 'updatePassword'])->name('update-password');
        });
    });
    
    // Admin Only Routes
    Route::middleware(['auth', 'admin'])->group(function () {
        // User Management
        Route::middleware('permission:manage-users')->group(function () {
            Route::resource('users', UserController::class);
            Route::get('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::put('/users/{user}/update-password', [UserController::class, 'updatePassword'])->name('users.update-password');
        });

        // Roles & Permissions
        Route::middleware('permission:manage-roles')->group(function () {
            Route::resource('roles', \App\Http\Controllers\RoleController::class)->except(['show']);
        });

        // Settings
        Route::middleware('permission:manage-settings')->group(function () {
            Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');

            // Academic Year Settings
            Route::get('/settings/academic-years', [SettingController::class, 'academicYears'])->name('settings.academic-years');
            Route::get('/settings/academic-years/create', [SettingController::class, 'createAcademicYear'])->name('settings.academic-years.create');
            Route::post('/settings/academic-years', [SettingController::class, 'storeAcademicYear'])->name('settings.academic-years.store');
            Route::get('/settings/academic-years/{academicYear}/edit', [SettingController::class, 'editAcademicYear'])->name('settings.academic-years.edit');
            Route::put('/settings/academic-years/{academicYear}', [SettingController::class, 'updateAcademicYear'])->name('settings.academic-years.update');
            Route::delete('/settings/academic-years/{academicYear}', [SettingController::class, 'destroyAcademicYear'])->name('settings.academic-years.destroy');
            Route::put('/settings/academic-years/{academicYear}/set-current', [SettingController::class, 'setCurrentAcademicYear'])->name('settings.academic-years.set-current');

            // Grade Scheme Settings
            Route::get('/settings/grade-schemes', [SettingController::class, 'gradeSchemes'])->name('settings.grade-schemes');
            Route::get('/settings/grade-schemes/create', [SettingController::class, 'createGradeScheme'])->name('settings.grade-schemes.create');
            Route::post('/settings/grade-schemes', [SettingController::class, 'storeGradeScheme'])->name('settings.grade-schemes.store');
            Route::get('/settings/grade-schemes/{gradeScheme}/edit', [SettingController::class, 'editGradeScheme'])->name('settings.grade-schemes.edit');
            Route::put('/settings/grade-schemes/{gradeScheme}', [SettingController::class, 'updateGradeScheme'])->name('settings.grade-schemes.update');
            Route::delete('/settings/grade-schemes/{gradeScheme}', [SettingController::class, 'destroyGradeScheme'])->name('settings.grade-schemes.destroy');
            Route::patch('/settings/grade-schemes/{gradeScheme}/set-default', [SettingController::class, 'setDefaultGradeScheme'])->name('settings.grade-schemes.set-default');

            // Database Backup
            Route::get('/settings/backup', [SettingController::class, 'backup'])->name('settings.backup');
            Route::post('/settings/backup/create', [SettingController::class, 'backupDatabase'])->name('settings.backup.create');
            Route::get('/settings/backup/{filename}/download', [SettingController::class, 'downloadBackup'])->name('settings.backup.download');
            Route::delete('/settings/backup/{filename}', [SettingController::class, 'destroyBackup'])->name('settings.backup.destroy');

            // System Settings
            Route::get('/settings/system', [SettingController::class, 'system'])->name('settings.system');
            Route::put('/settings/system', [SettingController::class, 'updateSystem'])->name('settings.system.update');

            // School & report settings (levels offered, score split, admission numbers)
            Route::get('/settings/school', [SettingController::class, 'school'])->name('settings.school');
            Route::put('/settings/school', [SettingController::class, 'updateSchool'])->name('settings.school.update');

            // Institution Settings
            Route::get('/settings/institution', [SettingController::class, 'institution'])->name('settings.institution');
            Route::put('/settings/institution', [SettingController::class, 'updateInstitution'])->name('settings.institution.update');

            // Score sheets: the columns teachers enter marks on (continuous assessment + exam), per class level.
            Route::get('/assessment-settings', [\App\Http\Controllers\AssessmentSettingController::class, 'index'])->name('assessment-settings.index');
            Route::get('/assessment-settings/{level}/edit', [\App\Http\Controllers\AssessmentSettingController::class, 'edit'])->whereNumber('level')->name('assessment-settings.edit');
            Route::put('/assessment-settings/{level}', [\App\Http\Controllers\AssessmentSettingController::class, 'update'])->whereNumber('level')->name('assessment-settings.update');
            Route::delete('/assessment-settings/{level}', [\App\Http\Controllers\AssessmentSettingController::class, 'destroy'])->whereNumber('level')->name('assessment-settings.destroy');
            // The old college-style CA score settings screen now lives here.
            Route::redirect('/ca-score-settings', '/assessment-settings')->name('ca-score-settings.index');
        });

        // Old SHS spreadsheet result upload - scores are entered on the class score sheet now.
        Route::middleware('permission:manage-results')->group(function () {
            Route::redirect('/bulkresults/upload', '/scores')->name('bulkresults.upload');
        });
    });
});
