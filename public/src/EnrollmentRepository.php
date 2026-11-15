<?php
declare(strict_types=1);

class EnrollmentRepository extends Repository
{
    public const GRADES = ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D', 'F'];

    private const SELECT =
        'SELECT e.id, e.student_id, e.course_id, e.grade, s.first AS student_first, s.last AS student_last, c.code AS course_code
         FROM enrollments e
         JOIN students s ON s.id = e.student_id
         JOIN courses c ON c.id = e.course_id';

    public function all(): array
    {
        return array_map([$this, 'addName'], $this->rows(self::SELECT . ' ORDER BY s.last, s.first, c.code'));
    }

    protected function row(int $id): ?array
    {
        $r = $this->rows(self::SELECT . ' WHERE e.id = ?', [$id])[0] ?? null;
        return $r === null ? null : $this->addName($r);
    }

    public function create(array $data): array
    {
        [$studentId, $courseId, $grade] = $this->validate($data, 0);
        $this->run('INSERT INTO enrollments (student_id, course_id, grade) VALUES (?, ?, ?)', [$studentId, $courseId, $grade]);
        // The (student, course) pair is unique, so we use it to read the new row back.
        $r = $this->rows(self::SELECT . ' WHERE e.student_id = ? AND e.course_id = ?', [$studentId, $courseId])[0];
        return $this->addName($r);
    }

    public function update(int $id, array $data): array
    {
        $this->find($id);
        [$studentId, $courseId, $grade] = $this->validate($data, $id);
        $this->run('UPDATE enrollments SET student_id = ?, course_id = ?, grade = ? WHERE id = ?', [$studentId, $courseId, $grade, $id]);
        return $this->find($id);
    }

    /** Enrollments can always be deleted. */
    public function delete(int $id): void
    {
        $this->find($id);
        $this->run('DELETE FROM enrollments WHERE id = ?', [$id]);
    }

    /** The transcript of one student: a join of enrollments and courses, plus the total credits. */
    public function transcript(int $studentId): array
    {
        $this->requireExists('students', $studentId, 'student');
        $courses = $this->rows(
            'SELECT c.code, c.title, c.credits, e.grade
             FROM enrollments e
             JOIN courses c ON c.id = e.course_id
             WHERE e.student_id = ?
             ORDER BY c.code',
            [$studentId]
        );
        $total = 0;
        foreach ($courses as $c) {
            $total += (int)$c['credits'];
        }
        return ['courses' => $courses, 'total_credits' => $total];
    }

    /** @return array [student_id, course_id, grade] */
    private function validate(array $data, int $ownId): array
    {
        $studentId = Validator::id($data['student_id'] ?? null, 'Student');
        $courseId  = Validator::id($data['course_id'] ?? null, 'Course');
        $grade     = Validator::oneOf($data, 'grade', 'Grade', self::GRADES);
        $this->requireExists('students', $studentId, 'student');
        $this->requireExists('courses', $courseId, 'course');

        $dup = (int)$this->scalar(
            'SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND course_id = ? AND id <> ?',
            [$studentId, $courseId, $ownId]
        );
        if ($dup > 0) {
            throw new ApiException('This student is already enrolled in this course.', 409);
        }
        return [$studentId, $courseId, $grade];
    }

    /** Adds "Last, First" built in PHP, because string concatenation differs between databases. */
    private function addName(array $r): array
    {
        $r['student_name'] = $r['student_last'] . ', ' . $r['student_first'];
        return $r;
    }
}
