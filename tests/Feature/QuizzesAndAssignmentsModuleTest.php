<?php

namespace Tests\Feature;

use App\Mail\AssignmentPostedMail;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class QuizzesAndAssignmentsModuleTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    private Subject $subject;
    private Student $kofi;
    private User $pupilLogin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
        Mail::fake();
        \Storage::fake('public');

        $this->subject = Subject::where('level', 9)->first();
        $this->kofi = $this->pupil('ASANTE Kofi', $this->basic4);
        $this->pupilLogin = $this->pupilUser($this->kofi);
    }

    private function quizForm(array $override = []): array
    {
        return $override + ['title' => 'Fractions Quiz', 'subject_id' => $this->subject->id, 'duration_minutes' => 20, 'pass_percentage' => 50];
    }

    private function question(Quiz $quiz, string $text, string $correct = '1', float $marks = 2): void
    {
        $this->post(route('quizzes.questions.store', $quiz), [
            'question_text' => $text, 'type' => 'mcq', 'options' => ['Three', 'Four', 'Five'], 'correct_answer' => $correct, 'marks' => $marks,
        ])->assertSessionHasNoErrors();
    }

    // ------------------------------------------------------------------ quizzes

    public function test_staff_build_a_quiz_with_questions(): void
    {
        $this->actingAs($this->staff('Super Admin'));

        $this->get(route('quizzes.index'))->assertOk();
        $this->get(route('quizzes.create'))->assertOk();

        $this->post(route('quizzes.store'), $this->quizForm(['title' => '']))->assertSessionHasErrors('title');
        $this->post(route('quizzes.store'), $this->quizForm(['duration_minutes' => 0]))->assertSessionHasErrors('duration_minutes');
        $this->post(route('quizzes.store'), $this->quizForm(['pass_percentage' => 120]))->assertSessionHasErrors('pass_percentage');
        $this->post(route('quizzes.store'), $this->quizForm(['has_negative_marking' => 1]))->assertSessionHasErrors('negative_mark_value');
        $this->post(route('quizzes.store'), $this->quizForm(['start_at' => '2027-01-02 10:00', 'end_at' => '2027-01-01 10:00']))->assertSessionHasErrors('end_at');
        // A quiz for a Basic 4 subject cannot be aimed at a JHS class.
        $this->post(route('quizzes.store'), $this->quizForm(['class_group_id' => $this->jhs1->id]))->assertSessionHasErrors('class_group_id');

        $this->post(route('quizzes.store'), $this->quizForm(['class_group_id' => $this->basic4->id]))->assertRedirect();
        $quiz = Quiz::first();
        $this->assertTrue((bool) $quiz->is_active);

        $this->post(route('quizzes.questions.store', $quiz), ['question_text' => 'x', 'type' => 'mcq', 'options' => ['a'], 'correct_answer' => '0', 'marks' => 1])->assertSessionHasErrors('options');
        $this->post(route('quizzes.questions.store', $quiz), ['question_text' => 'x', 'type' => 'mcq', 'options' => ['a', 'b'], 'correct_answer' => '5', 'marks' => 1])->assertSessionHasErrors('correct_answer');
        $this->question($quiz, '1 + 3 = ?', '1');
        $this->post(route('quizzes.questions.store', $quiz), ['question_text' => 'The sky is blue', 'type' => 'true_false', 'correct_answer' => 'true', 'marks' => 1])->assertSessionHasNoErrors();

        $question = $quiz->questions()->first();
        $this->assertSame('B', $question->correct_answer);                         // index 1 stored as the option key
        $this->get(route('quizzes.show', $quiz))->assertOk()->assertSee('Fractions Quiz');
        $this->get(route('quizzes.edit', $quiz))->assertOk();
        $this->get(route('quizzes.questions.create', $quiz))->assertOk();
        $this->get(route('quizzes.questions.edit', [$quiz, $question]))->assertOk();
        $this->get(route('quizzes.results', $quiz))->assertOk();

        $this->put(route('quizzes.update', $quiz), $this->quizForm(['title' => 'Fractions Quiz v2', 'is_active' => 1, 'class_group_id' => $this->basic4->id]))->assertRedirect(route('quizzes.show', $quiz));
        $this->assertSame('Fractions Quiz v2', $quiz->fresh()->title);

        $this->put(route('quizzes.questions.update', [$quiz, $question]), ['question_text' => '1 + 3?', 'type' => 'mcq', 'options' => ['3', '4'], 'correct_answer' => '1', 'marks' => 3])->assertRedirect();
        $this->assertEquals(3, $question->fresh()->marks);
        $this->delete(route('quizzes.questions.destroy', [$quiz, $question]))->assertRedirect();
        $this->assertSame(1, $quiz->questions()->count());
        $this->delete(route('quizzes.destroy', $quiz))->assertRedirect(route('quizzes.index'));
        $this->assertSame(0, Quiz::count());
    }

    public function test_a_pupil_takes_a_quiz_and_is_graded(): void
    {
        $this->actingAs($this->staff('Super Admin'));
        $this->post(route('quizzes.store'), $this->quizForm(['has_negative_marking' => 1, 'negative_mark_value' => 1, 'allow_retake' => 0]));
        $quiz = Quiz::first();
        $this->question($quiz, 'Q1', '1', 2);     // B
        $this->question($quiz, 'Q2', '0', 2);     // A
        $this->question($quiz, 'Q3', '2', 2);     // C
        [$q1, $q2, $q3] = $quiz->questions()->orderBy('id')->get()->all();

        $this->assertSame(1, $quiz->eligibleStudents()->count());          // Kofi - a Basic 4 pupil
        $this->actingAs($this->pupilLogin);

        $this->get(route('student.quizzes.index'))->assertOk()->assertSee('Fractions Quiz');
        $this->get(route('student.quizzes.show', $quiz))->assertOk();
        $this->get(route('student.quizzes.take', $quiz))->assertRedirect(route('student.quizzes.show', $quiz));      // not started

        $this->post(route('student.quizzes.start', $quiz))->assertRedirect(route('student.quizzes.take', $quiz));
        $this->get(route('student.quizzes.take', $quiz))->assertOk();

        $this->postJson(route('student.quizzes.save-answer', $quiz), ['question_id' => $q1->id, 'selected_option' => 'B'])->assertJson(['status' => 'saved']);   // right
        $this->postJson(route('student.quizzes.save-answer', $quiz), ['question_id' => $q2->id, 'selected_option' => 'C'])->assertJson(['status' => 'saved']);   // wrong: -1
        // Q3 left blank.

        $this->post(route('student.quizzes.submit', $quiz))->assertRedirect();
        $attempt = QuizAttempt::first();
        $this->assertSame('completed', $attempt->status);
        $this->assertEquals(1, $attempt->correct_count);
        $this->assertEquals(1.0, (float) $attempt->score);                  // 2 - 1 negative
        $this->assertEquals(6.0, (float) $attempt->total_marks);
        $this->assertEquals(16.67, (float) $attempt->percentage);
        $this->assertFalse((bool) $attempt->is_passed);

        $this->get(route('student.quizzes.result', $attempt))->assertOk();

        // No retake allowed: starting again goes back to the result, and nothing new is created.
        $this->post(route('student.quizzes.start', $quiz))->assertRedirect(route('student.quizzes.result', $attempt));
        $this->assertSame(1, QuizAttempt::count());

        // Another pupil cannot read this result.
        $other = $this->pupilUser($this->pupil('OTHER Pupil', $this->basic4));
        $this->actingAs($other)->get(route('student.quizzes.result', $attempt))->assertForbidden();
    }

    public function test_pupils_cannot_see_quizzes_for_other_levels_or_closed_ones(): void
    {
        $this->actingAs($this->staff('Super Admin'));
        $jhsSubject = Subject::where('level', 12)->first();
        $this->post(route('quizzes.store'), $this->quizForm(['title' => 'JHS Only', 'subject_id' => $jhsSubject->id]));
        $this->post(route('quizzes.store'), $this->quizForm(['title' => 'Not Yet Open', 'start_at' => now()->addDay()->toDateTimeString()]));
        $this->post(route('quizzes.store'), $this->quizForm(['title' => 'Empty Quiz']));
        $jhs = Quiz::where('title', 'JHS Only')->first();
        $future = Quiz::where('title', 'Not Yet Open')->first();
        $empty = Quiz::where('title', 'Empty Quiz')->first();
        $this->question($future, 'Q', '0');

        $this->actingAs($this->pupilLogin);
        $this->get(route('student.quizzes.index'))->assertOk()->assertDontSee('JHS Only');
        $this->get(route('student.quizzes.show', $jhs))->assertForbidden();
        $this->post(route('student.quizzes.start', $jhs))->assertForbidden();

        $this->post(route('student.quizzes.start', $future))->assertSessionHas('error');                  // not open yet
        $this->post(route('student.quizzes.start', $empty))->assertSessionHas('error');                   // no questions
        $this->assertSame(0, QuizAttempt::count());
    }

    public function test_an_expired_attempt_is_graded_when_reopened(): void
    {
        $this->actingAs($this->staff('Super Admin'));
        $this->post(route('quizzes.store'), $this->quizForm());
        $quiz = Quiz::first();
        $this->question($quiz, 'Q1', '1');

        $this->actingAs($this->pupilLogin);
        $this->post(route('student.quizzes.start', $quiz));
        $attempt = QuizAttempt::first();
        $question = $quiz->questions()->first();
        $this->postJson(route('student.quizzes.save-answer', $quiz), ['question_id' => $question->id, 'selected_option' => 'B']);

        $attempt->update(['ends_at' => now()->subMinute()]);
        $this->postJson(route('student.quizzes.save-answer', $quiz), ['question_id' => $question->id, 'selected_option' => 'A'])->assertStatus(403);   // too late to change

        $this->get(route('student.quizzes.take', $quiz))->assertRedirect(route('student.quizzes.result', $attempt));
        $this->assertSame('completed', $attempt->fresh()->status);
        $this->assertEquals(100.0, (float) $attempt->fresh()->percentage);                      // the saved answer counts
    }

    public function test_a_teacher_only_manages_quizzes_for_subjects_they_teach(): void
    {
        $this->subject->teachers()->attach($this->teacher->id);
        $other = Subject::where('level', 14)->first();
        $this->actingAs($this->staff('Super Admin'));
        $this->post(route('quizzes.store'), $this->quizForm(['title' => 'Mine']));
        $this->post(route('quizzes.store'), $this->quizForm(['title' => 'Theirs', 'subject_id' => $other->id]));
        $theirs = Quiz::where('title', 'Theirs')->first();

        $this->actingAs($this->teacherUser());
        $this->get(route('quizzes.index'))->assertOk()->assertSee('Mine')->assertDontSee('Theirs');
        $this->get(route('quizzes.show', $theirs))->assertForbidden();
        $this->delete(route('quizzes.destroy', $theirs))->assertForbidden();
        $this->post(route('quizzes.store'), $this->quizForm(['subject_id' => $other->id]))->assertSessionHasErrors('subject_id');
    }

    // -------------------------------------------------------------- assignments

    private function assignmentForm(array $override = []): array
    {
        return $override + ['title' => 'Read chapter 3', 'subject_id' => $this->subject->id, 'class_group_ids' => [$this->basic4->id], 'due_at' => now()->addDays(3)->toDateTimeString(), 'notify_students' => 0];
    }

    public function test_staff_set_edit_and_delete_an_assignment_with_level_checks(): void
    {
        $this->actingAs($this->staff('Super Admin'));
        $this->get(route('assignments.index'))->assertOk();
        $this->get(route('assignments.create'))->assertOk();

        $this->post(route('assignments.store'), $this->assignmentForm(['title' => '']))->assertSessionHasErrors('title');
        $this->post(route('assignments.store'), $this->assignmentForm(['class_group_ids' => []]))->assertSessionHasErrors('class_group_ids');
        $this->post(route('assignments.store'), $this->assignmentForm(['due_at' => now()->subDay()->toDateTimeString()]))->assertSessionHasErrors('due_at');
        $this->post(route('assignments.store'), $this->assignmentForm(['class_group_ids' => [$this->jhs1->id]]))->assertSessionHasErrors('class_group_ids');   // wrong level

        $this->post(route('assignments.store'), $this->assignmentForm(['attachment' => UploadedFile::fake()->create('notes.pdf', 100)]))->assertRedirect();
        $assignment = Assignment::first();
        \Storage::disk('public')->assertExists($assignment->attachment_path);
        $this->assertSame([$this->basic4->id], $assignment->classGroups()->pluck('class_groups.id')->all());

        $this->get(route('assignments.show', $assignment))->assertOk();
        $this->get(route('assignments.edit', $assignment))->assertOk();
        $this->put(route('assignments.update', $assignment), $this->assignmentForm(['title' => 'Read chapter 4', 'is_active' => 1]))->assertRedirect();
        $this->assertSame('Read chapter 4', $assignment->fresh()->title);

        $path = $assignment->attachment_path;
        $this->delete(route('assignments.destroy', $assignment))->assertRedirect(route('assignments.index'));
        $this->assertSame(0, Assignment::count());
        \Storage::disk('public')->assertMissing($path);
    }

    public function test_assignment_emails_go_to_the_pupil_or_failing_that_the_parent(): void
    {
        $this->kofi->update(['parent_email' => 'mum@example.test']);
        $akua = $this->pupil('BOATENG Akua', $this->basic4, 'Female', ['email' => 'akua@example.test']);
        $this->pupil('NOEMAIL Pupil', $this->basic4);

        $this->actingAs($this->staff('Super Admin'));
        $this->post(route('assignments.store'), $this->assignmentForm(['notify_students' => 1]))->assertRedirect();

        Mail::assertSent(AssignmentPostedMail::class, 2);
        Mail::assertSent(AssignmentPostedMail::class, fn ($m) => $m->hasTo('mum@example.test'));
        Mail::assertSent(AssignmentPostedMail::class, fn ($m) => $m->hasTo('akua@example.test'));
        $this->assertNotNull(Assignment::first()->notified_at);
    }

    public function test_a_pupil_submits_work_and_the_teacher_grades_it(): void
    {
        $this->actingAs($this->staff('Super Admin'));
        $this->post(route('assignments.store'), $this->assignmentForm(['max_score' => 20]));
        $assignment = Assignment::first();

        $this->actingAs($this->pupilLogin);
        $this->get(route('student.assignments.index'))->assertOk()->assertSee('Read chapter 3');
        $this->get(route('student.assignments.show', $assignment))->assertOk();

        $this->post(route('student.assignments.submit', $assignment), [])->assertSessionHas('error');                       // nothing to hand in
        $this->post(route('student.assignments.submit', $assignment), ['submission_text' => 'My answer', 'file' => UploadedFile::fake()->create('work.pdf', 50)])->assertRedirect();

        $submission = AssignmentSubmission::first();
        $this->assertFalse((bool) $submission->is_late);
        \Storage::disk('public')->assertExists($submission->file_path);

        // Handing in again replaces the previous submission.
        $this->post(route('student.assignments.submit', $assignment), ['submission_text' => 'Better answer']);
        $this->assertSame(1, AssignmentSubmission::count());
        $this->assertSame('Better answer', $submission->fresh()->submission_text);

        $this->actingAs($this->staff('Super Admin'));
        $this->post(route('assignments.submissions.grade', [$assignment, $submission]), ['score' => 25, 'feedback' => 'x'])->assertSessionHasErrors('score');    // above the maximum
        $this->post(route('assignments.submissions.grade', [$assignment, $submission]), ['score' => 17, 'feedback' => 'Well done'])->assertSessionHasNoErrors();
        $this->assertEquals(17, $submission->fresh()->score);
        $this->assertNotNull($submission->fresh()->graded_at);
    }

    public function test_late_work_needs_late_submission_to_be_allowed_and_other_classes_are_shut_out(): void
    {
        $this->actingAs($this->staff('Super Admin'));
        $this->post(route('assignments.store'), $this->assignmentForm());
        $assignment = Assignment::first();
        $assignment->update(['due_at' => now()->subDay(), 'allow_late_submission' => false]);

        $this->actingAs($this->pupilLogin);
        $this->post(route('student.assignments.submit', $assignment), ['submission_text' => 'Late work'])->assertSessionHas('error');
        $this->assertSame(0, AssignmentSubmission::count());

        $assignment->update(['allow_late_submission' => true]);
        $this->post(route('student.assignments.submit', $assignment), ['submission_text' => 'Late work'])->assertRedirect();
        $this->assertTrue((bool) AssignmentSubmission::first()->is_late);

        $jhsPupil = $this->pupilUser($this->pupil('ADJEI Esi', $this->jhs1, 'Female'));
        $this->actingAs($jhsPupil)->get(route('student.assignments.show', $assignment))->assertForbidden();
        $this->post(route('student.assignments.submit', $assignment), ['submission_text' => 'Sneaky'])->assertForbidden();
    }

    public function test_a_teacher_only_manages_assignments_for_subjects_they_teach(): void
    {
        $this->subject->teachers()->attach($this->teacher->id);
        $other = Subject::where('level', 14)->first();
        $final = \App\Models\ClassGroup::create(['name' => 'JHS 3 Gold', 'level' => 14]);

        $this->actingAs($this->staff('Super Admin'));
        $this->post(route('assignments.store'), $this->assignmentForm(['title' => 'Mine']));
        $this->post(route('assignments.store'), $this->assignmentForm(['title' => 'Theirs', 'subject_id' => $other->id, 'class_group_ids' => [$final->id]]));
        $theirs = Assignment::where('title', 'Theirs')->first();

        $this->actingAs($this->teacherUser());
        $this->get(route('assignments.index'))->assertOk()->assertSee('Mine')->assertDontSee('Theirs');
        $this->get(route('assignments.show', $theirs))->assertForbidden();
        $this->delete(route('assignments.destroy', $theirs))->assertForbidden();
    }
}
