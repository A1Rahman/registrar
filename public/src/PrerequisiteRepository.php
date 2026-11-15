<?php
declare(strict_types=1);

/** Each row means: course_id requires prereq_id. */
class PrerequisiteRepository extends Repository
{
    private const SELECT =
        'SELECT p.id, p.course_id, p.prereq_id, c.code AS course_code, r.code AS prereq_code
         FROM course_prerequisites p
         JOIN courses c ON c.id = p.course_id
         JOIN courses r ON r.id = p.prereq_id';

    public function all(): array
    {
        return $this->rows(self::SELECT . ' ORDER BY c.code, r.code');
    }

    protected function row(int $id): ?array
    {
        return $this->rows(self::SELECT . ' WHERE p.id = ?', [$id])[0] ?? null;
    }

    public function create(array $data): array
    {
        [$courseId, $prereqId] = $this->validate($data, 0);
        $this->run('INSERT INTO course_prerequisites (course_id, prereq_id) VALUES (?, ?)', [$courseId, $prereqId]);
        // The (course, prerequisite) pair is unique, so we use it to read the new row back.
        return $this->rows(self::SELECT . ' WHERE p.course_id = ? AND p.prereq_id = ?', [$courseId, $prereqId])[0];
    }

    public function update(int $id, array $data): array
    {
        $this->find($id);
        [$courseId, $prereqId] = $this->validate($data, $id);
        $this->run('UPDATE course_prerequisites SET course_id = ?, prereq_id = ? WHERE id = ?', [$courseId, $prereqId, $id]);
        return $this->find($id);
    }

    /** Prerequisites can always be deleted. */
    public function delete(int $id): void
    {
        $this->find($id);
        $this->run('DELETE FROM course_prerequisites WHERE id = ?', [$id]);
    }

    /**
     * Checks the references, duplicates and Rule 1 (no circular prerequisites).
     * $ownId is the row being edited (0 when creating); that row is ignored because it is being replaced.
     * @return int[] [course_id, prereq_id]
     */
    private function validate(array $data, int $ownId): array
    {
        $courseId = Validator::id($data['course_id'] ?? null, 'Course');
        $prereqId = Validator::id($data['prereq_id'] ?? null, 'Required course');
        $this->requireExists('courses', $courseId, 'course');
        $this->requireExists('courses', $prereqId, 'required course');

        if ($courseId === $prereqId) {
            throw new ApiException('A course cannot be its own prerequisite.', 400);
        }

        $dup = (int)$this->scalar(
            'SELECT COUNT(*) FROM course_prerequisites WHERE course_id = ? AND prereq_id = ? AND id <> ?',
            [$courseId, $prereqId, $ownId]
        );
        if ($dup > 0) {
            throw new ApiException('This prerequisite already exists.', 409);
        }

        // "A requires B" makes a loop if B already requires A, directly or through a chain.
        if ($this->requires($prereqId, $courseId, $ownId)) {
            $a = $this->scalar('SELECT code FROM courses WHERE id = ?', [$courseId]);
            $b = $this->scalar('SELECT code FROM courses WHERE id = ?', [$prereqId]);
            throw new ApiException("Circular prerequisite: $b already requires $a (directly or through other courses).", 409);
        }
        return [$courseId, $prereqId];
    }

    /**
     * True if course $from requires course $target, directly or through a chain.
     * Walks the prerequisite graph depth-first, skipping the row $ignoreId.
     */
    private function requires(int $from, int $target, int $ignoreId): bool
    {
        $graph = [];
        foreach ($this->rows('SELECT course_id, prereq_id FROM course_prerequisites WHERE id <> ?', [$ignoreId]) as $e) {
            $graph[(int)$e['course_id']][] = (int)$e['prereq_id'];
        }

        $stack = [$from];
        $seen = [];
        while ($stack) {
            $current = array_pop($stack);
            if ($current === $target) {
                return true;
            }
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            foreach ($graph[$current] ?? [] as $next) {
                $stack[] = $next;
            }
        }
        return false;
    }
}
