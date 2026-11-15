<?php
declare(strict_types=1);

class CourseRepository extends Repository
{
    public function all(): array
    {
        return $this->rows('SELECT id, code, title, credits FROM courses ORDER BY code');
    }

    protected function row(int $id): ?array
    {
        return $this->rows('SELECT id, code, title, credits FROM courses WHERE id = ?', [$id])[0] ?? null;
    }

    public function create(array $data): array
    {
        $v = $this->validate($data, 0);
        $this->run('INSERT INTO courses (code, title, credits) VALUES (?, ?, ?)', [$v['code'], $v['title'], $v['credits']]);
        // Code is unique, so we use it to read the new row back.
        return $this->rows('SELECT id, code, title, credits FROM courses WHERE code = ?', [$v['code']])[0];
    }

    public function update(int $id, array $data): array
    {
        $this->find($id);
        $v = $this->validate($data, $id);
        $this->run('UPDATE courses SET code = ?, title = ?, credits = ? WHERE id = ?', [$v['code'], $v['title'], $v['credits'], $id]);
        return $this->find($id);
    }

    public function delete(int $id): void
    {
        $this->find($id);
        // Rule 2: a course in any prerequisite (either side) cannot be deleted.
        $links = (int)$this->scalar(
            'SELECT COUNT(*) FROM course_prerequisites WHERE course_id = ? OR prereq_id = ?',
            [$id, $id]
        );
        if ($links > 0) {
            throw new ApiException('This course is part of a prerequisite chain. Remove its prerequisite links first.', 409);
        }
        // Rule 2: a course with enrollments cannot be deleted.
        if ((int)$this->scalar('SELECT COUNT(*) FROM enrollments WHERE course_id = ?', [$id]) > 0) {
            throw new ApiException('This course has enrollments. Delete the enrollments first.', 409);
        }
        $this->run('DELETE FROM courses WHERE id = ?', [$id]);
    }

    /** $ownId is the course being edited (0 when creating) so its own code is not a duplicate. */
    private function validate(array $data, int $ownId): array
    {
        $v = [
            'code'    => Validator::text($data, 'code', 'Code', 20),
            'title'   => Validator::text($data, 'title', 'Title', 200),
            'credits' => Validator::intRange($data, 'credits', 'Credits', 1, 6),
        ];
        if ((int)$this->scalar('SELECT COUNT(*) FROM courses WHERE code = ? AND id <> ?', [$v['code'], $ownId]) > 0) {
            throw new ApiException('A course with this code already exists.', 409);
        }
        return $v;
    }
}
