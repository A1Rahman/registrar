<?php
declare(strict_types=1);

class StudentRepository extends Repository
{
    private const COLUMNS = 'id, first, last, email, birthdate, date_of_enrollment';

    public function all(): array
    {
        return $this->rows('SELECT ' . self::COLUMNS . ' FROM students ORDER BY last, first');
    }

    protected function row(int $id): ?array
    {
        return $this->rows('SELECT ' . self::COLUMNS . ' FROM students WHERE id = ?', [$id])[0] ?? null;
    }

    public function create(array $data): array
    {
        $v = $this->validate($data, 0);
        $this->run(
            'INSERT INTO students (first, last, email, birthdate, date_of_enrollment) VALUES (?, ?, ?, ?, ?)',
            [$v['first'], $v['last'], $v['email'], $v['birthdate'], $v['date_of_enrollment']]
        );
        // Email is unique, so we use it to read the new row back (avoids database-specific "last insert id" code).
        return $this->rows('SELECT ' . self::COLUMNS . ' FROM students WHERE email = ?', [$v['email']])[0];
    }

    public function update(int $id, array $data): array
    {
        $this->find($id);
        $v = $this->validate($data, $id);
        $this->run(
            'UPDATE students SET first = ?, last = ?, email = ?, birthdate = ?, date_of_enrollment = ? WHERE id = ?',
            [$v['first'], $v['last'], $v['email'], $v['birthdate'], $v['date_of_enrollment'], $id]
        );
        return $this->find($id);
    }

    public function delete(int $id): void
    {
        $this->find($id);
        // Rule 2: a student with enrollments cannot be deleted.
        if ((int)$this->scalar('SELECT COUNT(*) FROM enrollments WHERE student_id = ?', [$id]) > 0) {
            throw new ApiException('This student has enrollments. Delete the enrollments first.', 409);
        }
        $this->run('DELETE FROM students WHERE id = ?', [$id]);
    }

    /** Checks every field; $ownId is the student being edited (0 when creating) so its own email is not a duplicate. */
    private function validate(array $data, int $ownId): array
    {
        $v = [
            'first'              => Validator::text($data, 'first', 'First name', 100),
            'last'               => Validator::text($data, 'last', 'Last name', 100),
            'email'              => Validator::email($data, 'email', 'Email'),
            'birthdate'          => Validator::date($data, 'birthdate', 'Birthdate'),
            'date_of_enrollment' => Validator::date($data, 'date_of_enrollment', 'Date of enrollment'),
        ];
        if ((int)$this->scalar('SELECT COUNT(*) FROM students WHERE email = ? AND id <> ?', [$v['email'], $ownId]) > 0) {
            throw new ApiException('A student with this email already exists.', 409);
        }
        return $v;
    }
}
